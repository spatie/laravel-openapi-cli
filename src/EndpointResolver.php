<?php

namespace Spatie\OpenApiCli;

use Illuminate\Support\Collection;

class EndpointResolver
{
    public function __construct(
        protected RefResolver $refResolver,
    ) {}

    /**
     * @param  array<string, array<int, string>>  $pathsWithMethods
     * @param  array<string, mixed>  $spec
     * @return Collection<int, array{method: string, path: string, operationData: array<string, mixed>, commandSuffix: string}>
     */
    public function resolve(CommandConfiguration $config, array $pathsWithMethods, array $spec): Collection
    {
        $endpoints = collect($pathsWithMethods)
            ->flatMap(fn (array $methods, string $path) => collect($methods)->map(
                fn (string $method) => $this->buildEndpoint($config, $method, $path, $spec)
            ))
            ->values();

        $suffixCounts = $endpoints->countBy('commandSuffix');

        return $endpoints
            ->map(function (array $endpoint) use ($suffixCounts) {
                if ($suffixCounts->get($endpoint['commandSuffix']) > 1) {
                    $endpoint['commandSuffix'] = CommandNameGenerator::fromPathDisambiguated($endpoint['method'], $endpoint['path']);
                }

                return $endpoint;
            })
            // After disambiguation, so an exclusion by name matches the name the
            // command would actually have been registered under.
            ->reject(fn (array $endpoint) => $config->isExcluded(
                $endpoint['commandSuffix'],
                $endpoint['method'],
                $endpoint['path'],
                $endpoint['operationData'],
            ))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{method: string, path: string, operationData: array<string, mixed>, commandSuffix: string}
     */
    protected function buildEndpoint(CommandConfiguration $config, string $method, string $path, array $spec): array
    {
        $pathItem = $spec['paths'][$path] ?? [];
        $operationData = $this->refResolver->resolve($pathItem[$method] ?? []);
        $operationData = $this->mergePathLevelParameters($operationData, $pathItem);

        return [
            'method' => $method,
            'path' => $path,
            'operationData' => $operationData,
            'commandSuffix' => $this->resolveCommandSuffix($config, $method, $path, $operationData),
        ];
    }

    /**
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
    protected function mergePathLevelParameters(array $operationData, array $pathItem): array
    {
        $pathLevelParams = $pathItem['parameters'] ?? [];

        if (empty($pathLevelParams)) {
            return $operationData;
        }

        $key = fn (array $param) => ($param['name'] ?? '').':'.($param['in'] ?? '');
        $operationParams = $operationData['parameters'] ?? [];
        $existingKeys = array_map($key, $operationParams);

        foreach ($pathLevelParams as $pathParam) {
            $resolved = $this->refResolver->resolve($pathParam);

            if (! in_array($key($resolved), $existingKeys, true)) {
                $operationParams[] = $resolved;
            }
        }

        $operationData['parameters'] = $operationParams;

        return $operationData;
    }

    /**
     * @param  array<string, mixed>  $operationData
     */
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
