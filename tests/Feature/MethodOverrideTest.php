<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    // Create a minimal OpenAPI spec with multiple HTTP methods
    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/users' => [
                'get' => [
                    'summary' => 'List all users',
                    'operationId' => 'listUsers',
                ],
                'post' => [
                    'summary' => 'Create a user',
                    'operationId' => 'createUser',
                ],
            ],
            '/users/{id}' => [
                'get' => [
                    'summary' => 'Get a user',
                    'operationId' => 'getUser',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'put' => [
                    'summary' => 'Update a user',
                    'operationId' => 'updateUser',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'patch' => [
                    'summary' => 'Partially update a user',
                    'operationId' => 'patchUser',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'delete' => [
                    'summary' => 'Delete a user',
                    'operationId' => 'deleteUser',
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

    $this->specPath = sys_get_temp_dir().'/test_spec_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('executes GET request with explicit --method GET', function () {
    Http::fake([
        'api.example.com/users' => Http::response(['users' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users', '--method' => 'GET'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users'
            && $request->method() === 'GET';
    });
});

it('executes POST request with explicit --method POST', function () {
    Http::fake([
        'api.example.com/users' => Http::response(['id' => 1], 201),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users', '--method' => 'POST'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users'
            && $request->method() === 'POST';
    });
});

it('executes PUT request with explicit --method PUT', function () {
    Http::fake([
        'api.example.com/users/123' => Http::response(['id' => 123], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users/123', '--method' => 'PUT'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/123'
            && $request->method() === 'PUT';
    });
});

it('executes PATCH request with explicit --method PATCH', function () {
    Http::fake([
        'api.example.com/users/123' => Http::response(['id' => 123], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users/123', '--method' => 'PATCH'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/123'
            && $request->method() === 'PATCH';
    });
});

it('executes DELETE request with explicit --method DELETE', function () {
    Http::fake([
        'api.example.com/users/123' => Http::response(null, 204),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users/123', '--method' => 'DELETE'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users/123'
            && $request->method() === 'DELETE';
    });
});

it('accepts case insensitive method names - lowercase', function () {
    Http::fake([
        'api.example.com/users/123' => Http::response(null, 204),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users/123', '--method' => 'delete'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE';
    });
});

it('accepts case insensitive method names - mixed case', function () {
    Http::fake([
        'api.example.com/users/123' => Http::response(['id' => 123], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users/123', '--method' => 'PaTcH'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH';
    });
});

it('shows error when method not defined for path in spec', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users', '--method' => 'DELETE'])
        ->assertFailed()
        ->expectsOutput('Method DELETE is not allowed for this endpoint.')
        ->expectsOutput('Available methods: GET, POST');

    Http::assertNothingSent();
});

it('validates PUT method not allowed for /users endpoint', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users', '--method' => 'PUT'])
        ->assertFailed()
        ->expectsOutput('Method PUT is not allowed for this endpoint.')
        ->expectsOutput('Available methods: GET, POST');

    Http::assertNothingSent();
});

it('validates PATCH method not allowed for /users endpoint', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users', '--method' => 'PATCH'])
        ->assertFailed()
        ->expectsOutput('Method PATCH is not allowed for this endpoint.')
        ->expectsOutput('Available methods: GET, POST');

    Http::assertNothingSent();
});

it('validates POST method not allowed for /users/{id} endpoint', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', ['endpoint' => 'users/123', '--method' => 'POST'])
        ->assertFailed()
        ->expectsOutput('Method POST is not allowed for this endpoint.')
        ->expectsOutput('Available methods: GET, PUT, PATCH, DELETE');

    Http::assertNothingSent();
});
