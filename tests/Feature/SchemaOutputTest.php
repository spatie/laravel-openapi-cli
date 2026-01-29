<?php

use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    $this->specContent = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
  description: A test API for schema output
servers:
  - url: https://api.example.com
paths:
  /projects:
    get:
      summary: List all projects
      responses:
        '200':
          description: Success
    post:
      summary: Create a new project
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
      responses:
        '201':
          description: Created
  /users:
    get:
      summary: List all users
      responses:
        '200':
          description: Success
components:
  schemas:
    Project:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
    User:
      type: object
      properties:
        id:
          type: integer
        email:
          type: string
YAML;

    $this->specPath = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';
    file_put_contents($this->specPath, $this->specContent);

    OpenApiCli::register($this->specPath, 'test-api');
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }

    \Spatie\OpenApiCli\OpenApiCli::clearRegistrations();
});

it('outputs valid JSON when --schema flag is used', function () {
    // Test that the command runs successfully and outputs something
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('{');
});

it('includes openapi version in schema output', function () {
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('openapi');
});

it('includes paths from spec in schema output', function () {
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('paths');
});

it('includes components in schema output', function () {
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('components');
});

it('includes info section in schema output', function () {
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('info');
});

it('includes servers section in schema output', function () {
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('servers');
});

it('works with --schema flag without requiring endpoint argument', function () {
    $this->artisan('test-api', ['--schema' => true])
        ->assertSuccessful();
});

it('outputs minified JSON when --schema and --minify flags are both used', function () {
    $this->artisan('test-api', ['--schema' => true, '--minify' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('{"openapi"');
});
