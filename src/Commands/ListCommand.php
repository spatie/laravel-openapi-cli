<?php

namespace Spatie\OpenApiCli\Commands;

use Illuminate\Console\Command;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\CommandNameGenerator;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\RefResolver;

class ListCommand extends Command
{
    protected $signature;

    protected $description = 'List all available API commands';

    public function __construct(
        protected CommandConfiguration $config,
    ) {
        $this->signature = $config->getPrefix().':list';

        parent::__construct();
    }

    public function handle(): int
    {
        $parser = new OpenApiParser($this->config->getSpecPath());
        $pathsWithMethods = $parser->getPathsWithMethods();
        $spec = $parser->getSpec();
        $resolver = new RefResolver($spec);

        // First pass: collect all command suffixes
        $rawEndpoints = [];
        foreach ($pathsWithMethods as $path => $methods) {
            foreach ($methods as $method) {
                $operationData = $spec['paths'][$path][$method] ?? [];
                $operationData = $resolver->resolve($operationData);

                if ($this->config->shouldUseOperationIds()) {
                    $operationId = $operationData['operationId'] ?? null;
                    $commandSuffix = $operationId
                        ? CommandNameGenerator::fromOperationId($operationId)
                        : CommandNameGenerator::fromPath($method, $path);
                } else {
                    $commandSuffix = CommandNameGenerator::fromPath($method, $path);
                }

                $rawEndpoints[] = [
                    'method' => $method,
                    'path' => $path,
                    'commandSuffix' => $commandSuffix,
                ];
            }
        }

        // Detect collisions and disambiguate
        $suffixCounts = [];
        foreach ($rawEndpoints as $ep) {
            $suffixCounts[$ep['commandSuffix']] = ($suffixCounts[$ep['commandSuffix']] ?? 0) + 1;
        }

        $endpoints = [];
        foreach ($rawEndpoints as $ep) {
            $commandSuffix = $ep['commandSuffix'];
            if ($suffixCounts[$commandSuffix] > 1) {
                $commandSuffix = CommandNameGenerator::fromPathDisambiguated($ep['method'], $ep['path']);
            }

            $commandName = $this->config->getPrefix().':'.$commandSuffix;
            $summary = $parser->getOperationSummary($ep['path'], $ep['method']);
            $description = $parser->getOperationDescription($ep['path'], $ep['method']);

            $endpoints[] = [
                'method' => strtoupper($ep['method']),
                'path' => $ep['path'],
                'command' => $commandName,
                'description' => $summary ?? $description ?? '',
            ];
        }

        usort($endpoints, function ($a, $b) {
            $pathCompare = strcmp($a['path'], $b['path']);

            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            $methodOrder = ['GET' => 1, 'POST' => 2, 'PUT' => 3, 'PATCH' => 4, 'DELETE' => 5];
            $aOrder = $methodOrder[$a['method']] ?? 999;
            $bOrder = $methodOrder[$b['method']] ?? 999;

            return $aOrder <=> $bOrder;
        });

        $methodWidth = 7;
        $maxCommandWidth = 0;
        foreach ($endpoints as $endpoint) {
            $maxCommandWidth = max($maxCommandWidth, strlen($endpoint['command']));
        }

        foreach ($endpoints as $endpoint) {
            $method = str_pad($endpoint['method'], $methodWidth);
            $command = str_pad($endpoint['command'], $maxCommandWidth);
            $this->line("{$method}{$command}  {$endpoint['description']}");
        }

        return self::SUCCESS;
    }
}
