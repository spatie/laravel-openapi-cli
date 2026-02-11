<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\CommandNameGenerator;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\RefResolver;
use Spatie\OpenApiCli\SpecResolver;

class ListCommand extends Command
{
    protected $signature;

    protected $description = 'List all available API commands';

    public function __construct(
        protected CommandConfiguration $config,
    ) {
        $namespace = $config->getNamespace();
        $this->signature = $namespace !== '' ? "{$namespace}:list" : 'openapi:list';

        parent::__construct();
    }

    public function handle(): int
    {
        $this->displayBanner();

        $endpoints = $this->resolveEndpoints();

        $this->displayEndpoints($endpoints);

        return self::SUCCESS;
    }

    protected function displayBanner(): void
    {
        $banner = $this->config->getBanner();

        if ($banner === null) {
            return;
        }

        is_callable($banner)
            ? $banner($this)
            : $this->line($banner);

        $this->line('');
    }

    /** @return Collection<int, array{method: string, path: string, command: string, description: string}> */
    protected function resolveEndpoints(): Collection
    {
        $parser = new OpenApiParser(SpecResolver::resolve($this->config->getSpecPath(), $this->config));
        $spec = $parser->getSpec();
        $resolver = new RefResolver($spec);

        $rawEndpoints = $this->collectRawEndpoints($parser, $spec, $resolver);
        $suffixCounts = $rawEndpoints->countBy('commandSuffix');

        return $rawEndpoints
            ->map(function (array $endpoint) use ($suffixCounts, $parser) {
                $commandSuffix = $suffixCounts->get($endpoint['commandSuffix']) > 1
                    ? CommandNameGenerator::fromPathDisambiguated($endpoint['method'], $endpoint['path'])
                    : $endpoint['commandSuffix'];

                $commandName = $this->config->hasNamespace()
                    ? "{$this->config->getNamespace()}:{$commandSuffix}"
                    : $commandSuffix;

                return [
                    'method' => strtoupper($endpoint['method']),
                    'path' => $endpoint['path'],
                    'command' => $commandName,
                    'description' => $parser->getOperationSummary($endpoint['path'], $endpoint['method'])
                        ?? $parser->getOperationDescription($endpoint['path'], $endpoint['method'])
                        ?? '',
                ];
            })
            ->sort(function (array $a, array $b) {
                return strcmp($a['path'], $b['path'])
                    ?: $this->methodSortOrder($a['method']) <=> $this->methodSortOrder($b['method']);
            })
            ->values();
    }

    /** @return Collection<int, array{method: string, path: string, commandSuffix: string}> */
    protected function collectRawEndpoints(OpenApiParser $parser, array $spec, RefResolver $resolver): Collection
    {
        return collect($parser->getPathsWithMethods())
            ->flatMap(function (array $methods, string $path) use ($spec, $resolver) {
                return collect($methods)->map(function (string $method) use ($path, $spec, $resolver) {
                    $operationData = $resolver->resolve($spec['paths'][$path][$method] ?? []);

                    return [
                        'method' => $method,
                        'path' => $path,
                        'commandSuffix' => $this->resolveCommandSuffix($method, $path, $operationData),
                    ];
                });
            });
    }

    protected function resolveCommandSuffix(string $method, string $path, array $operationData): string
    {
        if (! $this->config->shouldUseOperationIds()) {
            return CommandNameGenerator::fromPath($method, $path);
        }

        $operationId = $operationData['operationId'] ?? null;

        return $operationId
            ? CommandNameGenerator::fromOperationId($operationId)
            : CommandNameGenerator::fromPath($method, $path);
    }

    protected function methodSortOrder(string $method): int
    {
        return match ($method) {
            'GET' => 1,
            'POST' => 2,
            'PUT' => 3,
            'PATCH' => 4,
            'DELETE' => 5,
            default => 999,
        };
    }

    /** @param Collection<int, array{method: string, path: string, command: string, description: string}> $endpoints */
    protected function displayEndpoints(Collection $endpoints): void
    {
        $methodWidth = 7;
        $maxCommandWidth = $endpoints->max(fn (array $endpoint) => strlen($endpoint['command']));

        $endpoints->each(function (array $endpoint) use ($methodWidth, $maxCommandWidth) {
            $method = str_pad($endpoint['method'], $methodWidth);
            $command = str_pad($endpoint['command'], $maxCommandWidth);
            $this->line("{$method}{$command}  {$endpoint['description']}");
        });
    }
}
