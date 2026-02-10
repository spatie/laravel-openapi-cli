<?php

use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\Commands\EndpointCommand;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();
});

it('resolves base URL from configuration when provided', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $config = new CommandConfiguration($specPath, 'test-api');
    $config->baseUrl('https://custom.example.com');

    $command = new EndpointCommand($config, 'get', '/me', ['summary' => 'Get me'], 'get-me');

    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('resolveBaseUrl');
    $method->setAccessible(true);

    $baseUrl = $method->invoke($command);

    expect($baseUrl)->toBe('https://custom.example.com');
});

it('falls back to spec servers url when no configuration provided', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $config = new CommandConfiguration($specPath, 'test-api');

    $command = new EndpointCommand($config, 'get', '/me', ['summary' => 'Get me'], 'get-me');

    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('resolveBaseUrl');
    $method->setAccessible(true);

    $baseUrl = $method->invoke($command);

    expect($baseUrl)->toBeString()->not->toBeEmpty();
});

it('throws exception when no base url is available', function () {
    $specPath = sys_get_temp_dir().'/test-spec-no-servers-'.uniqid().'.yaml';
    $specContent = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
YAML;

    file_put_contents($specPath, $specContent);

    try {
        $config = new CommandConfiguration($specPath, 'test-api');
        $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('resolveBaseUrl');
        $method->setAccessible(true);

        $method->invoke($command);
    } finally {
        if (file_exists($specPath)) {
            unlink($specPath);
        }
    }
})->throws(\RuntimeException::class, 'No base URL available');

it('prefers configured base url over spec servers url', function () {
    $specPath = __DIR__.'/../../flare-api.yaml';
    $config = new CommandConfiguration($specPath, 'test-api');
    $config->baseUrl('https://override.example.com');

    $command = new EndpointCommand($config, 'get', '/me', ['summary' => 'Get me'], 'get-me');

    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('resolveBaseUrl');
    $method->setAccessible(true);

    $baseUrl = $method->invoke($command);

    expect($baseUrl)->toBe('https://override.example.com');
});

it('handles spec with empty servers array', function () {
    $specPath = sys_get_temp_dir().'/test-spec-empty-servers-'.uniqid().'.yaml';
    $specContent = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers: []
paths:
  /test:
    get:
      summary: Test endpoint
YAML;

    file_put_contents($specPath, $specContent);

    try {
        $config = new CommandConfiguration($specPath, 'test-api');
        $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('resolveBaseUrl');
        $method->setAccessible(true);

        $method->invoke($command);
    } finally {
        if (file_exists($specPath)) {
            unlink($specPath);
        }
    }
})->throws(\RuntimeException::class, 'No base URL available');
