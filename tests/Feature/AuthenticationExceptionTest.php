<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Exceptions\AuthenticationException;
use Spatie\OpenApiCli\OpenApiCli;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-auth-exception-'.uniqid().'.yaml';

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
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('aborts before sending the request when the auth callable throws', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->auth(fn () => throw new AuthenticationException('Your session has expired.'));

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Your session has expired.');

    Http::assertNothingSent();
});

it('prints the hint on its own line when present', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->auth(fn () => throw new AuthenticationException(
            'Your session has expired.',
            hint: 'Run `login` to authenticate again.',
        ));

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Your session has expired.')
        ->expectsOutputToContain('Run `login` to authenticate again.');
});

it('stops retrying and skips onError when the retryOn callable throws', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Unauthorized']),
            401
        ),
    ]);

    $onErrorCalled = false;

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn () => throw new AuthenticationException('Token refresh failed.', hint: 'Log in again.'))
        ->onError(function () use (&$onErrorCalled) {
            $onErrorCalled = true;

            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Token refresh failed.')
        ->expectsOutputToContain('Log in again.')
        ->doesntExpectOutputToContain('HTTP 401 Error');

    expect($onErrorCalled)->toBeFalse();
    Http::assertSentCount(1);
});

it('aborts a retry when the auth callable throws on re-invocation', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::sequence()
            ->push(json_encode(['error' => 'Unauthorized']), 401)
            ->push(json_encode(['data' => 'ok']), 200),
    ]);

    $authCalls = 0;

    OpenApiCli::register($this->specPath, 'test-api')
        ->auth(function () use (&$authCalls) {
            $authCalls++;

            if ($authCalls > 1) {
                throw new AuthenticationException('Refreshed credentials are invalid.');
            }

            return 'token-1';
        })
        ->retryOn(fn ($response) => $response->status() === 401);

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('Refreshed credentials are invalid.');

    expect($authCalls)->toBe(2);
    Http::assertSentCount(1);
});

it('returns a failure exit code', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->auth(fn () => throw new AuthenticationException('Not authenticated.'));

    $this->artisan('test-api:get-projects')
        ->assertExitCode(1);
});

it('lets other exceptions from the auth callable propagate', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->auth(fn () => throw new RuntimeException('Something else broke.'));

    expect(fn () => $this->artisan('test-api:get-projects'))
        ->toThrow(RuntimeException::class, 'Something else broke.');

    Http::assertNothingSent();
});
