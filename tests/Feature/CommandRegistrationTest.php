<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    // Clear registrations before each test
    OpenApiCli::clearRegistrations();
});

it('registers a command with the specified signature', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $signature = 'test:flare-api';

    $config = OpenApiCli::register($specPath, $signature);

    expect($config->getSpecPath())->toBe($specPath)
        ->and($config->getSignature())->toBe($signature);

    $registrations = OpenApiCli::getRegistrations();
    expect($registrations)->toHaveCount(1)
        ->and($registrations[0])->toBe($config);
});

it('can register multiple specs with different command names', function () {
    $specPath1 = __DIR__.'/../../flare-api.yaml';
    $specPath2 = __DIR__.'/../../flare-api.yaml';

    $config1 = OpenApiCli::register($specPath1, 'test:api1');
    $config2 = OpenApiCli::register($specPath2, 'test:api2');

    $registrations = OpenApiCli::getRegistrations();
    expect($registrations)->toHaveCount(2)
        ->and($registrations[0])->toBe($config1)
        ->and($registrations[1])->toBe($config2);
});

it('returns a fluent configuration object for chaining', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    $config = OpenApiCli::register($specPath, 'test:flare-api')
        ->baseUrl('https://api.example.com')
        ->bearer('test-token');

    expect($config->getBaseUrl())->toBe('https://api.example.com')
        ->and($config->getBearerToken())->toBe('test-token');
});

it('can chain all fluent methods', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    $config = OpenApiCli::register($specPath, 'test:flare-api')
        ->baseUrl('https://api.example.com')
        ->bearer('test-token')
        ->apiKey('X-API-Key', 'key-value')
        ->basic('user', 'pass');

    expect($config->getBaseUrl())->toBe('https://api.example.com')
        ->and($config->getBearerToken())->toBe('test-token')
        ->and($config->getApiKeyHeader())->toBe('X-API-Key')
        ->and($config->getApiKeyValue())->toBe('key-value')
        ->and($config->getBasicUsername())->toBe('user')
        ->and($config->getBasicPassword())->toBe('pass');
});

it('can configure callable auth', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $callable = fn () => 'dynamic-token';

    $config = OpenApiCli::register($specPath, 'test:flare-api')
        ->auth($callable);

    expect($config->getAuthCallable())->toBe($callable)
        ->and($config->getAuthCallable()())->toBe('dynamic-token');
});

it('clears all registrations', function () {
    OpenApiCli::register(__DIR__.'/../../flare-api.yaml', 'test:api1');
    OpenApiCli::register(__DIR__.'/../../flare-api.yaml', 'test:api2');

    expect(OpenApiCli::getRegistrations())->toHaveCount(2);

    OpenApiCli::clearRegistrations();

    expect(OpenApiCli::getRegistrations())->toHaveCount(0);
});

it('can register command that appears in artisan list', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $signature = 'test:flare-api-check';

    OpenApiCli::register($specPath, $signature);

    // Re-bootstrap the service provider to register commands
    $this->refreshServiceProvider();

    $commands = array_keys(Artisan::all());

    expect($commands)->toContain($signature);
});
