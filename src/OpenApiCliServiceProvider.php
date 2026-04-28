<?php

namespace Spatie\OpenApiCli;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\OpenApiCli\Commands\EndpointCommand;
use Spatie\OpenApiCli\Commands\ListCommand;

class OpenApiCliServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-openapi-cli');
    }

    public function packageBooted(): void
    {
        collect(OpenApiCli::getRegistrations())
            ->each(fn (CommandConfiguration $config) => $this->registerEndpointCommands($config));
    }

    protected function registerEndpointCommands(CommandConfiguration $config): void
    {
        $parser = new OpenApiParser(SpecResolver::resolve($config->getSpecPath(), $config));
        $spec = $parser->getSpec();
        $resolver = new EndpointResolver(new RefResolver($spec));

        $commandBindings = $resolver->resolve($config, $parser->getPathsWithMethods(), $spec)
            ->map(function (array $endpoint) use ($config) {
                $namespace = $config->getNamespace();
                $bindingKey = $namespace !== ''
                    ? "openapi.{$namespace}.{$endpoint['commandSuffix']}"
                    : "openapi.{$endpoint['commandSuffix']}";

                $this->app->singleton($bindingKey, fn () => new EndpointCommand(
                    $config,
                    $endpoint['method'],
                    $endpoint['path'],
                    $endpoint['operationData'],
                    $endpoint['commandSuffix'],
                ));

                return $bindingKey;
            });

        if ($config->hasNamespace()) {
            $listBindingKey = "openapi.{$config->getNamespace()}.list";
            $this->app->singleton($listBindingKey, fn () => new ListCommand($config));
            $commandBindings->push($listBindingKey);
        }

        $this->commands($commandBindings->all());
    }
}
