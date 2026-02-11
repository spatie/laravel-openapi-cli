<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-5xx-'.uniqid().'.yaml';

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
            '/projects/{id}' => [
                'get' => [
                    'summary' => 'Get project',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
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

it('detects 500 Internal Server Error and displays status code and response body', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Internal Server Error']),
            500
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 500 Error')
        ->expectsOutputToContain('Internal Server Error');
});

it('detects 502 Bad Gateway errors and displays status code and response body', function () {
    Http::fake([
        'https://api.example.com/projects/123' => Http::response(
            json_encode(['error' => 'Bad Gateway']),
            502
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects-id', ['--id' => '123'])
        ->assertFailed()
        ->expectsOutputToContain('HTTP 502 Error')
        ->expectsOutputToContain('Bad Gateway');
});

it('detects 503 Service Unavailable errors and displays status code and response body', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Service Unavailable']),
            503
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 503 Error')
        ->expectsOutputToContain('Service Unavailable');
});

it('displays 5xx error response body as pretty-printed JSON by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Internal Server Error', 'details' => ['trace' => 'stack trace here']]),
            500
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 500 Error')
        ->expectsOutputToContain('Internal Server Error');
});

it('displays 5xx error response body as minified JSON with --minify flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Internal Server Error', 'code' => 'DB_CONNECTION_FAILED']),
            500
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->assertFailed()
        ->expectsOutputToContain('HTTP 500 Error')
        ->expectsOutputToContain('{"error":"Internal Server Error","code":"DB_CONNECTION_FAILED"}');
});

it('displays 5xx error response headers with --headers flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Service Unavailable']),
            503,
            ['Retry-After' => '3600', 'X-Request-ID' => 'abc123']
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->assertFailed()
        ->expectsOutputToContain('HTTP 503 Error')
        ->expectsOutputToContain('HTTP/1.1 503 Service Unavailable')
        ->expectsOutputToContain('Retry-After')
        ->expectsOutputToContain('X-Request-ID');
});

it('displays non-JSON 5xx error responses with raw body and content-type notice', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            '<html><body><h1>500 Internal Server Error</h1><p>Something went wrong</p></body></html>',
            500,
            ['Content-Type' => 'text/html']
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 500 Error')
        ->expectsOutputToContain('Response is not JSON (content-type: text/html)')
        ->expectsOutputToContain('<h1>500 Internal Server Error</h1>');
});

it('exits with non-zero code on 5xx errors', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Internal Server Error']),
            500
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed();
});
