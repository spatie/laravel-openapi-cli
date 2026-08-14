<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-exclude-'.uniqid().'.yaml';

    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /projects:
    get:
      operationId: listProjects
      summary: List all projects
      responses:
        '200':
          description: Success
  /downloads/{file}:
    get:
      operationId: downloadFile
      summary: Download a file
      parameters:
        - name: file
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

    file_put_contents($this->specPath, $spec);
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }

    OpenApiCli::clearRegistrations();
});

it('does not register a command for an operation excluded by name', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com')
        ->exclude('download-file');

    $this->registerOpenApiCommands();

    expect(array_keys(Artisan::all()))
        ->toContain('test-api:list-projects')
        ->not->toContain('test-api:download-file');
});

it('does not register a command for an operation excluded by callable', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com')
        ->exclude(fn (string $method, string $path) => str_starts_with($path, '/downloads'));

    $this->registerOpenApiCommands();

    expect(array_keys(Artisan::all()))
        ->toContain('test-api:list-projects')
        ->not->toContain('test-api:download-file');
});

it('accepts several exclusions at once', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com')
        ->exclude(['download-file', 'list-projects']);

    $this->registerOpenApiCommands();

    $commands = array_keys(Artisan::all());

    expect($commands)
        ->not->toContain('test-api:download-file')
        ->not->toContain('test-api:list-projects');
});

it('leaves the remaining commands working', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com')
        ->exclude('download-file');

    $this->registerOpenApiCommands();

    $this->artisan('test-api:list-projects')->assertSuccessful();
});

it('registers every operation when nothing is excluded', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com');

    $this->registerOpenApiCommands();

    expect(array_keys(Artisan::all()))
        ->toContain('test-api:list-projects')
        ->toContain('test-api:download-file');
});
