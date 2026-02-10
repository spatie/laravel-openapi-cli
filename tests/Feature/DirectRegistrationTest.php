<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
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
                'post' => [
                    'summary' => 'Create a project',
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
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/test_spec_direct_'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('registers commands without namespace', function () {
    OpenApiCli::register($this->specPath);

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('get-projects')
        ->toContain('post-projects')
        ->toContain('get-projects-id');
});

it('does not register list command when no namespace is set', function () {
    OpenApiCli::register($this->specPath);

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    // The built-in 'list' command exists, but no OpenAPI-specific list command should be registered
    expect($commands)->not->toContain('openapi:list');
});

it('executes commands without namespace', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(['data' => ['project1']], 200),
    ]);

    OpenApiCli::register($this->specPath);

    $this->artisan('get-projects')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects'
            && $request->method() === 'GET';
    });
});

it('executes commands with path parameters without namespace', function () {
    Http::fake([
        'https://api.example.com/projects/42' => Http::response(['id' => 42], 200),
    ]);

    OpenApiCli::register($this->specPath);

    $this->artisan('get-projects-id', ['--id' => '42'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects/42'
            && $request->method() === 'GET';
    });
});

it('handles collision disambiguation without namespace', function () {
    OpenApiCli::register($this->specPath);

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    // /projects -> get-projects, /projects/{id} -> get-projects-id (disambiguated)
    expect($commands)->toContain('get-projects')
        ->toContain('get-projects-id');
});

it('can mix namespaced and non-namespaced registrations', function () {
    OpenApiCli::register($this->specPath);
    OpenApiCli::register($this->specPath, 'api');

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('get-projects')
        ->toContain('api:get-projects')
        ->toContain('api:list');
});

it('reports hasNamespace correctly', function () {
    $configWithout = OpenApiCli::register($this->specPath);
    expect($configWithout->hasNamespace())->toBeFalse()
        ->and($configWithout->getNamespace())->toBe('');

    OpenApiCli::clearRegistrations();

    $configWith = OpenApiCli::register($this->specPath, 'api');
    expect($configWith->hasNamespace())->toBeTrue()
        ->and($configWith->getNamespace())->toBe('api');
});
