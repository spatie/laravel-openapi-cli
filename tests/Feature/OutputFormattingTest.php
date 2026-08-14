<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

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
  /projects/{project_id}:
    delete:
      summary: Delete a project
      parameters:
        - in: path
          name: project_id
          required: true
          schema:
            type: integer
      responses:
        '204':
          description: Successful response
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

// Human-readable output (default)

it('outputs human-readable format by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects')
        ->expectsOutputToContain('| Name   | Project 1 |')
        ->expectsOutputToContain('| ID     | 123       |')
        ->expectsOutputToContain('| Active | Yes       |')
        ->assertSuccessful();
});

it('displays array of objects as table by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects')
        ->expectsOutputToContain('| ID | Name')
        ->expectsOutputToContain('| 1')
        ->expectsOutputToContain('| Project 2')
        ->assertSuccessful();
});

it('displays wrapper pattern with data and meta in human-readable format by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"data":[{"id":1,"name":"Foo"},{"id":2,"name":"Bar"}],"meta":{"total":2}}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects')
        ->expectsOutputToContain('# Data')
        ->expectsOutputToContain('| ID | Name')
        ->expectsOutputToContain('| Foo')
        ->expectsOutputToContain('# Meta')
        ->expectsOutputToContain('| Total | 2 |')
        ->assertSuccessful();
});

it('displays error responses in human-readable format by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"error":"Not Found","message":"Resource not found"}', 404),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects')
        ->expectsOutputToContain('| Error   | Not Found          |')
        ->expectsOutputToContain('| Message | Resource not found |')
        ->assertFailed();
});

it('combines human-readable output with --headers flag by default', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('HTTP/1.1 200 OK')
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('| Name | Project 1 |')
        ->assertSuccessful();
});

// --json flag tests

it('outputs pretty-printed JSON with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $expected = <<<'JSON'
{
    "name": "Project 1",
    "id": 123,
    "active": true
}
JSON;

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('pretty-prints nested JSON structures with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"project":{"name":"Test","metadata":{"created":"2024-01-01","tags":["api","test"]}}}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

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

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('pretty-prints JSON arrays with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

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

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('preserves unicode characters in JSON output with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Tëst Prøjéct","emoji":"🚀"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $expected = <<<'JSON'
{
    "name": "Tëst Prøjéct",
    "emoji": "🚀"
}
JSON;

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('does not escape forward slashes in JSON output with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"url":"https://example.com/path/to/resource"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutputToContain('"url": "https://example.com/path/to/resource"')
        ->assertSuccessful();
});

it('handles empty JSON objects with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput('[]')
        ->assertSuccessful();
});

it('handles empty JSON arrays with --json flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput('[]')
        ->assertSuccessful();
});

// --minify flag tests

it('minifies JSON output when --minify flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"name":"Project 1","id":123,"active":true}')
        ->assertSuccessful();
});

it('--minify implies --json output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    // --minify alone should produce JSON, not human-readable
    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"name":"Project 1","id":123,"active":true}')
        ->doesntExpectOutputToContain('Name: Project 1')
        ->assertSuccessful();
});

it('minifies nested JSON structures when --minify flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"project":{"name":"Test","metadata":{"created":"2024-01-01","tags":["api","test"]}}}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"project":{"name":"Test","metadata":{"created":"2024-01-01","tags":["api","test"]}}}')
        ->assertSuccessful();
});

it('minifies JSON arrays when --minify flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('[{"id":1,"name":"Project 1"},{"id":2,"name":"Project 2"}]')
        ->assertSuccessful();
});

it('outputs minified JSON on single line with no extra whitespace', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name": "Test",  "id":   123,   "active":  true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"name":"Test","id":123,"active":true}')
        ->assertSuccessful();
});

it('preserves unicode characters in minified JSON output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Tëst Prøjéct","emoji":"🚀"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"name":"Tëst Prøjéct","emoji":"🚀"}')
        ->assertSuccessful();
});

it('does not escape forward slashes in minified JSON output', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"url":"https://example.com/path/to/resource"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"url":"https://example.com/path/to/resource"}')
        ->assertSuccessful();
});

