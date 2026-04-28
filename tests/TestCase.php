<?php

namespace Spatie\OpenApiCli\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\OpenApiCli\Commands\EndpointCommand;
use Spatie\OpenApiCli\Commands\ListCommand;
use Spatie\OpenApiCli\EndpointResolver;
use Spatie\OpenApiCli\OpenApiCli;
use Spatie\OpenApiCli\OpenApiCliServiceProvider;
use Spatie\OpenApiCli\OpenApiParser;
use Spatie\OpenApiCli\RefResolver;
use Spatie\OpenApiCli\SpecResolver;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        // Clear registrations before booting the app to prevent stale spec file references
        OpenApiCli::clearRegistrations();

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
                Kernel::class,
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
        $app->singleton(Kernel::class, \Orchestra\Testbench\Console\Kernel::class);
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
            $resolver = new EndpointResolver(new RefResolver($spec));
            $namespace = $config->getNamespace();

            $endpoints = $resolver->resolve($config, $parser->getPathsWithMethods(), $spec);

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
            $kernel = $this->app->make(Kernel::class);

            foreach ($commandBindings as $binding) {
                try {
                    $command = $this->app->make($binding);
                    $kernel->registerCommand($command);
                } catch (\Exception $e) {
                    $this->ensureConsoleKernelBound();
                    $command = $this->app->make($binding);
                    $this->app->make(Kernel::class)
                        ->registerCommand($command);
                }
            }
        }
    }
}
