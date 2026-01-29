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

        // Ensure the Console Kernel is properly bound
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
     * Override artisan() method to automatically register OpenAPI commands before execution.
     *
     * This ensures that any commands registered via OpenApiCli::register() are available
     * to artisan without requiring manual intervention in each test.
     */
    public function artisan($command, $parameters = [])
    {
        // Automatically register any OpenAPI commands that were registered since setUp()
        $this->registerOpenApiCommands();

        return parent::artisan($command, $parameters);
    }

    /**
     * Register OpenAPI commands without re-registering the service provider.
     *
     * This method registers commands that were added via OpenApiCli::register()
     * without disrupting the application container state.
     */
    protected function registerOpenApiCommands(): void
    {
        // Ensure Console Kernel binding exists before we try to use it
        $this->ensureConsoleKernelBound();

        // Manually register all OpenAPI commands
        foreach (\Spatie\OpenApiCli\Facades\OpenApiCli::getRegistrations() as $config) {
            $signature = $config->getSignature();

            // Re-bind the command with the current configuration
            // This ensures we always use the latest spec path
            $this->app->singleton($signature, function () use ($config) {
                return new \Spatie\OpenApiCli\Commands\OpenApiCommand($config);
            });

            // Check if command is already registered with Artisan
            $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
            $commands = $kernel->all();

            // Only register if not already in Artisan's command list
            if (! isset($commands[$signature])) {
                try {
                    $kernel->registerCommand($this->app->make($signature));
                } catch (\Exception $e) {
                    // If registration fails, ensure Console Kernel is bound and retry
                    $this->ensureConsoleKernelBound();
                    $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
                        ->registerCommand($this->app->make($signature));
                }
            }
        }
    }
}
