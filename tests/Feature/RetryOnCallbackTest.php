<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\OpenApiCli;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-retry-'.uniqid().'.yaml';

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
                'post' => [
                    'summary' => 'Create project',
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

it('retries on a 4xx response when callback returns true', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::sequence()
            ->push(json_encode(['error' => 'Unauthorized']), 401)
            ->push(json_encode(['data' => 'ok']), 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn ($response) => $response->status() === 401);

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

it('does not invoke the retry callback on a 2xx response', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['data' => 'ok']),
            200
        ),
    ]);

    $invocations = 0;

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(function () use (&$invocations) {
            $invocations++;

            return true;
        });

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    expect($invocations)->toBe(0);
    Http::assertSentCount(1);
});

it('re-invokes the auth callable on each retry', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::sequence()
            ->push(json_encode(['error' => 'Unauthorized']), 401)
            ->push(json_encode(['data' => 'ok']), 200),
    ]);

    $authCalls = 0;

    OpenApiCli::register($this->specPath, 'test-api')
        ->auth(function () use (&$authCalls) {
            $authCalls++;

            return "token-{$authCalls}";
        })
        ->retryOn(fn ($response) => $response->status() === 401);

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    expect($authCalls)->toBe(2);

    $sent = Http::recorded();
    expect($sent[0][0]->header('Authorization'))->toBe(['Bearer token-1']);
    expect($sent[1][0]->header('Authorization'))->toBe(['Bearer token-2']);
});

it('falls through to onError when the retry callback returns false', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Forbidden']),
            403
        ),
    ]);

    $onErrorCalled = false;

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn () => false)
        ->onError(function () use (&$onErrorCalled) {
            $onErrorCalled = true;

            return false;
        });

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 403 Error');

    expect($onErrorCalled)->toBeTrue();
    Http::assertSentCount(1);
});

it('caps total retries at maxRetries when callback always returns true', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Unauthorized']),
            401
        ),
    ]);

    $retryCalls = 0;

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(function () use (&$retryCalls) {
            $retryCalls++;

            return true;
        }, maxRetries: 2);

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 401 Error');

    expect($retryCalls)->toBe(2);
    Http::assertSentCount(3);
});

it('defaults maxRetries to 1', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Unauthorized']),
            401
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn () => true);

    $this->artisan('test-api:get-projects')
        ->assertFailed();

    Http::assertSentCount(2);
});

it('behaves identically to today when no retryOn is configured', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Unauthorized']),
            401
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 401 Error');

    Http::assertSentCount(1);
});

it('preserves a JSON request body across retries', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::sequence()
            ->push(json_encode(['error' => 'Unauthorized']), 401)
            ->push(json_encode(['data' => 'created']), 201),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn ($response) => $response->status() === 401);

    $this->artisan('test-api:post-projects', ['--input' => '{"name":"x"}'])
        ->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        return $request->body() === '{"name":"x"}';
    });

    $sent = Http::recorded();
    expect($sent[0][0]->body())->toBe('{"name":"x"}');
    expect($sent[1][0]->body())->toBe('{"name":"x"}');
});

it('preserves form fields across retries', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::sequence()
            ->push(json_encode(['error' => 'Unauthorized']), 401)
            ->push(json_encode(['data' => 'created']), 201),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn ($response) => $response->status() === 401);

    $this->artisan('test-api:post-projects', ['--field' => ['name=x']])
        ->assertSuccessful();

    $sent = Http::recorded();
    expect($sent[0][0]->body())->toBe('{"name":"x"}');
    expect($sent[1][0]->body())->toBe('{"name":"x"}');
});

it('returns self from retryOn() for method chaining', function () {
    $config = new CommandConfiguration($this->specPath, 'test-api');

    $result = $config->retryOn(fn () => true);

    expect($result)->toBeInstanceOf(CommandConfiguration::class);
});

it('returns null and 0 from retry getters when not configured', function () {
    $config = new CommandConfiguration($this->specPath, 'test-api');

    expect($config->getRetryCallable())->toBeNull();
    expect($config->getRetryMaxRetries())->toBe(0);
});

it('treats a truthy non-bool return value as a retry signal', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::sequence()
            ->push(json_encode(['error' => 'Unauthorized']), 401)
            ->push(json_encode(['data' => 'ok']), 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->retryOn(fn () => 'refreshed-token-value');

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    Http::assertSentCount(2);
});
