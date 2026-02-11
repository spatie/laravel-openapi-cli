<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/projects' => [
                'get' => [
                    'summary' => 'List all projects',
                ],
            ],
            '/projects/{id}' => [
                'get' => [
                    'summary' => 'Get a project',
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
            '/users/{userId}/posts' => [
                'get' => [
                    'summary' => 'List user posts',
                    'parameters' => [
                        [
                            'name' => 'userId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'post' => [
                    'summary' => 'Create a post',
                    'parameters' => [
                        [
                            'name' => 'userId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test_spec_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('executes GET request to simple endpoint', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"data":["project1","project2"]}', 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $expected = <<<'JSON'
{
    "data": [
        "project1",
        "project2"
    ]
}
JSON;

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutput($expected);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->method() === 'GET';
    });
});

it('executes GET request with path parameters via options', function () {
    Http::fake([
        'https://api.example.com/projects/123' => Http::response('{"id":123,"name":"Test Project"}', 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $expected = <<<'JSON'
{
    "id": 123,
    "name": "Test Project"
}
JSON;

    $this->artisan('test-api:get-projects-id', ['--id' => '123', '--json' => true])
        ->assertSuccessful()
        ->expectsOutput($expected);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects/123'
            && $request->method() === 'GET';
    });
});

it('executes GET request with camelCase path parameter', function () {
    Http::fake([
        'https://api.example.com/users/456/posts' => Http::response('{"posts":["post1","post2"]}', 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-users-posts', ['--user-id' => '456'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/456/posts'
            && $request->method() === 'GET';
    });
});

it('applies bearer authentication', function () {
    Http::fake([
        'api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->bearer('test-token-123');

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-token-123');
    });
});

it('uses configured base URL override', function () {
    Http::fake([
        'staging.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://staging.example.com');

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://staging.example.com/projects';
    });
});

it('executes POST to endpoint', function () {
    Http::fake([
        'api.example.com/users/456/posts' => Http::response(['success' => true], 201),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-users-posts', ['--user-id' => '456'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/456/posts'
            && $request->method() === 'POST';
    });
});
