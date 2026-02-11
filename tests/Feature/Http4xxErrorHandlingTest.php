<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-4xx-'.uniqid().'.yaml';

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

it('detects 400 Bad Request errors and displays status code and response body', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Invalid request', 'message' => 'Missing required field: name']),
            400
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 400 Error')
        ->expectsOutputToContain('Invalid request');
});

it('detects 404 Not Found errors and displays status code and response body', function () {
    Http::fake([
        'https://api.example.com/projects/999' => Http::response(
            json_encode(['error' => 'Not Found', 'message' => 'Project with ID 999 does not exist']),
            404
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects-id', ['--id' => '999'])
        ->assertFailed()
        ->expectsOutputToContain('HTTP 404 Error')
        ->expectsOutputToContain('Not Found');
});

it('detects 422 Unprocessable Entity errors and displays validation details', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode([
                'error' => 'Validation failed',
                'errors' => [
                    'name' => ['The name field is required.'],
                    'team_id' => ['The team_id must be an integer.'],
                ],
            ]),
            422
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 422 Error')
        ->expectsOutputToContain('Validation failed');
});

it('displays 4xx error response body as pretty-printed JSON by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Bad Request', 'details' => ['field' => 'name', 'issue' => 'required']]),
            400
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 400 Error')
        ->expectsOutputToContain('Bad Request');
});

it('displays 4xx error response body as minified JSON with --minify flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Bad Request', 'code' => 'INVALID_INPUT']),
            400
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->assertFailed()
        ->expectsOutputToContain('HTTP 400 Error')
        ->expectsOutputToContain('{"error":"Bad Request","code":"INVALID_INPUT"}');
});

it('displays 4xx error response headers with --headers flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Unauthorized']),
            401,
            ['WWW-Authenticate' => 'Bearer realm="api"', 'X-RateLimit-Remaining' => '0']
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->assertFailed()
        ->expectsOutputToContain('HTTP 401 Error')
        ->expectsOutputToContain('HTTP/1.1 401 Unauthorized')
        ->expectsOutputToContain('WWW-Authenticate')
        ->expectsOutputToContain('X-RateLimit-Remaining');
});

it('displays non-JSON 4xx error responses with raw body and content-type notice', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            '<html><body><h1>400 Bad Request</h1><p>Invalid input</p></body></html>',
            400,
            ['Content-Type' => 'text/html']
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 400 Error')
        ->expectsOutputToContain('Response is not JSON (content-type: text/html)')
        ->expectsOutputToContain('<h1>400 Bad Request</h1>');
});

it('exits with non-zero code on 4xx errors', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['error' => 'Bad Request']),
            400
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertFailed();
});