it('minifies empty JSON objects', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('[]')
        ->assertSuccessful();
});

it('minifies empty JSON arrays', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('[]', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('[]')
        ->assertSuccessful();
});

// Non-JSON response tests

it('suppresses HTML body for non-JSON responses by default', function () {
    Http::fake([
        'https://api.example.com/html-response' => Http::response('<html><body>Hello World</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-html-response')
        ->expectsOutputToContain('Use --output-html to see it.')
        ->doesntExpectOutputToContain('<html><body>Hello World</body></html>')
        ->assertSuccessful();
});

it('outputs raw body for non-JSON responses (plain text)', function () {
    Http::fake([
        'https://api.example.com/plain-text' => Http::response('This is plain text content', 200, ['Content-Type' => 'text/plain']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-plain-text')
        ->expectsOutputToContain('This is plain text content')
        ->assertSuccessful();
});

it('writes a binary body byte for byte so it can be redirected to a file', function () {
    // A real PNG header, plus bytes the console formatter would otherwise read
    // as style tags and swallow.
    $png = "\x89PNG\r\n\x1a\n".'<info>not a tag</info>'."\x00\xFF\x01";

    Http::fake([
        'https://api.example.com/plain-text' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->withoutMockingConsoleOutput();
    $this->registerOpenApiCommands();

    expect(Artisan::call('test-api:get-plain-text'))->toBe(0)
        ->and(Artisan::output())->toBe($png);
});

it('outputs raw body for invalid JSON', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"invalid": json content}', 200, ['Content-Type' => 'application/json']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects')
        ->expectsOutputToContain('{"invalid": json content}')
        ->assertSuccessful();
});

it('suppresses HTML body for non-JSON responses even with --minify flag', function () {
    Http::fake([
        'https://api.example.com/html-response' => Http::response('<html><body>Hello</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-html-response', ['--minify' => true])
        ->expectsOutputToContain('Use --output-html to see it.')
        ->doesntExpectOutputToContain('<html><body>Hello</body></html>')
        ->assertSuccessful();
});

// --headers flag tests

it('shows HTTP status line when --headers flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1"}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('HTTP/1.1 200 OK')
        ->assertSuccessful();
});

it('shows response headers when --headers flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1"}', 200, [
            'Content-Type' => 'application/json',
            'X-Custom-Header' => 'custom-value',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('X-Custom-Header: custom-value')
        ->assertSuccessful();
});

it('separates headers from body with blank line when --headers flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1"}', 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('| Name | Project 1 |')
        ->assertSuccessful();
});

it('shows headers before body when --headers flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"id":123}', 200, [
            'X-Request-ID' => 'abc-123',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('X-Request-ID: abc-123')
        ->expectsOutputToContain('| ID | 123 |')
        ->assertSuccessful();
});

it('shows headers with different status codes when --headers flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"error":"Not Found"}', 404),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true])
        ->expectsOutputToContain('HTTP/1.1 404 Not Found')
        ->assertFailed();
});

it('combines --headers and --minify flags correctly', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true, '--minify' => true])
        ->expectsOutputToContain('HTTP/1.1 200 OK')
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('{"name":"Project 1","id":123}')
        ->assertSuccessful();
});

it('combines --headers and --json flags correctly', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--headers' => true, '--json' => true])
        ->expectsOutputToContain('HTTP/1.1 200 OK')
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('"name": "Project 1"')
        ->assertSuccessful();
});

// 204 No Content tests

it('shows spec response description for 204 No Content', function () {
    Http::fake([
        'https://api.example.com/projects/*' => Http::response('', 204),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:delete-projects', ['--project-id' => 1])
        ->expectsOutput('Successful response (204)')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects/1';
    });
});

