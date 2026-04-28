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

    /** @return Collection<int|string, array{method: string, path: string, operationData: array<string, mixed>, commandSuffix: string}> */
    protected function resolveEndpoints(CommandConfiguration $config, array $pathsWithMethods, array $spec, RefResolver $resolver): Collection
    {
        $endpoints = collect($pathsWithMethods)
            ->flatMap(function (array $methods, string $path) use ($config, $spec, $resolver) {
                return collect($methods)->map(function (string $method) use ($config, $path, $spec, $resolver) {
                    $operationData = $resolver->resolve($spec['paths'][$path][$method] ?? []);
                    $operationData = $this->mergePathLevelParameters($operationData, $spec['paths'][$path] ?? [], $resolver);

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

    /**
     * Merge path-level parameters into operation data.
     *
     * Per the OpenAPI spec, parameters can be defined at the path item level
     * and are inherited by all operations under that path, unless overridden
     * at the operation level (matched by name + in).
     *
     * @see https://spec.openapis.org/oas/v3.0.3#path-item-object
     *
     * @param  array<string, mixed>  $operationData
     * @param  array<string, mixed>  $pathItem
     * @return array<string, mixed>
     */
    protected function mergePathLevelParameters(array $operationData, array $pathItem, RefResolver $resolver): array
    {
        $pathLevelParams = $pathItem['parameters'] ?? [];

        if (empty($pathLevelParams)) {
            return $operationData;
        }

        /** @var array<int, array<string, mixed>> $resolvedPathParams */
        $resolvedPathParams = array_map(
            fn (mixed $param) => $resolver->resolve($param),
            $pathLevelParams,
        );

        /** @var array<int, array<string, mixed>> $operationParams */
        $operationParams = $operationData['parameters'] ?? [];

        // Build a set of "name:in" keys from existing operation-level params
        // so we can skip path-level params that are overridden
        $existingKeys = array_map(
            fn (array $p) => ($p['name'] ?? '').':'.($p['in'] ?? ''),
            $operationParams,
        );

        foreach ($resolvedPathParams as $pathParam) {
            $key = ($pathParam['name'] ?? '').':'.($pathParam['in'] ?? '');

            if (! in_array($key, $existingKeys)) {
                $operationParams[] = $pathParam;
            }
        }

        $operationData['parameters'] = $operationParams;

        return $operationData;
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
