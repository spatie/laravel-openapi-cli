<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\CommandNameGenerator;
use Spatie\OpenApiCli\HumanReadableFormatter;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\OutputHighlighter;
use Spatie\OpenApiCli\SpecResolver;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Yaml\Yaml;

class EndpointCommand extends Command
{
    protected $signature;

    protected $description;

    /** @var array<string, string> */
    private array $pathParamOptionMap = [];

    /** @var array<string, string> */
    private array $queryParamOptionMap = [];

    public function __construct(
        protected CommandConfiguration $config,
        protected string $method,
        protected string $path,
        protected array $operationData,
        string $commandName,
    ) {
        $this->buildSignature($commandName);

        $this->description = $this->operationData['summary'] ?? "Execute {$this->method} {$this->path}";

        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->validatePathParameters()) {
            return self::FAILURE;
        }

        $url = $this->buildRequestUrl();

        $input = $this->parseRequestInput();
        if ($input === null) {
            return self::FAILURE;
        }

        $response = $this->sendRequest($url, $input['fields'], $input['files'], $input['jsonData']);
        if ($response === null) {
            return self::FAILURE;
        }

        return $this->processResponse($response);
    }

    protected function validatePathParameters(): bool
    {
        foreach ($this->pathParamOptionMap as $paramName => $optionName) {
            if (! $this->option($optionName)) {
                $this->error("The --{$optionName} option is required for path parameter '{$paramName}'.");

                return false;
            }
        }

        return true;
    }

    protected function buildRequestUrl(): string
    {
        $baseUrl = $this->resolveBaseUrl();
        $urlPath = $this->path;

        foreach ($this->pathParamOptionMap as $paramName => $optionName) {
            $urlPath = str_replace("{{$paramName}}", $this->option($optionName), $urlPath);
        }

        $url = rtrim($baseUrl, '/').$urlPath;

        $queryParams = collect($this->queryParamOptionMap)
            ->mapWithKeys(fn (string $optionName, string $paramName) => [$paramName => $this->option($optionName)])
            ->filter(fn ($value) => $value !== null)
            ->all();

        if (! empty($queryParams)) {
            $encodedParams = [];
            $this->flattenParams($queryParams, $encodedParams);
            $url .= '?'.implode('&', $encodedParams);
        }

        return $url;
    }

    /**
     * @return ?array{
     *     fields: array<string, string>,
     *     files: array<string, string>,
     *     jsonData: ?array<string, mixed>,
     * }
     */
    protected function parseRequestInput(): ?array
    {
        $parseResult = $this->parseFields($this->option('field'));
        $fields = $parseResult['fields'];
        $files = $parseResult['files'];

        foreach ($files as $fieldName => $filePath) {
            if (! file_exists($filePath)) {
                $this->error("File not found: {$filePath}");

                return null;
            }

            if (! is_readable($filePath)) {
                $this->error("File is not readable: {$filePath}");

                return null;
            }
        }

        $jsonData = null;
        $jsonInput = $this->option('input');
        if ($jsonInput) {
            try {
                $jsonData = json_decode($jsonInput, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $this->error("Invalid JSON input: {$exception->getMessage()}");

                return null;
            }
        }

        if (! empty($fields) || ! empty($files)) {
            if ($jsonData !== null) {
                $this->error('Cannot use both --field and --input options. Use --input for JSON data or --field for form fields, not both.');

                return null;
            }
        }

        return [
            'fields' => $fields,
            'files' => $files,
            'jsonData' => $jsonData,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $files
     * @param  ?array<string, mixed>  $jsonData
     */
    protected function sendRequest(string $url, array $fields, array $files, ?array $jsonData): ?Response
    {
        $redirects = $this->config->shouldFollowRedirects();
        $http = ! empty($files)
            ? Http::withOptions(['allow_redirects' => $redirects])
            : Http::asJson()->withOptions(['allow_redirects' => $redirects]);

        $acceptTypes = $this->getAcceptContentTypes();
        if ($acceptTypes !== null) {
            $http = $http->withHeaders(['Accept' => $acceptTypes]);
        }

        $http = $this->applyAuthentication($http);
        $method = strtoupper($this->method);

        if ($this->output->isDebug()) {
            $this->outputDebugRequest($method, $url, $acceptTypes, $jsonData, $fields, $files);
        }

        try {
            if ($jsonData !== null) {
                return $http->send($method, $url, ['json' => $jsonData]);
            }

            if (! empty($files)) {
                return $this->sendMultipartRequest($http, $method, $url, $fields, $files);
            }

            if (! empty($fields)) {
                return $this->sendFieldsRequest($http, $method, $url, $fields);
            }

            return $http->send($method, $url);
        } catch (ConnectionException $exception) {
            $this->error("Network error: Could not connect to {$url}");
            $this->line($exception->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $files
     */
    protected function sendMultipartRequest(PendingRequest $http, string $method, string $url, array $fields, array $files): Response
    {
        $multipart = [];

        foreach ($files as $fieldName => $filePath) {
            $multipart[] = [
                'name' => $fieldName,
                'contents' => file_get_contents($filePath),
                'filename' => basename($filePath),
            ];
        }

        foreach ($fields as $fieldName => $value) {
            $multipart[] = [
                'name' => $fieldName,
                'contents' => $value,
            ];
        }

        return $http->send($method, $url, ['multipart' => $multipart]);
    }

    /**
     * @param  array<string, string>  $fields
     */
    protected function sendFieldsRequest(PendingRequest $http, string $method, string $url, array $fields): Response
    {
        $contentTypes = $this->operationData['requestBody']['content'] ?? [];

        if (empty($contentTypes) || isset($contentTypes['application/json'])) {
            return $http->send($method, $url, ['json' => $fields]);
        }

        return $http->send($method, $url, ['form_params' => $fields]);
    }

    protected function processResponse(Response $response): int
    {
        if ($response->status() >= 400) {
            $onError = $this->config->getOnErrorCallable();

            if ($onError) {
                if ($onError($response, $this)) {
                    return self::FAILURE;
                }
            }

            $this->error("HTTP {$response->status()} Error");
            $this->line('');
            $this->outputResponse($response);

            return self::FAILURE;
        }

        $this->outputResponse($response);

        return self::SUCCESS;
    }

    private function buildSignature(string $commandName): void
    {
        $namespace = $this->config->getNamespace();
        $parts = [$namespace !== '' ? "{$namespace}:{$commandName}" : $commandName];

        $parameters = collect($this->operationData['parameters'] ?? []);

        $parameters
            ->filter(fn (array $param) => ($param['in'] ?? '') === 'path')
            ->each(function (array $param) use (&$parts) {
                $paramName = $param['name'] ?? '';
                $optionName = CommandNameGenerator::parameterToOptionName($paramName);
                $this->pathParamOptionMap[$paramName] = $optionName;
                $description = $param['description'] ?? "Path parameter: {$paramName}";
                if (isset($param['schema']['enum'])) {
                    $description .= ' ['.implode(', ', $param['schema']['enum']).']';
                }
                $parts[] = "{--{$optionName}= : {$description}}";
            });

        $parameters
            ->filter(fn (array $param) => ($param['in'] ?? '') === 'query')
            ->each(function (array $param) use (&$parts) {
                $paramName = $param['name'] ?? '';
                $optionName = CommandNameGenerator::queryParamToOptionName($paramName);
                $this->queryParamOptionMap[$paramName] = $optionName;
                $description = $param['description'] ?? "Query parameter: {$paramName}";
                if (isset($param['schema']['enum'])) {
                    $description .= ' ['.implode(', ', $param['schema']['enum']).']';
                }
                $parts[] = "{--{$optionName}= : {$description}}";
            });

        $parts = array_merge($parts, [
            '{--field=* : Form field in key=value format (can be used multiple times)}',
            '{--input= : Raw JSON input}',
            '{--json : Output raw JSON instead of human-readable format}',
            '{--yaml : Output as YAML}',
            '{--minify : Minify JSON output (implies --json)}',
            '{--H|headers : Include response headers in output}',
            '{--output-html : Show the full response body when content-type is text/html}',
        ]);

        $this->signature = implode("\n            ", $parts);
    }

    protected function resolveBaseUrl(): string
    {
        $configuredBaseUrl = $this->config->getBaseUrl();

        if ($configuredBaseUrl !== null) {
            return $configuredBaseUrl;
        }

        $parser = new OpenApiParser(SpecResolver::resolve($this->config->getSpecPath(), $this->config));
        $specBaseUrl = $parser->getServerUrl();

        if ($specBaseUrl !== null) {
            return $specBaseUrl;
        }

        throw new RuntimeException(
            'No base URL available. Either configure one using ->baseUrl() or ensure your OpenAPI spec has a servers array.'
        );
    }

    protected function applyAuthentication(PendingRequest $http): PendingRequest
    {
        if ($this->config->getBearerToken() !== null) {
            return $http->withToken($this->config->getBearerToken());
        }

        if ($this->config->getApiKeyHeader() !== null) {
            if ($this->config->getApiKeyValue() !== null) {
                return $http->withHeader($this->config->getApiKeyHeader(), $this->config->getApiKeyValue());
            }
        }

        if ($this->config->getBasicUsername() !== null) {
            if ($this->config->getBasicPassword() !== null) {
                return $http->withBasicAuth($this->config->getBasicUsername(), $this->config->getBasicPassword());
            }
        }

        if ($this->config->getAuthCallable() !== null) {
            $callable = $this->config->getAuthCallable();

            return $http->withToken($callable());
        }

        return $http;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $files
     */
    private function outputDebugRequest(
        string $method,
        string $url,
        ?string $acceptTypes,
        ?array $jsonData,
        array $fields,
        array $files,
    ): void {
        $this->newLine();
        $this->comment('  Request');
        $this->comment('  -------');
        $this->line("  {$method} {$url}");

        $headers = $this->collectDebugHeaders($acceptTypes, $files);

        if ($headers !== []) {
            $this->newLine();
            $this->comment('  Request Headers');
            $this->comment('  ---------------');
            collect($headers)->each(fn (string $value, string $name) => $this->line("  {$name}: {$value}"));
        }

        $this->outputDebugRequestBody($jsonData, $fields, $files);

        $this->newLine();
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $files
     */
    private function outputDebugRequestBody(?array $jsonData, array $fields, array $files): void
    {
        if ($jsonData !== null) {
            $this->outputDebugBodySection(
                json_encode($jsonData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            return;
        }

        if (! empty($files)) {
            $this->newLine();
            $this->comment('  Request Body');
            $this->comment('  ------------');
            collect($files)->each(function (string $filePath, string $fieldName) {
                $size = file_exists($filePath) ? filesize($filePath) : 0;
                $filename = basename($filePath);
                $this->line("  {$fieldName}: {$filename} ({$size} bytes)");
            });
            collect($fields)->each(fn (string $value, string $fieldName) => $this->line("  {$fieldName}: {$value}"));

            return;
        }

        if (empty($fields)) {
            return;
        }

        $contentTypes = $this->operationData['requestBody']['content'] ?? [];
        $isFormEncoded = ! empty($contentTypes) && ! isset($contentTypes['application/json']);

        if ($isFormEncoded) {
            $this->newLine();
            $this->comment('  Request Body');
            $this->comment('  ------------');
            collect($fields)->each(fn (string $value, string $fieldName) => $this->line("  {$fieldName}: {$value}"));

            return;
        }

        $this->outputDebugBodySection(
            json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function outputDebugBodySection(string $content): void
    {
        $this->newLine();
        $this->comment('  Request Body');
        $this->comment('  ------------');
        $this->line("  {$content}");
    }

    /**
     * @param  array<string, string>  $files
     * @return array<string, string>
     */
    private function collectDebugHeaders(?string $acceptTypes, array $files): array
    {
        $headers = [];

        if ($acceptTypes !== null) {
            $headers['Accept'] = $acceptTypes;
        }

        $headers['Content-Type'] = ! empty($files) ? 'multipart/form-data' : 'application/json';

        if ($this->config->getBearerToken() !== null) {
            $headers['Authorization'] = "Bearer {$this->config->getBearerToken()}";
        } elseif ($this->config->getApiKeyHeader() !== null) {
            if ($this->config->getApiKeyValue() !== null) {
                $headers[$this->config->getApiKeyHeader()] = $this->config->getApiKeyValue();
            }
        } elseif ($this->config->getBasicUsername() !== null) {
            if ($this->config->getBasicPassword() !== null) {
                $headers['Authorization'] = 'Basic '.base64_encode("{$this->config->getBasicUsername()}:{$this->config->getBasicPassword()}");
            }
        } elseif ($this->config->getAuthCallable() !== null) {
            $headers['Authorization'] = 'Bearer (dynamic)';
        }

        return $headers;
    }

    private function getAcceptContentTypes(): ?string
    {
        $contentTypes = collect($this->operationData['responses'] ?? [])
            ->flatMap(fn (array $response) => array_keys($response['content'] ?? []))
            ->unique()
            ->implode(', ');

        return $contentTypes !== '' ? $contentTypes : null;
    }

    /**
     * @param  array<int, string>  $fieldOptions
     * @return array{
     *     fields: array<string, string>,
     *     files: array<string, string>,
     * }
     */
    protected function parseFields(array $fieldOptions): array
    {
        $parsed = collect($fieldOptions)
            ->filter(fn (string $field) => str_contains($field, '='))
            ->mapWithKeys(function (string $field) {
                [$key, $value] = explode('=', $field, 2);

                return [$key => $value];
            });

        $files = $parsed
            ->filter(fn (string $value) => str_starts_with($value, '@'))
            ->map(fn (string $value) => substr($value, 1))
            ->all();

        $fields = $parsed
            ->reject(fn (string $value) => str_starts_with($value, '@'))
            ->all();

        return [
            'fields' => $fields,
            'files' => $files,
        ];
    }

    protected function flattenParams(array $params, array &$result, string $prefix = ''): void
    {
        foreach ($params as $key => $value) {
            $fullKey = $prefix === '' ? $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $this->flattenParams($value, $result, $fullKey);

                continue;
            }

            $result[] = urlencode($fullKey).'='.urlencode($value);
        }
    }

    protected function outputResponse(Response $response): void
    {
        $this->outputResponseHeaders($response);

        if ($response->status() === 204) {
            $description = $this->operationData['responses']['204']['description'] ?? null;
            $this->line($description ? "{$description} (204)" : 'No content (204)');

            return;
        }

        $body = $response->body();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            $this->outputNonJsonResponse($response, $body);

            return;
        }

        $this->outputJsonResponse($decoded);
    }

    protected function outputResponseHeaders(Response $response): void
    {
        if (! $this->option('headers')) {
            return;
        }

        $this->line("HTTP/1.1 {$response->status()} {$response->reason()}");

        collect($response->headers())->each(function (array $values, string $name) {
            collect($values)->each(fn (string $value) => $this->line("{$name}: {$value}"));
        });

        $this->line('');
    }

    protected function outputJsonResponse(array $decoded): void
    {
        $highlighter = new OutputHighlighter(enabled: $this->output->isDecorated());
        $useYaml = $this->option('yaml') || $this->config->shouldOutputYaml();
        $useJson = $this->option('json') || $this->option('minify') || $this->config->shouldOutputJson();

        if ($useYaml) {
            if (! $this->option('json')) {
                if (! $this->option('minify')) {
                    $this->outputLines($highlighter->highlightYaml(rtrim(Yaml::dump($decoded, 10, 2))));

                    return;
                }
            }
        }

        if ($useJson || $useYaml) {
            $formatted = $this->option('minify')
                ? json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->line($highlighter->highlightJson($formatted));

            return;
        }

        $formatter = new HumanReadableFormatter((new Terminal)->getWidth());
        $this->outputLines($highlighter->highlightHumanReadable($formatter->format($decoded)));
    }

    protected function outputNonJsonResponse(Response $response, string $body): void
    {
        $contentType = $response->header('Content-Type') ?: 'unknown';
        $contentLength = $response->header('Content-Length') ?: strlen($body);
        $this->line("Response is not JSON (content-type: {$contentType}, status: {$response->status()}, content-length: {$contentLength})");
        $this->line('');

        if (str_contains($contentType, 'text/html')) {
            if (! $this->option('output-html')) {
                if (! $this->config->shouldShowHtmlBody()) {
                    $this->line('Use --output-html to see the full response body.');

                    return;
                }
            }
        }

        $this->line($body);
    }

    protected function outputLines(string $content): void
    {
        collect(explode("\n", $content))->each(fn (string $line) => $this->line($line));
    }
}
