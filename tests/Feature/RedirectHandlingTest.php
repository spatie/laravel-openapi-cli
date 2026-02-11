<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-redirects-'.uniqid().'.yaml';

    $spec = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'Test API',
            'version' => '1.0.0',
        ],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/projects' => [
                'get' => [
                    'summary' => 'List projects',
                ],
            ],
        ],
    ];

    file_put_contents($this->specPath, Yaml::dump($spec, 10, 2));

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('does not follow redirects by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            null,
            302,
            ['Location' => 'https://api.example.com/v2/projects']
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('HTTP/1.1 302');
});

it('follows redirects when followRedirects is enabled', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['data' => 'redirected']),
            200
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->followRedirects();

    $this->artisan('test-api:get-projects')
        ->assertSuccessful()
        ->expectsOutputToContain('redirected');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects';
    });
});

it('passes allow_redirects false to Guzzle by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response(
            json_encode(['data' => 'ok']),
            200
        ),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:get-projects')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects';
    });
});
