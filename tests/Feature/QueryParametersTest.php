<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    // Create temporary spec file with query parameter endpoints
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
                    'parameters' => [
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ],
                ],
            ],
            '/users' => [
                'get' => [
                    'summary' => 'List users',
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test_spec_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    // Clean up temporary spec file
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('appends single query parameter to URL', function () {
    Http::fake([
        'https://api.example.com/projects?status=active' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--query' => 'status=active',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?status=active'
            && $request->method() === 'GET';
    });
});

it('appends multiple query parameters to URL', function () {
    Http::fake([
        'https://api.example.com/projects?status=active&limit=10' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--query' => 'status=active&limit=10',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?status=active&limit=10'
            && $request->method() === 'GET';
    });
});

it('URL-encodes query parameter values', function () {
    Http::fake([
        'https://api.example.com/projects?name=Test+Project&status=in+progress' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--query' => 'name=Test Project&status=in progress',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?name=Test+Project&status=in+progress'
            && $request->method() === 'GET';
    });
});

it('URL-encodes special characters in query parameters', function () {
    Http::fake([
        'https://api.example.com/projects?filter=%3E100&tag=%23important' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--query' => 'filter=>100&tag=#important',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?filter=%3E100&tag=%23important'
            && $request->method() === 'GET';
    });
});

it('works without query parameters', function () {
    Http::fake([
        'https://api.example.com/users' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'users',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users'
            && $request->method() === 'GET';
    });
});

it('combines query parameters with path parameters', function () {
    // Update spec with path parameters
    $spec = json_decode(file_get_contents($this->specPath), true);
    $spec['paths']['/projects/{id}'] = [
        'get' => [
            'summary' => 'Get project details',
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ['name' => 'include', 'in' => 'query', 'schema' => ['type' => 'string']],
            ],
        ],
    ];
    file_put_contents($this->specPath, json_encode($spec));

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    Http::fake([
        'https://api.example.com/projects/123?include=members' => Http::response(['data' => 'success'], 200),
    ]);

    $this->artisan('test-api', [
        'endpoint' => 'projects/123',
        '--query' => 'include=members',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects/123?include=members'
            && $request->method() === 'GET';
    });
});

it('handles empty query parameter values', function () {
    Http::fake([
        'https://api.example.com/projects?status=' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--query' => 'status=',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?status='
            && $request->method() === 'GET';
    });
});

it('handles query parameters with numbers', function () {
    Http::fake([
        'https://api.example.com/projects?limit=50&offset=100' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--query' => 'limit=50&offset=100',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?limit=50&offset=100'
            && $request->method() === 'GET';
    });
});

it('works with POST requests and query parameters', function () {
    // Update spec to include POST endpoint
    $spec = json_decode(file_get_contents($this->specPath), true);
    $spec['paths']['/projects']['post'] = [
        'summary' => 'Create project',
        'parameters' => [
            ['name' => 'notify', 'in' => 'query', 'schema' => ['type' => 'boolean']],
        ],
    ];
    file_put_contents($this->specPath, json_encode($spec));

    OpenApiCli::register($this->specPath, 'test-api');
    $this->refreshServiceProvider();

    Http::fake([
        'https://api.example.com/projects?notify=true' => Http::response(['data' => 'created'], 201),
    ]);

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--method' => 'POST',
        '--query' => 'notify=true',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?notify=true'
            && $request->method() === 'POST';
    });
});
