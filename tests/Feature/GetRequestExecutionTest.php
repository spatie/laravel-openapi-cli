<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    // Create a minimal OpenAPI spec for testing
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
                    'operationId' => 'listProjects',
                ],
            ],
            '/projects/{id}' => [
                'get' => [
                    'summary' => 'Get a project',
                    'operationId' => 'getProject',
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
                    'operationId' => 'listUserPosts',
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
                    'operationId' => 'createPost',
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

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('executes GET request to simple endpoint', function () {
    Http::fake([
        'api.example.com/projects' => Http::response(['data' => ['project1', 'project2']], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'projects'])
        ->assertSuccessful()
        ->expectsOutput('{"data":["project1","project2"]}');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->method() === 'GET';
    });
});

it('executes GET request with path parameters', function () {
    Http::fake([
        'api.example.com/projects/123' => Http::response(['id' => 123, 'name' => 'Test Project'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'projects/123'])
        ->assertSuccessful()
        ->expectsOutput('{"id":123,"name":"Test Project"}');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects/123'
            && $request->method() === 'GET';
    });
});

it('executes GET request with multiple path parameters', function () {
    Http::fake([
        'api.example.com/users/456/posts' => Http::response(['posts' => ['post1', 'post2']], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'users/456/posts'])
        ->assertSuccessful()
        ->expectsOutput('{"posts":["post1","post2"]}');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/456/posts'
            && $request->method() === 'GET';
    });
});

it('defaults to GET method when no method specified', function () {
    Http::fake([
        'api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'projects'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'GET';
    });
});

it('applies bearer authentication', function () {
    Http::fake([
        'api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->bearer('test-token-123');

    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'projects'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer test-token-123');
    });
});

it('shows error when endpoint not found', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'non-existent'])
        ->assertFailed()
        ->expectsOutput('Endpoint not found in OpenAPI spec.');

    Http::assertNothingSent();
});

it('shows error when method not allowed for endpoint', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'projects', '--method' => 'POST'])
        ->assertFailed()
        ->expectsOutput('Method POST is not allowed for this endpoint.')
        ->expectsOutput('Available methods: GET');

    Http::assertNothingSent();
});

it('shows error when no endpoint provided', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api')
        ->assertFailed()
        ->expectsOutput('Please provide an endpoint path.');

    Http::assertNothingSent();
});

it('uses configured base URL override', function () {
    Http::fake([
        'staging.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://staging.example.com');

    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'projects'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://staging.example.com/projects';
    });
});

it('executes explicit method with method option', function () {
    Http::fake([
        'api.example.com/users/456/posts' => Http::response(['success' => true], 201),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api', ['endpoint' => 'users/456/posts', '--method' => 'post'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/456/posts'
            && $request->method() === 'POST';
    });
});
