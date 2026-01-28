<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\OpenApiParser;

class OpenApiCommand extends Command
{
    protected $signature;

    protected $description = 'Execute OpenAPI requests';

    public function __construct(
        protected CommandConfiguration $config
    ) {
        $this->signature = $config->getSignature();

        parent::__construct();
    }

    public function handle(): int
    {
        // Placeholder for actual command execution logic
        // This will be implemented in later user stories
        $this->info('OpenAPI command registered: '.$this->config->getSignature());
        $this->info('Spec path: '.$this->config->getSpecPath());

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
}
