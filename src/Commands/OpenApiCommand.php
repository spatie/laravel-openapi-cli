<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Spatie\OpenApiCli\CommandConfiguration;

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
}
