<?php

namespace Spatie\OpenApiCli;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\OpenApiCli\Commands\OpenApiCliCommand;

class OpenApiCliServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-openapi-cli')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_openapi_cli_table')
            ->hasCommand(OpenApiCliCommand::class);
    }
}
