<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\PathMatcher;

class OpenApiCommand extends Command
{
    protected $signature;

    protected $description = 'Execute OpenAPI requests';

    public function __construct(
        protected CommandConfiguration $config
    ) {
        $this->signature = $config->getSignature().'
            {endpoint? : The API endpoint path to call}
            {--method= : HTTP method (GET, POST, PUT, PATCH, DELETE)}
            {--field=* : Form field in key=value format (can be used multiple times)}
            {--query= : Query string parameters}
            {--input= : Raw JSON input}
            {--list : List all available endpoints}
            {--schema : Output the full OpenAPI schema as JSON}
            {--help-endpoint : Show detailed help for a specific endpoint}
            {--minify : Minify JSON output}
            {--include : Include response headers in output}';

        parent::__construct();
    }

    public function handle(): int
    {
        $endpoint = $this->argument('endpoint');

        // Get endpoint path
        if (! $endpoint) {
            $this->error('Please provide an endpoint path.');

            return self::FAILURE;
        }

        // Parse the OpenAPI spec
        $parser = new OpenApiParser($this->config->getSpecPath());
        $paths = $parser->getPaths();

        // Match the endpoint path
        $matcher = new PathMatcher;
        $matches = $matcher->matchPath($endpoint, $paths);

        // Check if path was found
        if (empty($matches)) {
            $this->error('Endpoint not found in OpenAPI spec.');

            return self::FAILURE;
        }

        // Check for ambiguous matches
        $ambiguityCheck = $matcher->checkAmbiguity($matches, $this->config->getSignature());

        if ($ambiguityCheck['isAmbiguous']) {
            $this->error($ambiguityCheck['message']);

            return self::FAILURE;
        }

        // Use the first (and only) match
        $match = $matches[0];

        // Parse form fields and file uploads if provided
        $parseResult = $this->parseFields($this->option('field'));
        $fields = $parseResult['fields'];
        $files = $parseResult['files'];

        // Validate file paths exist and are readable
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

        // Parse JSON input if provided
        $jsonInput = $this->option('input');
        $jsonData = null;
        if ($jsonInput) {
            // Validate JSON
            try {
                $jsonData = json_decode($jsonInput, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error('Invalid JSON input: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        // Handle conflict: both --field and --input provided
        if ((! empty($fields) || ! empty($files)) && $jsonData !== null) {
            $this->error('Cannot use both --field and --input options. Use --input for JSON data or --field for form fields, not both.');

            return self::FAILURE;
        }

        // Determine HTTP method (auto-detect POST when --field or --input is used, default to GET)
        $method = $this->option('method');
        if (! $method && (! empty($fields) || ! empty($files) || $jsonData !== null)) {
            $method = 'POST';
        }
        $method = strtoupper($method ?? 'GET');

        // Validate method is allowed for this path
        if (! in_array($method, $match['methods'])) {
            $this->error("Method {$method} is not allowed for this endpoint.");
            $this->error('Available methods: '.implode(', ', $match['methods']));

            return self::FAILURE;
        }

        // Build the full URL
        $baseUrl = $this->resolveBaseUrl();
        $path = $match['path'];

        // Substitute path parameters
        foreach ($match['parameters'] as $name => $value) {
            $path = str_replace('{'.$name.'}', $value, $path);
        }

        $url = rtrim($baseUrl, '/').$path;

        // Append query parameters if provided
        if ($queryString = $this->option('query')) {
            $url = $this->appendQueryParameters($url, $queryString);
        }

        // Execute the HTTP request
        // Note: Don't use Http::asJson() for multipart requests as it sets conflicting headers
        $http = ! empty($files) ? Http::withOptions([]) : Http::asJson();
        $http = $this->applyAuthentication($http);

        // Determine how to send the request based on data type
        if ($jsonData !== null) {
            // Send as JSON (always use application/json for --input)
            $response = $http->send($method, $url, [
                'json' => $jsonData,
            ]);
        } elseif (! empty($files)) {
            // File uploads require multipart/form-data
            // Build multipart array with files and regular fields
            $multipart = [];

            // Add file attachments
            foreach ($files as $fieldName => $filePath) {
                $multipart[] = [
                    'name' => $fieldName,
                    'contents' => file_get_contents($filePath),
                    'filename' => basename($filePath),
                ];
            }

            // Add regular fields if present
            foreach ($fields as $fieldName => $value) {
                $multipart[] = [
                    'name' => $fieldName,
                    'contents' => $value,
                ];
            }

            // Send with multipart option
            $response = $http->send($method, $url, [
                'multipart' => $multipart,
            ]);
        } elseif (! empty($fields)) {
            // Get content types from spec to determine format
            $contentTypes = $parser->getRequestBodyContentTypes($match['path'], strtolower($method));

            // Check if spec expects application/json or if no content type is specified (default to JSON)
            if (empty($contentTypes) || in_array('application/json', $contentTypes)) {
                // Send as JSON
                $response = $http->send($method, $url, [
                    'json' => $fields,
                ]);
            } else {
                // Send as form-data (for multipart/form-data or application/x-www-form-urlencoded)
                $response = $http->send($method, $url, [
                    'form_params' => $fields,
                ]);
            }
        } else {
            // No data, just send the request
            $response = $http->send($method, $url);
        }

        // Output the response body
        $this->line($response->body());

        return self::SUCCESS;
    }

    /**
     * Resolve the base URL from configuration or spec.
     * Throws an exception if no base URL is available.
     */
    protected function resolveBaseUrl(): string
    {
        // Check if base URL is overridden in configuration
        $configuredBaseUrl = $this->config->getBaseUrl();

        if ($configuredBaseUrl !== null) {
            return $configuredBaseUrl;
        }

        // Fall back to spec's servers[0].url
        $parser = new OpenApiParser($this->config->getSpecPath());
        $specBaseUrl = $parser->getServerUrl();

        if ($specBaseUrl !== null) {
            return $specBaseUrl;
        }

        // No base URL available
        throw new \RuntimeException(
            'No base URL available. Either configure one using ->baseUrl() or ensure your OpenAPI spec has a servers array.'
        );
    }

    /**
     * Append query parameters to the URL with URL encoding.
     */
    protected function appendQueryParameters(string $url, string $queryString): string
    {
        // Parse the query string into key-value pairs
        parse_str($queryString, $params);

        // Build the query string with URL-encoded values
        $encodedParams = [];
        foreach ($params as $key => $value) {
            $encodedParams[] = urlencode($key).'='.urlencode($value);
        }

        // Append to URL with appropriate separator
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.implode('&', $encodedParams);
    }

    /**
     * Apply configured authentication to the HTTP client.
     */
    protected function applyAuthentication(PendingRequest $http): PendingRequest
    {
        // Apply bearer token authentication
        if ($this->config->getBearerToken() !== null) {
            return $http->withToken($this->config->getBearerToken());
        }

        // Apply API key authentication
        if ($this->config->getApiKeyHeader() !== null && $this->config->getApiKeyValue() !== null) {
            return $http->withHeader($this->config->getApiKeyHeader(), $this->config->getApiKeyValue());
        }

        // Apply basic authentication
        if ($this->config->getBasicUsername() !== null && $this->config->getBasicPassword() !== null) {
            return $http->withBasicAuth($this->config->getBasicUsername(), $this->config->getBasicPassword());
        }

        // Apply callable authentication
        if ($this->config->getAuthCallable() !== null) {
            $callable = $this->config->getAuthCallable();
            $token = $callable();

            return $http->withToken($token);
        }

        // No authentication configured
        return $http;
    }

    /**
     * Parse field options into regular fields and file uploads.
     * Detects @ prefix for file uploads.
     *
     * @param  array<string>  $fieldOptions
     * @return array{fields: array<string, string>, files: array<string, string>}
     */
    protected function parseFields(array $fieldOptions): array
    {
        $fields = [];
        $files = [];

        foreach ($fieldOptions as $field) {
            // Parse format: key=value or key=@/path/to/file
            if (str_contains($field, '=')) {
                [$key, $value] = explode('=', $field, 2);

                // Check if value starts with @ (file upload)
                if (str_starts_with($value, '@')) {
                    // Remove @ prefix and store as file path
                    $files[$key] = substr($value, 1);
                } else {
                    // Regular field value
                    $fields[$key] = $value;
                }
            }
        }

        return [
            'fields' => $fields,
            'files' => $files,
        ];
    }
}
