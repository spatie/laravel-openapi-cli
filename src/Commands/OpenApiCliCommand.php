<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;

class OpenApiCliCommand extends Command
{
    public $signature = 'laravel-openapi-cli';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
