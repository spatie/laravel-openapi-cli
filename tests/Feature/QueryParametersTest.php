<?php

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
                    'summary' => 'List projects',
                    'parameters' => [
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ],
                ],
                'post' => [
                    'summary' => 'Create project',
                    'parameters' => [
                        ['name' => 'notify', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                    ],
                ],
            ],
            '/projects/{id}' => [
                'get' => [
                    'summary' => 'Get project details',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'include', 'in' => 'query', 'schema' => ['type' => 'string']],
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
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('appends single query parameter via option', function () {
    Http::fake([
        'https://api.example.com/projects?status=active' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', [
        '--status' => 'active',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects?status=active'
            && $request->method() === 'GET';
    });
});

it('appends multiple query parameters via options', function () {
    Http::fake([
        'https://api.example.com/projects*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', [
        '--status' => 'active',
        '--limit' => '10',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'status=active')
            && str_contains($request->url(), 'limit=10')
            && $request->method() === 'GET';
    });
});

it('works without query parameters', function () {
    Http::fake([
        'https://api.example.com/users' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-users')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/users'
            && $request->method() === 'GET';
    });
});

it('combines query parameters with path parameters', function () {
    Http::fake([
        'https://api.example.com/projects/123*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects-id', [
        '--id' => '123',
        '--include' => 'members',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://api.example.com/projects/123')
            && str_contains($request->url(), 'include=members')
            && $request->method() === 'GET';
    });
});

it('maps bracket params with operators to clean option names and encodes original param in URL', function () {
    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/aggregations' => [
                'get' => [
                    'summary' => 'List aggregations',
                    'parameters' => [
                        ['name' => 'filter[p95:>=]', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'filter[error_rate:>=]', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                ],
            ],
        ],
    ];

    $specPath = sys_get_temp_dir().'/test_spec_operator_'.uniqid().'.json';
    file_put_contents($specPath, json_encode($spec));

    Http::fake([
        'https://api.example.com/aggregations*' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($specPath, 'test-api');

    $this->artisan('test-api:get-aggregations', [
        '--filter-p95' => '500',
        '--filter-error-rate' => '10',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, 'filter%5Bp95%3A%3E%3D%5D=500')
            && str_contains($url, 'filter%5Berror_rate%3A%3E%3D%5D=10');
    });

    unlink($specPath);
});

it('works with POST requests and query parameters', function () {
    Http::fake([
        'https://api.example.com/projects*' => Http::response(['data' => 'created'], 201),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:post-projects', [
        '--notify' => 'true',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'notify=true')
            && $request->method() === 'POST';
    });
});
