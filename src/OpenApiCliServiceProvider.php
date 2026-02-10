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
        foreach (OpenApiCli::getRegistrations() as $config) {
            $this->registerEndpointCommands($config);
        }
    }

    protected function registerEndpointCommands(CommandConfiguration $config): void
    {
        $parser = new OpenApiParser(SpecResolver::resolve($config->getSpecPath(), $config));
        $spec = $parser->getSpec();
        $resolver = new RefResolver($spec);
        $pathsWithMethods = $parser->getPathsWithMethods();
        $namespace = $config->getNamespace();

        $endpoints = $this->resolveEndpoints($config, $pathsWithMethods, $spec, $resolver);

        $commandBindings = [];

        foreach ($endpoints as $endpoint) {
            $commandSuffix = $endpoint['commandSuffix'];
            $bindingKey = $namespace !== '' ? "openapi.{$namespace}.{$commandSuffix}" : "openapi.{$commandSuffix}";

            $this->app->singleton($bindingKey, function () use ($config, $endpoint, $commandSuffix) {
                return new EndpointCommand($config, $endpoint['method'], $endpoint['path'], $endpoint['operationData'], $commandSuffix);
            });

            $commandBindings[] = $bindingKey;
        }

        // Register list command only when a namespace is set
        if ($config->hasNamespace()) {
            $listBindingKey = "openapi.{$namespace}.list";
            $this->app->singleton($listBindingKey, function () use ($config) {
                return new ListCommand($config);
            });
            $commandBindings[] = $listBindingKey;
        }

        $this->commands($commandBindings);
    }

    /**
     * Resolve all endpoints with their command suffixes, handling naming collisions.
     *
     * @return array<int, array{method: string, path: string, operationData: array, commandSuffix: string}>
     */
    protected function resolveEndpoints(CommandConfiguration $config, array $pathsWithMethods, array $spec, RefResolver $resolver): array
    {
        $endpoints = [];

        foreach ($pathsWithMethods as $path => $methods) {
            foreach ($methods as $method) {
                $operationData = $spec['paths'][$path][$method] ?? [];
                $operationData = $resolver->resolve($operationData);

                if ($config->shouldUseOperationIds()) {
                    $operationId = $operationData['operationId'] ?? null;
                    $commandSuffix = $operationId
                        ? CommandNameGenerator::fromOperationId($operationId)
                        : CommandNameGenerator::fromPath($method, $path);
                } else {
                    $commandSuffix = CommandNameGenerator::fromPath($method, $path);
                }

                $endpoints[] = [
                    'method' => $method,
                    'path' => $path,
                    'operationData' => $operationData,
                    'commandSuffix' => $commandSuffix,
                ];
            }
        }

        // Detect and resolve naming collisions
        $suffixCounts = [];
        foreach ($endpoints as $endpoint) {
            $suffixCounts[$endpoint['commandSuffix']] = ($suffixCounts[$endpoint['commandSuffix']] ?? 0) + 1;
        }

        foreach ($endpoints as &$endpoint) {
            if ($suffixCounts[$endpoint['commandSuffix']] > 1) {
                // Collision detected - use disambiguated name
                $endpoint['commandSuffix'] = CommandNameGenerator::fromPathDisambiguated($endpoint['method'], $endpoint['path']);
            }
        }

        return $endpoints;
    }
}
