<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    Http::fake([
        'https://api.example.com/*' => Http::response(['success' => true], 200),
    ]);

    // Create a simple OpenAPI spec
    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/projects' => [
                'post' => [
                    'summary' => 'Create project',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'object'],
                            ],
                        ],
                    ],
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
            '/users' => [
                'post' => [
                    'summary' => 'Create user',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
                'put' => [
                    'summary' => 'Update user',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test-spec-'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));
});

afterEach(function () {
    if (isset($this->specPath) && file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('sends JSON input with POST method auto-detection', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{"name":"Test Project","team_id":1}',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->method() === 'POST'
            && $request->data() === ['name' => 'Test Project', 'team_id' => 1];
    });
});

it('sends nested JSON structures', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{"name":"Test","data":{"nested":true,"count":5}}',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->data() === [
                'name' => 'Test',
                'data' => [
                    'nested' => true,
                    'count' => 5,
                ],
            ];
    });
});

it('sends JSON arrays', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{"tags":["php","laravel"],"active":true}',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->data() === [
                'tags' => ['php', 'laravel'],
                'active' => true,
            ];
    });
});

it('works with explicit PUT method', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'users',
        '--method' => 'PUT',
        '--input' => '{"name":"Updated"}',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users'
            && $request->method() === 'PUT'
            && $request->data() === ['name' => 'Updated'];
    });
});

it('validates JSON syntax and shows error for invalid JSON', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{"name":"Test"',  // Missing closing brace
    ])
        ->assertFailed()
        ->expectsOutput('Invalid JSON input: Syntax error');

    Http::assertNothingSent();
});

it('shows error when both --field and --input are provided', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name=Test'],
        '--input' => '{"name":"Test"}',
    ])
        ->assertFailed()
        ->expectsOutput('Cannot use both --field and --input options. Use --input for JSON data or --field for form fields, not both.');

    Http::assertNothingSent();
});

it('works with query parameters', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{"name":"Test"}',
        '--query' => 'source=cli&version=1',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?source=cli&version=1'
            && $request->data() === ['name' => 'Test'];
    });
});

it('validates JSON syntax for malformed arrays', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{"tags":["php","laravel"',  // Missing closing brackets
    ])
        ->assertFailed()
        ->expectsOutputToContain('Invalid JSON input:');

    Http::assertNothingSent();
});

it('handles empty JSON objects', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, force: true);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--input' => '{}',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->method() === 'POST'
            && $request->data() === [];
    });
});
