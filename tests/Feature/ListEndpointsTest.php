<?php

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
      summary: List all projects
      responses:
        '200':
          description: Success
    post:
      summary: Create a new project
      responses:
        '201':
          description: Created
  /projects/{projectId}:
    get:
      summary: Get a specific project
      responses:
        '200':
          description: Success
    delete:
      summary: Delete a project
      responses:
        '204':
          description: No Content
  /users:
    get:
      description: Retrieve all users from the system
      responses:
        '200':
          description: Success
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

it('lists all endpoints when --list flag is used', function () {
    // This test verifies that all endpoints are listed
    // Specific content checks are covered by other tests
    $this->artisan('test-api', ['--list' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('GET')
        ->expectsOutputToContain('/projects');
});

it('displays summary field when available', function () {
    $this->artisan('test-api', ['--list' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('List all projects');
});

it('displays description field when summary is not available', function () {
    $this->artisan('test-api', ['--list' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Retrieve all users from the system');
});

it('includes multiple HTTP methods for the same endpoint', function () {
    // Uses the test-api command which has GET and POST for /projects
    $this->artisan('test-api', ['--list' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('GET')
        ->expectsOutputToContain('POST')
        ->expectsOutputToContain('DELETE');
});

it('works with --list flag without requiring endpoint argument', function () {
    // The --list flag should work even if no endpoint is provided
    $this->artisan('test-api', ['--list' => true])
        ->assertSuccessful();
});
