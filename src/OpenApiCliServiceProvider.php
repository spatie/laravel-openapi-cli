<?php

namespace Spatie\OpenApiCli;

use Illuminate\Support\Collection;
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
        $resolver = new RefResolver($spec);

        $commandBindings = $this->resolveEndpoints($config, $parser->getPathsWithMethods(), $spec, $resolver)
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

    /** @return Collection<int|string, array{method: string, path: string, operationData: mixed, commandSuffix: string}> */
    protected function resolveEndpoints(CommandConfiguration $config, array $pathsWithMethods, array $spec, RefResolver $resolver): Collection
    {
        $endpoints = collect($pathsWithMethods)
            ->flatMap(function (array $methods, string $path) use ($config, $spec, $resolver) {
                return collect($methods)->map(function (string $method) use ($config, $path, $spec, $resolver) {
                    $operationData = $resolver->resolve($spec['paths'][$path][$method] ?? []);

                    return [
                        'method' => $method,
                        'path' => $path,
                        'operationData' => $operationData,
                        'commandSuffix' => $this->resolveCommandSuffix($config, $method, $path, $operationData),
                    ];
                });
            });

        $suffixCounts = $endpoints->countBy('commandSuffix');

        return $endpoints->map(function (array $endpoint) use ($suffixCounts) {
            if ($suffixCounts->get($endpoint['commandSuffix']) > 1) {
                $endpoint['commandSuffix'] = CommandNameGenerator::fromPathDisambiguated($endpoint['method'], $endpoint['path']);
            }

            return $endpoint;
        });
    }

    protected function resolveCommandSuffix(CommandConfiguration $config, string $method, string $path, array $operationData): string
    {
        if (! $config->shouldUseOperationIds()) {
            return CommandNameGenerator::fromPath($method, $path);
        }

        $operationId = $operationData['operationId'] ?? null;

        return $operationId
            ? CommandNameGenerator::fromOperationId($operationId)
            : CommandNameGenerator::fromPath($method, $path);
    }
}
