<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    // Create a minimal OpenAPI spec for testing
    $this->specContent = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /projects:
    get:
      summary: List projects
      responses:
        '200':
          description: Success
  /html-response:
    get:
      summary: Get HTML response
      responses:
        '200':
          description: Success
  /plain-text:
    get:
      summary: Get plain text
      responses:
        '200':
          description: Success
YAML;

    $this->specFile = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';
    file_put_contents($this->specFile, $this->specContent);
});

afterEach(function () {
    if (file_exists($this->specFile)) {
        unlink($this->specFile);
    }
    OpenApiCli::clearRegistrations();
});

it('pretty-prints JSON responses by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $expected = <<<'JSON'
{
    "name": "Project 1",
    "id": 123,
    "active": true
}
JSON;

    $this->artisan('test-api projects')
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('pretty-prints nested JSON structures', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"project":{"name":"Test","metadata":{"created":"2024-01-01","tags":["api","test"]}}}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $expected = <<<'JSON'
{
    "project": {
        "name": "Test",
        "metadata": {
            "created": "2024-01-01",
            "tags": [
                "api",
                "test"
            ]
        }
    }
}
JSON;

    $this->artisan('test-api projects')
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('pretty-prints JSON arrays', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $expected = <<<'JSON'
[
    {
        "id": 1,
        "name": "Project 1"
    },
    {
        "id": 2,
        "name": "Project 2"
    }
]
JSON;

    $this->artisan('test-api projects')
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('outputs raw body for non-JSON responses (HTML)', function () {
    Http::fake([
        'https://api.example.com/html-response' => Http::response('<html><body>Hello World</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api html-response')
        ->expectsOutput('<html><body>Hello World</body></html>')
        ->assertSuccessful();
});

it('outputs raw body for non-JSON responses (plain text)', function () {
    Http::fake([
        'https://api.example.com/plain-text' => Http::response('This is plain text content', 200, ['Content-Type' => 'text/plain']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api plain-text')
        ->expectsOutput('This is plain text content')
        ->assertSuccessful();
});

it('outputs raw body for invalid JSON', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"invalid": json content}', 200, ['Content-Type' => 'application/json']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects')
        ->expectsOutput('{"invalid": json content}')
        ->assertSuccessful();
});

it('handles empty JSON objects', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects')
        ->expectsOutput('[]')
        ->assertSuccessful();
});

it('handles empty JSON arrays', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects')
        ->expectsOutput('[]')
        ->assertSuccessful();
});

it('preserves unicode characters in JSON output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Tëst Prøjéct","emoji":"🚀"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $expected = <<<'JSON'
{
    "name": "Tëst Prøjéct",
    "emoji": "🚀"
}
JSON;

    $this->artisan('test-api projects')
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('does not escape forward slashes in JSON output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"url":"https://example.com/path/to/resource"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects')
        ->expectsOutputToContain('"url": "https://example.com/path/to/resource"')
        ->assertSuccessful();

    // Verify slashes are NOT escaped
    Http::assertSent(function ($request) {
        return true; // Just need to trigger the command to check output
    });
});

// --minify flag tests

it('minifies JSON output when --minify flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('{"name":"Project 1","id":123,"active":true}')
        ->assertSuccessful();
});

it('minifies nested JSON structures when --minify flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"project":{"name":"Test","metadata":{"created":"2024-01-01","tags":["api","test"]}}}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('{"project":{"name":"Test","metadata":{"created":"2024-01-01","tags":["api","test"]}}}')
        ->assertSuccessful();
});

it('minifies JSON arrays when --minify flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]')
        ->assertSuccessful();
});

it('outputs minified JSON on single line with no extra whitespace', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name": "Test",  "id":   123,   "active":  true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('{"name":"Test","id":123,"active":true}')
        ->assertSuccessful();
});

it('preserves unicode characters in minified JSON output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Tëst Prøjéct","emoji":"🚀"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('{"name":"Tëst Prøjéct","emoji":"🚀"}')
        ->assertSuccessful();
});

it('does not escape forward slashes in minified JSON output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"url":"https://example.com/path/to/resource"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('{"url":"https://example.com/path/to/resource"}')
        ->assertSuccessful();
});

it('minifies empty JSON objects', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('[]')
        ->assertSuccessful();
});

it('minifies empty JSON arrays', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api projects --minify')
        ->expectsOutput('[]')
        ->assertSuccessful();
});

it('outputs raw body for non-JSON responses even with --minify flag', function () {
    Http::fake([
        'https://api.example.com/html-response' => Http::response('<html><body>Hello</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');
    $this->app->register(\Spatie\OpenApiCli\OpenApiCliServiceProvider::class, true);

    $this->artisan('test-api html-response --minify')
        ->expectsOutput('<html><body>Hello</body></html>')
        ->assertSuccessful();
});
