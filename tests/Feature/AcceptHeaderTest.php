<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('sends Accept header derived from spec response content types', function () {
    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [['url' => 'https://api.example.com']],
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
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test_spec_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    Http::fake([
        'api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Accept', 'application/json');
    });
});

it('does not force Accept header when spec has no response content types', function () {
    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [['url' => 'https://api.example.com']],
        'paths' => [
            '/projects/{id}' => [
                'delete' => [
                    'summary' => 'Delete a project',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'responses' => [
                        '204' => [
                            'description' => 'No content',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test_spec_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    Http::fake([
        'api.example.com/projects/1' => Http::response(null, 204),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:delete-projects', ['--id' => '1'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return ! $request->hasHeader('Accept', 'application/json');
    });
});

it('joins multiple response content types in Accept header', function () {
    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [['url' => 'https://api.example.com']],
        'paths' => [
            '/reports' => [
                'get' => [
                    'summary' => 'Get reports',
                    'responses' => [
                        '200' => [
                            'description' => 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'object'],
                                ],
                                'text/csv' => [
                                    'schema' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test_spec_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    Http::fake([
        'api.example.com/reports' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-reports')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Accept', 'application/json, text/csv');
    });
});
