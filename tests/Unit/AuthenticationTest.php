<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\Commands\OpenApiCommand;

beforeEach(function () {
    // Create a temporary spec file for testing
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

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication to a fresh HTTP builder
    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));

    // Make a request to verify the token is applied
    $authenticatedHttp->get('https://api.example.com/test');

    // Assert the Authorization header was added
    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization') &&
               $request->header('Authorization')[0] === 'Bearer test-token-123';
    });
});

it('applies API key authentication correctly', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->apiKey('X-API-Key', 'my-api-key-456');

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication to a fresh HTTP builder
    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));

    // Make a request to verify the API key is applied
    $authenticatedHttp->get('https://api.example.com/test');

    // Assert the custom header was added
    Http::assertSent(function ($request) {
        return $request->hasHeader('X-API-Key') &&
               $request->header('X-API-Key')[0] === 'my-api-key-456';
    });
});

it('applies basic authentication correctly', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    $config->basic('testuser', 'testpass');

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication to a fresh HTTP builder
    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));

    // Make a request to verify basic auth is applied
    $authenticatedHttp->get('https://api.example.com/test');

    // Assert the Authorization header was added with basic auth
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

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication (first invocation)
    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));
    $authenticatedHttp->get('https://api.example.com/test');

    // Assert the callable was invoked and token was applied
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

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication twice to simulate two separate requests
    $http1 = $method->invoke($command, Http::withHeaders([]));
    $http1->get('https://api.example.com/test');

    $http2 = $method->invoke($command, Http::withHeaders([]));
    $http2->get('https://api.example.com/test');

    // Assert the callable was invoked twice (fresh on each request)
    expect($invocationCount)->toBe(2);
});

it('returns unmodified http client when no authentication is configured', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication (should return unchanged)
    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));

    // Make a request
    $authenticatedHttp->get('https://api.example.com/test');

    // Assert no Authorization header was added
    Http::assertSent(function ($request) {
        return ! $request->hasHeader('Authorization');
    });
});

it('prioritizes bearer token over other auth methods when multiple are configured', function () {
    Http::fake();

    $config = new CommandConfiguration($this->specPath, 'test-api');
    // Configure multiple auth methods (this is unusual but testing priority)
    $config->bearer('bearer-token');
    $config->apiKey('X-API-Key', 'api-key-value');
    $config->basic('user', 'pass');

    $command = new OpenApiCommand($config);

    // Use reflection to access the protected applyAuthentication method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('applyAuthentication');
    $method->setAccessible(true);

    // Apply authentication
    $authenticatedHttp = $method->invoke($command, Http::withHeaders([]));

    // Make a request
    $authenticatedHttp->get('https://api.example.com/test');

    // Assert bearer token was applied (takes priority)
    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization') &&
               $request->header('Authorization')[0] === 'Bearer bearer-token' &&
               ! $request->hasHeader('X-API-Key'); // API key should not be applied
    });
});
