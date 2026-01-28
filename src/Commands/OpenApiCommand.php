<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
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
}
