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
      parameters:
        - name: projectId
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Success
    delete:
      summary: Delete a project
      parameters:
        - name: projectId
          in: path
          required: true
          schema:
            type: integer
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

it('lists all endpoints with GET methods', function () {
    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('GET');
});

it('displays summary field when available', function () {
    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('List all projects');
});

it('displays description field when summary is not available', function () {
    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Retrieve all users from the system');
});

it('includes multiple HTTP methods for the same endpoint', function () {
    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('GET')
        ->expectsOutputToContain('POST')
        ->expectsOutputToContain('DELETE');
});

it('displays command names in list output', function () {
    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('test-api:post-projects')
        ->expectsOutputToContain('test-api:delete-projects');
});

it('shows disambiguated command names for colliding paths', function () {
    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('test-api:get-projects-project-id');
});
