<?php

namespace Spatie\OpenApiCli\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\OpenApiCli\CommandNameGenerator;
use Spatie\OpenApiCli\Commands\EndpointCommand;
use Spatie\OpenApiCli\Commands\ListCommand;
use Spatie\OpenApiCli\OpenApiCliServiceProvider;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\RefResolver;
use Spatie\OpenApiCli\SpecResolver;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        // Clear registrations before booting the app to prevent stale spec file references
        \Spatie\OpenApiCli\OpenApiCli::clearRegistrations();

        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Spatie\\OpenApiCli\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Ensure the Console Kernel is properly bound
        $this->ensureConsoleKernelBound();
    }

    protected function tearDown(): void
    {
        // Re-bind Console Kernel before teardown to prevent destructor errors
        $this->ensureConsoleKernelBound();

        parent::tearDown();
    }

    protected function ensureConsoleKernelBound(): void
    {
        if (isset($this->app)) {
            $this->app->singleton(
                \Illuminate\Contracts\Console\Kernel::class,
                \Orchestra\Testbench\Console\Kernel::class
            );
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            OpenApiCliServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function resolveApplicationConsoleKernel($app)
    {
        $app->singleton(\Illuminate\Contracts\Console\Kernel::class, \Orchestra\Testbench\Console\Kernel::class);
    }

    public function artisan($command, $parameters = [])
    {
        $this->registerOpenApiCommands();

        return parent::artisan($command, $parameters);
    }

    protected function registerOpenApiCommands(): void
    {
        $this->ensureConsoleKernelBound();

        foreach (\Spatie\OpenApiCli\Facades\OpenApiCli::getRegistrations() as $config) {
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

            // Register all commands with Artisan
            $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

            foreach ($commandBindings as $binding) {
                try {
                    $command = $this->app->make($binding);
                    $kernel->registerCommand($command);
                } catch (\Exception $e) {
                    $this->ensureConsoleKernelBound();
                    $command = $this->app->make($binding);
                    $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
                        ->registerCommand($command);
                }
            }
        }
    }

    /**
     * @return array<int, array{method: string, path: string, operationData: array, commandSuffix: string}>
     */
    protected function resolveEndpoints(\Spatie\OpenApiCli\CommandConfiguration $config, array $pathsWithMethods, array $spec, RefResolver $resolver): array
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
                $endpoint['commandSuffix'] = CommandNameGenerator::fromPathDisambiguated($endpoint['method'], $endpoint['path']);
            }
        }

        return $endpoints;
    }
}
