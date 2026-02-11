<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    Http::fake([
        'https://api.example.com/*' => Http::response(['success' => true], 200),
    ]);

    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/projects' => [
                'get' => [
                    'summary' => 'List projects',
                    'responses' => [
                        '200' => [
                            'description' => 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'array'],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'summary' => 'Create project',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'object'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'OK'],
                    ],
                ],
            ],
            '/forms' => [
                'post' => [
                    'summary' => 'Submit form',
                    'requestBody' => [
                        'content' => [
                            'application/x-www-form-urlencoded' => [
                                'schema' => ['type' => 'object'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'OK'],
                    ],
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test-spec-debug-'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('shows method and URL in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('GET https://api.example.com/projects');
});

it('shows Accept header from spec in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Accept: application/json');
});

it('shows bearer auth header in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->bearer('my-secret-token');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Authorization: Bearer my-secret-token');
});

it('shows api key header in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->apiKey('X-API-Key', 'key-123');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('X-API-Key: key-123');
});

it('shows basic auth header in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->basic('user', 'pass');

    $expected = 'Authorization: Basic '.base64_encode('user:pass');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain($expected);
});

it('shows JSON body in debug output when using --input', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-projects', [
        '-vvv' => true,
        '--input' => '{"name":"Test Project"}',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Request Body')
        ->expectsOutputToContain('{"name":"Test Project"}');
});

it('shows form fields as JSON body in debug output when using --field', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-projects', [
        '-vvv' => true,
        '--field' => ['name=Test Project'],
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Request Body')
        ->expectsOutputToContain('{"name":"Test Project"}');
});

it('shows form-encoded fields individually in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-forms', [
        '-vvv' => true,
        '--field' => ['name=Test', 'email=test@example.com'],
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('name: Test')
        ->expectsOutputToContain('email: test@example.com');
});

it('does not show debug output without -vvv', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Request Headers');
});

it('shows Content-Type header in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Content-Type: application/json');
});

it('shows section headers in debug output', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->bearer('token');

    $this->artisan('test-api:get-projects', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Request Headers');
});
