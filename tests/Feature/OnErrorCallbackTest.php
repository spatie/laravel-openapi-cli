<?php

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\OpenApiCli;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-onerror-'.uniqid().'.yaml';

    $spec = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'Test API',
            'version' => '1.0.0',
        ],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/projects' => [
                'get' => [
                    'summary' => 'List projects',
                ],
            ],
        ],
    ];

    file_put_contents($this->specPath, Yaml::dump($spec, 10, 2));

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('calls onError callback with Response and Command instances', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    $callbackInvoked = false;

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) use (&$callbackInvoked) {
            $callbackInvoked = true;
            expect($response)->toBeInstanceOf(Response::class);
            expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class);
            expect($response->status())->toBe(403);

            return true;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed();

    expect($callbackInvoked)->toBeTrue();
});

it('suppresses default error output when callback returns true', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            $command->error('Custom error message');

            return true;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Custom error message')
        ->doesntExpectOutputToContain('HTTP 403 Error');
});

it('falls through to default error output when callback returns false', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 403 Error')
        ->expectsOutputToContain('Forbidden');
});

it('falls through to default error output when callback returns null', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Server Error']),
            500
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            return null;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 500 Error')
        ->expectsOutputToContain('Server Error');
});

it('uses default error handling when no callback is registered', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Bad Request']),
            400
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 400 Error')
        ->expectsOutputToContain('Bad Request');
});

it('handles 403 errors with custom message via callback', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            if ($response->status() === 403) {
                $command->error('Your API token lacks permission for this endpoint.');

                return true;
            }

            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Your API token lacks permission for this endpoint.')
        ->doesntExpectOutputToContain('HTTP 403 Error');
});

it('handles 500 errors with custom message via callback', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Internal Server Error']),
            500
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            if ($response->status() === 500) {
                $command->error('Server error — try again later.');

                return true;
            }

            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Server error — try again later.')
        ->doesntExpectOutputToContain('HTTP 500 Error');
});

it('handles 429 errors with Retry-After header via callback', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Too Many Requests']),
            429,
            ['Retry-After' => '30']
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            if ($response->status() === 429) {
                $command->warn('Rate limited. Try again in '.$response->header('Retry-After').'s.');

                return true;
            }

            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Rate limited. Try again in 30s.')
        ->doesntExpectOutputToContain('HTTP 429 Error');
});

it('can use match expression in callback for multiple status codes', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            return match ($response->status()) {
                403 => $command->error('No permission.') || true,
                500 => $command->error('Server error.') || true,
                default => false,
            };
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('No permission.');
});

it('allows callback to render multi-line output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Not Found']),
            404
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            if ($response->status() === 404) {
                $command->line('Resource not found.');
                $command->line('Please check the endpoint and try again.');

                return true;
            }

            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Resource not found.')
        ->expectsOutputToContain('Please check the endpoint and try again.')
        ->doesntExpectOutputToContain('HTTP 404 Error');
});

it('still returns FAILURE exit code when callback handles the error', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->onError(function ($response, $command) {
            $command->info('Handled gracefully.');

            return true;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed();
});

it('returns self from onError() for method chaining', function () {
    $config = new CommandConfiguration($this->specPath, 'test-api');

    $result = $config->onError(fn () => true);

    expect($result)->toBeInstanceOf(CommandConfiguration::class);
});

it('returns null from getOnErrorCallable() when not set', function () {
    $config = new CommandConfiguration($this->specPath, 'test-api');

    expect($config->getOnErrorCallable())->toBeNull();
});
