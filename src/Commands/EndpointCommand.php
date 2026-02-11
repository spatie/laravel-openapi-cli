<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\CommandNameGenerator;
use Spatie\OpenApiCli\HumanReadableFormatter;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\OutputHighlighter;
use Spatie\OpenApiCli\SpecResolver;

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
        $this->description = $this->operationData['summary'] ?? 'Execute '.$this->method.' '.$this->path;

        parent::__construct();
    }

    public function handle(): int
    {
        // Validate required path parameter options
        foreach ($this->pathParamOptionMap as $paramName => $optionName) {
            if (! $this->option($optionName)) {
                $this->error("The --{$optionName} option is required for path parameter '{$paramName}'.");

                return self::FAILURE;
            }
        }

        // Build the URL with path parameter substitution
        $baseUrl = $this->resolveBaseUrl();
        $urlPath = $this->path;

        foreach ($this->pathParamOptionMap as $paramName => $optionName) {
            $urlPath = str_replace('{'.$paramName.'}', $this->option($optionName), $urlPath);
        }

        $url = rtrim($baseUrl, '/').$urlPath;

        // Append query parameters from individual options
        $queryParams = [];
        foreach ($this->queryParamOptionMap as $paramName => $optionName) {
            $value = $this->option($optionName);
            if ($value !== null) {
                $queryParams[$paramName] = $value;
            }
        }

        if (! empty($queryParams)) {
            $encodedParams = [];
            $this->flattenParams($queryParams, $encodedParams);
            $url .= '?'.implode('&', $encodedParams);
        }

        // Parse form fields and file uploads
        $parseResult = $this->parseFields($this->option('field'));
        $fields = $parseResult['fields'];
        $files = $parseResult['files'];

        // Validate file paths
        foreach ($files as $fieldName => $filePath) {
            if (! file_exists($filePath)) {
                $this->error("File not found: {$filePath}");

                return self::FAILURE;
            }

            if (! is_readable($filePath)) {
                $this->error("File is not readable: {$filePath}");

                return self::FAILURE;
            }
        }

        // Parse JSON input
        $jsonInput = $this->option('input');
        $jsonData = null;
        if ($jsonInput) {
            try {
                $jsonData = json_decode($jsonInput, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error('Invalid JSON input: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        // Conflict check: --field and --input
        if ((! empty($fields) || ! empty($files)) && $jsonData !== null) {
            $this->error('Cannot use both --field and --input options. Use --input for JSON data or --field for form fields, not both.');

            return self::FAILURE;
        }

        // Execute HTTP request
        $http = ! empty($files) ? Http::withOptions([]) : Http::asJson();
        $http = $this->applyAuthentication($http);
        $method = strtoupper($this->method);

        try {
            if ($jsonData !== null) {
                $response = $http->send($method, $url, [
                    'json' => $jsonData,
                ]);
            } elseif (! empty($files)) {
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

                $response = $http->send($method, $url, [
                    'multipart' => $multipart,
                ]);
            } elseif (! empty($fields)) {
                $contentTypes = $this->operationData['requestBody']['content'] ?? [];

                if (empty($contentTypes) || isset($contentTypes['application/json'])) {
                    $response = $http->send($method, $url, [
                        'json' => $fields,
                    ]);
                } else {
                    $response = $http->send($method, $url, [
                        'form_params' => $fields,
                    ]);
                }
            } else {
                $response = $http->send($method, $url);
            }
        } catch (ConnectionException $e) {
            $this->error("Network error: Could not connect to {$url}");
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        // Handle HTTP errors
        $statusCode = $response->status();

        if ($statusCode >= 400) {
            $this->error("HTTP {$statusCode} Error");
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

        // Add path parameters as required options
        $parameters = $this->operationData['parameters'] ?? [];
        foreach ($parameters as $param) {
            if (($param['in'] ?? '') === 'path') {
                $paramName = $param['name'] ?? '';
                $optionName = CommandNameGenerator::parameterToOptionName($paramName);
                $this->pathParamOptionMap[$paramName] = $optionName;
                $description = $param['description'] ?? "Path parameter: {$paramName}";
                $parts[] = "{--{$optionName}= : {$description}}";
            }
        }

        // Add query parameters as optional options
        foreach ($parameters as $param) {
            if (($param['in'] ?? '') === 'query') {
                $paramName = $param['name'] ?? '';
                $optionName = CommandNameGenerator::queryParamToOptionName($paramName);
                $this->queryParamOptionMap[$paramName] = $optionName;
                $description = $param['description'] ?? "Query parameter: {$paramName}";
                $parts[] = "{--{$optionName}= : {$description}}";
            }
        }

        // Universal options
        $parts[] = '{--field=* : Form field in key=value format (can be used multiple times)}';
        $parts[] = '{--input= : Raw JSON input}';
        $parts[] = '{--minify : Minify JSON output}';
        $parts[] = '{--H|headers : Include response headers in output}';
        $parts[] = '{--human : Display response in human-readable format}';

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

        throw new \RuntimeException(
            'No base URL available. Either configure one using ->baseUrl() or ensure your OpenAPI spec has a servers array.'
        );
    }

    protected function applyAuthentication(PendingRequest $http): PendingRequest
    {
        if ($this->config->getBearerToken() !== null) {
            return $http->withToken($this->config->getBearerToken());
        }

        if ($this->config->getApiKeyHeader() !== null && $this->config->getApiKeyValue() !== null) {
            return $http->withHeader($this->config->getApiKeyHeader(), $this->config->getApiKeyValue());
        }

        if ($this->config->getBasicUsername() !== null && $this->config->getBasicPassword() !== null) {
            return $http->withBasicAuth($this->config->getBasicUsername(), $this->config->getBasicPassword());
        }

        if ($this->config->getAuthCallable() !== null) {
            $callable = $this->config->getAuthCallable();
            $token = $callable();

            return $http->withToken($token);
        }

        return $http;
    }

    /**
     * @param  array<string>  $fieldOptions
     * @return array{fields: array<string, string>, files: array<string, string>}
     */
    protected function parseFields(array $fieldOptions): array
    {
        $fields = [];
        $files = [];

        foreach ($fieldOptions as $field) {
            if (str_contains($field, '=')) {
                [$key, $value] = explode('=', $field, 2);

                if (str_starts_with($value, '@')) {
                    $files[$key] = substr($value, 1);
                } else {
                    $fields[$key] = $value;
                }
            }
        }

        return [
            'fields' => $fields,
            'files' => $files,
        ];
    }

    protected function flattenParams(array $params, array &$result, string $prefix = ''): void
    {
        foreach ($params as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $this->flattenParams($value, $result, $fullKey);
            } else {
                $result[] = urlencode($fullKey).'='.urlencode($value);
            }
        }
    }

    protected function outputResponse(Response $response): void
    {
        $highlighter = new OutputHighlighter(
            enabled: $this->output->isDecorated(),
        );

        if ($this->option('headers')) {
            $statusCode = $response->status();
            $reasonPhrase = $response->reason();
            $this->line("HTTP/1.1 {$statusCode} {$reasonPhrase}");

            foreach ($response->headers() as $name => $values) {
                foreach ($values as $value) {
                    $this->line("{$name}: {$value}");
                }
            }

            $this->line('');
        }

        if ($response->status() === 204) {
            $this->line('No content (204)');

            return;
        }

        $body = $response->body();
        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            if ($this->option('human')) {
                $formatter = new HumanReadableFormatter;
                $formatted = $highlighter->highlightHumanReadable($formatter->format($decoded));

                foreach (explode("\n", $formatted) as $line) {
                    $this->line($line);
                }

                return;
            }

            if ($this->option('minify')) {
                $formatted = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $this->line($highlighter->highlightJson($formatted));
        } else {
            $contentType = $response->header('Content-Type') ?: 'unknown';
            $this->line("Response is not JSON (content-type: {$contentType})");
            $this->line('');
            $this->line($body);
        }
    }
}
