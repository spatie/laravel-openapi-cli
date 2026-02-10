<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();
});

it('registers a command with the specified namespace', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $namespace = 'test-flare';

    $config = OpenApiCli::register($specPath, $namespace);

    expect($config->getSpecPath())->toBe($specPath)
        ->and($config->getNamespace())->toBe($namespace);

    $registrations = OpenApiCli::getRegistrations();
    expect($registrations)->toHaveCount(1)
        ->and($registrations[0])->toBe($config);
});

it('can register multiple specs with different namespaces', function () {
    $specPath1 = __DIR__.'/../../flare-api.yaml';
    $specPath2 = __DIR__.'/../../flare-api.yaml';

    $config1 = OpenApiCli::register($specPath1, 'api1');
    $config2 = OpenApiCli::register($specPath2, 'api2');

    $registrations = OpenApiCli::getRegistrations();
    expect($registrations)->toHaveCount(2)
        ->and($registrations[0])->toBe($config1)
        ->and($registrations[1])->toBe($config2);
});

it('returns a fluent configuration object for chaining', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    $config = OpenApiCli::register($specPath, 'test-flare')
        ->baseUrl('https://api.example.com')
        ->bearer('test-token');

    expect($config->getBaseUrl())->toBe('https://api.example.com')
        ->and($config->getBearerToken())->toBe('test-token');
});

it('can chain all fluent methods', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    $config = OpenApiCli::register($specPath, 'test-flare')
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

    $config = OpenApiCli::register($specPath, 'test-flare')
        ->auth($callable);

    expect($config->getAuthCallable())->toBe($callable)
        ->and($config->getAuthCallable()())->toBe('dynamic-token');
});

it('clears all registrations', function () {
    OpenApiCli::register(__DIR__.'/../../flare-api.yaml', 'api1');
    OpenApiCli::register(__DIR__.'/../../flare-api.yaml', 'api2');

    expect(OpenApiCli::getRegistrations())->toHaveCount(2);

    OpenApiCli::clearRegistrations();

    expect(OpenApiCli::getRegistrations())->toHaveCount(0);
});

it('registers per-endpoint commands that appear in artisan list', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    OpenApiCli::register($specPath, 'flare');

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('flare:get-me')
        ->toContain('flare:get-projects')
        ->toContain('flare:post-projects')
        ->toContain('flare:list');
});

it('registers commands with path parameters as options', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    OpenApiCli::register($specPath, 'flare');

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('flare:get-projects-errors')
        ->toContain('flare:delete-projects-errors')
        ->toContain('flare:delete-teams-users');
});

it('can enable useOperationIds mode', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';

    $config = OpenApiCli::register($specPath, 'test-flare')
        ->useOperationIds();

    expect($config->shouldUseOperationIds())->toBeTrue();
});
