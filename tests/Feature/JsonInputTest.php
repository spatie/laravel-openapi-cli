<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

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
                'put' => [
                    'summary' => 'Update user',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test-spec-'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (isset($this->specPath) && file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('sends JSON input with POST command', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');

    $this->artisan('test-api:post-projects', [
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

    $this->artisan('test-api:post-projects', [
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

    $this->artisan('test-api:post-projects', [
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

it('works with explicit PUT method command', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');

    $this->artisan('test-api:put-users', [
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

    $this->artisan('test-api:post-projects', [
        '--input' => '{"name":"Test"',
    ])
        ->assertFailed()
        ->expectsOutput('Invalid JSON input: Syntax error');

    Http::assertNothingSent();
});

it('shows error when both --field and --input are provided', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');

    $this->artisan('test-api:post-projects', [
        '--field' => ['name=Test'],
        '--input' => '{"name":"Test"}',
    ])
        ->assertFailed()
        ->expectsOutput('Cannot use both --field and --input options. Use --input for JSON data or --field for form fields, not both.');

    Http::assertNothingSent();
});

it('validates JSON syntax for malformed arrays', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');

    $this->artisan('test-api:post-projects', [
        '--input' => '{"tags":["php","laravel"',
    ])
        ->assertFailed()
        ->expectsOutputToContain('Invalid JSON input:');

    Http::assertNothingSent();
});

it('handles empty JSON objects', function () {
    OpenApiCli::register($this->specPath, 'test-api')->baseUrl('https://api.example.com');

    $this->artisan('test-api:post-projects', [
        '--input' => '{}',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->method() === 'POST'
            && $request->data() === [];
    });
});
