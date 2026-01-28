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

        // Parse form fields if provided
        $fields = $this->parseFields($this->option('field'));

        // Determine HTTP method (auto-detect POST when --field is used, default to GET)
        $method = $this->option('method');
        if (! $method && ! empty($fields)) {
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
        $http = Http::asJson();
        $http = $this->applyAuthentication($http);

        // Determine how to send the request based on fields
        if (! empty($fields)) {
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
            // No fields, just send the request
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
     * Parse field options into key-value array.
     *
     * @param  array<string>  $fieldOptions
     * @return array<string, string>
     */
    protected function parseFields(array $fieldOptions): array
    {
        $fields = [];

        foreach ($fieldOptions as $field) {
            // Parse format: key=value
            if (str_contains($field, '=')) {
                [$key, $value] = explode('=', $field, 2);
                $fields[$key] = $value;
            }
        }

        return $fields;
    }
}
