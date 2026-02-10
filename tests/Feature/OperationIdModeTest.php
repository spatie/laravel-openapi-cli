<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-opid-'.uniqid().'.yaml';

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
    post:
      operationId: createProject
      summary: Create a project
      responses:
        '201':
          description: Created
  /projects/{project_id}/errors:
    get:
      operationId: getProjectErrors
      summary: Get project errors
      parameters:
        - name: project_id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Success
  /users:
    get:
      summary: List users
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

    \Spatie\OpenApiCli\OpenApiCli::clearRegistrations();
});

it('registers commands using operationId when useOperationIds is enabled', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:list-projects')
        ->assertSuccessful();
});

it('uses operationId-based names for commands with path parameters', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:get-project-errors', ['--project-id' => '123'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/projects/123/errors');
    });
});

it('falls back to path-based naming when operationId is not set', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com');

    // /users GET has no operationId, so it falls back to path-based naming
    $this->artisan('test-api:get-users')
        ->assertSuccessful();
});

it('uses path-based naming by default when useOperationIds is not enabled', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'success'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    // Without useOperationIds(), should use path-based naming
    $this->artisan('test-api:get-projects')
        ->assertSuccessful();
});

it('list command shows operationId-based names when enabled', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->useOperationIds()
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('test-api:list-projects')
        ->expectsOutputToContain('test-api:create-project')
        ->expectsOutputToContain('test-api:get-project-errors');
});
