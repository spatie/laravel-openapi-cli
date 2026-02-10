<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\Commands\EndpointCommand;

beforeEach(function () {
    $this->specPath = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';
    file_put_contents($this->specPath, <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /test:
    get:
      summary: Test endpoint
YAML
    );
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('applies bearer token authentication correctly', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->bearer('test-token-123');

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization') &&
               $request->header('Authorization')[0] === 'Bearer test-token-123';
    });
});

it('applies API key authentication correctly', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->apiKey('X-API-Key', 'my-api-key-456');

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-API-Key') &&
               $request->header('X-API-Key')[0] === 'my-api-key-456';
    });
});

it('applies basic authentication correctly', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->basic('testuser', 'testpass');

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        if (! $request->hasHeader('Authorization')) {
            return false;
        }

        $authHeader = $request->header('Authorization')[0];
        $expectedCredentials = base64_encode('testuser:testpass');

        return $authHeader === "Basic {$expectedCredentials}";
    });
});

it('applies callable authentication and invokes function', function () {
    Http::fake();

    $invocationCount = 0;

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->auth(function () use (&$invocationCount) {
        $invocationCount++;

        return 'dynamic-token-'.$invocationCount;
    });

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    expect($invocationCount)->toBe(1);
    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization') &&
               $request->header('Authorization')[0] === 'Bearer dynamic-token-1';
    });
});

it('invokes callable authentication fresh on each request', function () {
    Http::fake();

    $invocationCount = 0;

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->auth(function () use (&$invocationCount) {
        $invocationCount++;

        return 'fresh-token-'.$invocationCount;
    });

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $http1 = $method->invoke($command, Http::withHeaders([]));
    $http1->get('https://api.example.com/test');

    $http2 = $method->invoke($command, Http::withHeaders([]));
    $http2->get('https://api.example.com/test');

    expect($invocationCount)->toBe(2);
});

it('returns unmodified http client when no authentication is configured', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        return ! $request->hasHeader('Authorization');
    });
});

it('prioritizes bearer token over other auth methods when multiple are configured', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->bearer('bearer-token');
    $config->apiKey('X-API-Key', 'api-key-value');
    $config->basic('user', 'pass');

    $command = new EndpointCommand($config, 'get', '/test', ['summary' => 'Test endpoint'], 'get-test');

    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization') &&
               $request->header('Authorization')[0] === 'Bearer bearer-token' &&
               ! $request->hasHeader('X-API-Key');
    });
});
