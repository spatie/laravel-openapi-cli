<?php

namespace Spatie\OpenApiCli\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\CommandNameGenerator;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\RefResolver;
use Spatie\OpenApiCli\SpecResolver;
use Symfony\Component\Console\Terminal;

class ListCommand extends Command
{
    protected $signature;

    protected $description = 'List all available API commands';

    /** @var array<string, string> */
    protected array $verbColors = [
        'GET' => 'blue',
        'HEAD' => '#6C7280',
        'POST' => 'yellow',
        'PUT' => 'yellow',
        'PATCH' => 'yellow',
        'DELETE' => 'red',
    ];

    protected static ?Closure $terminalWidthResolver = null;

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

    public static function getTerminalWidth(): int
    {
        return is_null(static::$terminalWidthResolver)
            ? (new Terminal)->getWidth()
            : call_user_func(static::$terminalWidthResolver);
    }

    public static function resolveTerminalWidthUsing(?Closure $resolver): void
    {
        static::$terminalWidthResolver = $resolver;
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
        $terminalWidth = static::getTerminalWidth();
        $maxMethod = $endpoints->max(fn (array $endpoint) => mb_strlen($endpoint['method'])) ?? 0;

        $lines = [''];

        foreach ($endpoints as $endpoint) {
            $method = $endpoint['method'];
            $path = $endpoint['path'];
            $command = $endpoint['command'];
            $description = $endpoint['description'];

            $color = $this->verbColors[$method] ?? '#6C7280';
            $paddedMethod = str_pad($method, $maxMethod);

            $formattedPath = (string) preg_replace(
                '/(\{[^}]+\})/',
                '<fg=yellow>$1</>',
                $path,
            );

            $indent = 2;
            $spacingAfterMethod = 2;
            $plainLineLength = $indent + $maxMethod + $spacingAfterMethod + mb_strlen($path) + 1 + mb_strlen($command);
            $dotsCount = max($terminalWidth - $plainLineLength, 3);
            $dots = str_repeat('.', $dotsCount);

            $lines[] = "  <fg={$color}>{$paddedMethod}</>"
                ."  <fg=white;options=bold>{$formattedPath}</>"
                ." <fg=#6C7280>{$dots} {$command}</>";

            if ($description !== '') {
                $descriptionIndent = str_repeat(' ', $indent + $maxMethod + $spacingAfterMethod);
                $lines[] = "{$descriptionIndent}<fg=#6C7280>{$description}</>";
            }
        }

        $lines[] = '';
        $lines[] = $this->determineEndpointCountOutput($endpoints, $terminalWidth);
        $lines[] = '';

        $this->output->writeln($lines);
    }

    /** @param Collection<int, array{method: string, path: string, command: string, description: string}> $endpoints */
    protected function determineEndpointCountOutput(Collection $endpoints, int $terminalWidth): string
    {
        $text = 'Showing ['.$endpoints->count().'] endpoints';

        $offset = max($terminalWidth - mb_strlen($text) - 2, 0);

        return str_repeat(' ', $offset).'<fg=blue;options=bold>'.$text.'</>';
    }
}
