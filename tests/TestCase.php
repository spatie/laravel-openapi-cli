<?php

namespace Spatie\OpenApiCli\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\OpenApiCli\OpenApiCliServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Spatie\\OpenApiCli\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Force-bind the Console Kernel to prevent container errors when tests
        // manually re-register the service provider with $this->app->register(..., true)
        $this->ensureConsoleKernelBound();
    }

    protected function tearDown(): void
    {
        // Re-bind Console Kernel before teardown to prevent destructor errors
        $this->ensureConsoleKernelBound();

        parent::tearDown();
    }

    /**
     * Ensure the Console Kernel is properly bound in the container.
     */
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

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    /**
     * Resolve application Console Kernel implementation.
     *
     * This method ensures the Console Kernel is properly bound to prevent
     * "Target [Illuminate\Contracts\Console\Kernel] is not instantiable" errors
     * when running multiple tests with artisan command execution.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationConsoleKernel($app)
    {
        $app->singleton(\Illuminate\Contracts\Console\Kernel::class, \Orchestra\Testbench\Console\Kernel::class);
    }

    /**
     * Refresh OpenAPI command registrations without re-registering the service provider.
     *
     * This helper method manually registers commands that were added via OpenApiCli::register()
     * without disrupting the application container state by re-registering the entire service provider.
     */
    protected function refreshServiceProvider(): void
    {
        // Ensure Console Kernel binding exists before we try to use it
        $this->ensureConsoleKernelBound();

        // Manually register all OpenAPI commands without re-registering the provider
        foreach (\Spatie\OpenApiCli\Facades\OpenApiCli::getRegistrations() as $config) {
            $this->app->singleton($config->getSignature(), function () use ($config) {
                return new \Spatie\OpenApiCli\Commands\OpenApiCommand($config);
            });

            // Register the command with Artisan
            $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
                ->registerCommand($this->app->make($config->getSignature()));
        }
    }
}
