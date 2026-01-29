<?php

namespace Spatie\OpenApiCli;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\OpenApiCli\Commands\OpenApiCommand;

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
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        //
    }

    public function packageBooted(): void
    {
        // Register all OpenAPI commands after all service providers have booted
        foreach (OpenApiCli::getRegistrations() as $config) {
            $this->app->singleton($config->getSignature(), function () use ($config) {
                return new OpenApiCommand($config);
            });

            $this->commands($config->getSignature());
        }
    }
}