it('shows 204 status in headers when --headers flag is provided', function () {
    Http::fake([
        'https://api.example.com/projects/*' => Http::response('', 204),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:delete-projects', ['--headers' => true, '--project-id' => 1])
        ->expectsOutputToContain('HTTP/1.1 204 No Content')
        ->expectsOutputToContain('Successful response (204)')
        ->assertSuccessful();
});

it('falls back to generic message for 204 without spec description', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('', 204),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects')
        ->expectsOutput('No content (204)')
        ->assertSuccessful();
});

// --output-html flag tests

it('shows HTML body when --output-html flag is passed', function () {
    Http::fake([
        'https://api.example.com/html-response' => Http::response('<html><body>Hello World</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-html-response', ['--output-html' => true])
        ->expectsOutputToContain('<html><body>Hello World</body></html>')
        ->doesntExpectOutputToContain('Use --output-html to see it.')
        ->assertSuccessful();
});

it('shows HTML body when showHtmlBody config is enabled', function () {
    Http::fake([
        'https://api.example.com/html-response' => Http::response('<html><body>Hello World</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->showHtmlBody();

    $this->artisan('test-api:get-html-response')
        ->expectsOutputToContain('<html><body>Hello World</body></html>')
        ->doesntExpectOutputToContain('Use --output-html to see it.')
        ->assertSuccessful();
});

it('still shows plain text non-JSON responses by default', function () {
    Http::fake([
        'https://api.example.com/plain-text' => Http::response('This is plain text content', 200, ['Content-Type' => 'text/plain']),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-plain-text')
        ->expectsOutputToContain('This is plain text content')
        ->doesntExpectOutputToContain('Use --output-html to see it.')
        ->assertSuccessful();
});

// jsonOutput() config tests

it('outputs pretty-printed JSON when jsonOutput() config is set', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->jsonOutput();

    $expected = <<<'JSON'
{
    "name": "Project 1",
    "id": 123,
    "active": true
}
JSON;

    $this->artisan('test-api:get-projects')
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('--json flag still works with jsonOutput() config', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->jsonOutput();

    $expected = <<<'JSON'
{
    "name": "Project 1",
    "id": 123
}
JSON;

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('--minify works with jsonOutput() config', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->jsonOutput();

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"name":"Project 1","id":123}')
        ->assertSuccessful();
});

// --yaml flag tests

it('outputs YAML format with --yaml flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123,"active":true}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--yaml' => true])
        ->expectsOutputToContain('name: \'Project 1\'')
        ->expectsOutputToContain('id: 123')
        ->expectsOutputToContain('active: true')
        ->assertSuccessful();
});

it('outputs nested YAML structures with --yaml flag', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"project":{"name":"Test","tags":["api","test"]}}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--yaml' => true])
        ->expectsOutputToContain('project:')
        ->expectsOutputToContain('  name: Test')
        ->expectsOutputToContain('  tags:')
        ->expectsOutputToContain('    - api')
        ->expectsOutputToContain('    - test')
        ->assertSuccessful();
});

it('combines --yaml and --headers flags correctly', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    OpenApiCli::register($this->specFile, 'test-api');

    $this->artisan('test-api:get-projects', ['--yaml' => true, '--headers' => true])
        ->expectsOutputToContain('HTTP/1.1 200 OK')
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('name: \'Project 1\'')
        ->expectsOutputToContain('id: 123')
        ->assertSuccessful();
});

it('outputs YAML by default when yamlOutput() config is set', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->yamlOutput();

    $this->artisan('test-api:get-projects')
        ->expectsOutputToContain('name: \'Project 1\'')
        ->expectsOutputToContain('id: 123')
        ->assertSuccessful();
});

it('--json flag overrides yamlOutput() config', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->yamlOutput();

    $expected = <<<'JSON'
{
    "name": "Project 1",
    "id": 123
}
JSON;

    $this->artisan('test-api:get-projects', ['--json' => true])
        ->expectsOutput($expected)
        ->assertSuccessful();
});

it('--minify flag overrides yamlOutput() config', function () {
    Http::fake([
        'https://api.example.com/projects' => Http::response('{"name":"Project 1","id":123}', 200),
    ]);

    OpenApiCli::register($this->specFile, 'test-api')
        ->yamlOutput();

    $this->artisan('test-api:get-projects', ['--minify' => true])
        ->expectsOutput('{"name":"Project 1","id":123}')
        ->assertSuccessful();
});
