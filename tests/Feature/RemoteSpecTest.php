<?php

namespace Spatie\OpenApiCli\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

$specJson = json_encode([
    'openapi' => '3.0.0',
    'info' => ['title' => 'Remote Test API', 'version' => '1.0.0'],
    'servers' => [
        ['url' => 'https://api.example.com'],
    ],
    'paths' => [
        '/projects' => [
            'get' => [
                'summary' => 'List all projects',
            ],
        ],
    ],
]);

$specYaml = <<<'YAML'
openapi: 3.0.0
info:
  title: Remote Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /projects:
    get:
      summary: List all projects
YAML;

beforeEach(function () {
    OpenApiCli::clearRegistrations();
    Cache::flush();
});

it('registers and executes commands from a remote YAML spec', function () use ($specYaml) {
    Http::fake([
        'https://specs.example.com/api.yaml' => Http::response($specYaml, 200, [
            'Content-Type' => 'text/yaml',
        ]),
        'https://api.example.com/projects' => Http::response(['data' => ['project1']], 200),
    ]);

    OpenApiCli::register('https://specs.example.com/api.yaml', 'remote-api');

    $this->artisan('remote-api:get-projects')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects';
    });
});

it('registers and executes commands from a remote JSON spec', function () use ($specJson) {
    Http::fake([
        'https://specs.example.com/api.json' => Http::response($specJson, 200, [
            'Content-Type' => 'application/json',
        ]),
        'https://api.example.com/projects' => Http::response(['data' => ['project1']], 200),
    ]);

    OpenApiCli::register('https://specs.example.com/api.json', 'remote-api');

    $this->artisan('remote-api:get-projects')
        ->assertSuccessful();
});

it('caches remote spec and avoids duplicate HTTP fetches when cache is enabled', function () use ($specYaml) {
    Http::fake([
        'https://specs.example.com/api.yaml' => Http::response($specYaml, 200),
        'https://api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register('https://specs.example.com/api.yaml', 'remote-api')
        ->cache();

    // Run the command twice - spec should only be fetched once per unique resolve call
    $this->artisan('remote-api:get-projects')
        ->assertSuccessful();

    // Count how many times the spec URL was fetched
    $specFetches = 0;
    Http::assertSent(function ($request) use (&$specFetches) {
        if ($request->url() === 'https://specs.example.com/api.yaml') {
            $specFetches++;
        }

        return true;
    });

    // Spec should be fetched once (first resolve), then cached for subsequent resolves
    expect($specFetches)->toBe(1);
});

it('re-fetches on every resolve when cache is not enabled', function () use ($specYaml) {
    Http::fake([
        'https://specs.example.com/api.yaml' => Http::response($specYaml, 200),
        'https://api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register('https://specs.example.com/api.yaml', 'remote-api');

    $this->artisan('remote-api:get-projects')
        ->assertSuccessful();

    // Without cache(), every resolve call fetches
    $specFetches = 0;
    Http::assertSent(function ($request) use (&$specFetches) {
        if ($request->url() === 'https://specs.example.com/api.yaml') {
            $specFetches++;
        }

        return true;
    });

    expect($specFetches)->toBeGreaterThanOrEqual(2);
});

it('lists commands from a remote spec', function () use ($specYaml) {
    Http::fake([
        'https://specs.example.com/api.yaml' => Http::response($specYaml, 200),
    ]);

    OpenApiCli::register('https://specs.example.com/api.yaml', 'remote-api');

    $this->artisan('remote-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('remote-api:get-projects');
});

it('supports custom cache TTL for remote specs', function () use ($specYaml) {
    Http::fake([
        'https://specs.example.com/api.yaml' => Http::response($specYaml, 200),
        'https://api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register('https://specs.example.com/api.yaml', 'remote-api')
        ->cache(ttl: 600);

    $this->artisan('remote-api:get-projects')
        ->assertSuccessful();
});

it('detects JSON content type for extensionless URLs', function () use ($specJson) {
    Http::fake([
        'https://specs.example.com/openapi' => Http::response($specJson, 200, [
            'Content-Type' => 'application/json',
        ]),
        'https://api.example.com/projects' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register('https://specs.example.com/openapi', 'remote-api');

    $this->artisan('remote-api:get-projects')
        ->assertSuccessful();
});
