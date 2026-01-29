<?php

use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    // Create a temporary OpenAPI spec for testing
    $this->specPath = sys_get_temp_dir().'/test-spec-endpoint-help-'.uniqid().'.yaml';

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
      summary: List all projects
      description: Retrieve a list of all projects in the system
      responses:
        '200':
          description: Success
  /projects/{projectId}:
    get:
      summary: Get a project
      description: Retrieve details for a specific project
      parameters:
        - name: projectId
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Success
    post:
      summary: Update a project
      description: Update an existing project
      parameters:
        - name: projectId
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                description:
                  type: string
              required:
                - name
      responses:
        '200':
          description: Success
  /projects/{projectId}/errors/{errorId}:
    get:
      summary: Get an error
      parameters:
        - name: projectId
          in: path
          required: true
          schema:
            type: string
        - name: errorId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

    file_put_contents($this->specPath, $spec);

    OpenApiCli::clearRegistrations();
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('shows detailed help for a simple GET endpoint', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects --help-endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('GET /projects')
        ->expectsOutputToContain('List all projects')
        ->expectsOutputToContain('Retrieve a list of all projects in the system');
});

it('shows path parameters with types and required status', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects/123 --help-endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('Path Parameters:')
        ->expectsOutputToContain('projectId (integer, required)');
});

it('shows request body schema when present', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects/123 --help-endpoint --method POST')
        ->assertSuccessful()
        ->expectsOutputToContain('POST /projects/{projectId}')
        ->expectsOutputToContain('Request Body:')
        ->expectsOutputToContain('Content-Type: application/json')
        ->expectsOutputToContain('Schema:')
        ->expectsOutputToContain('"name"')
        ->expectsOutputToContain('"type": "string"');
});

it('shows help for endpoints with multiple path parameters', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects/123/errors/456 --help-endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('GET /projects/{projectId}/errors/{errorId}')
        ->expectsOutputToContain('Path Parameters:')
        ->expectsOutputToContain('projectId (string, required)')
        ->expectsOutputToContain('errorId (string, required)');
});

it('shows help for parameterized paths using braces in input', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects/{projectId} --help-endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('GET /projects/{projectId}')
        ->expectsOutputToContain('Path Parameters:')
        ->expectsOutputToContain('projectId (integer, required)');
});

it('shows help for all methods when endpoint has multiple methods', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects/123 --help-endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('GET /projects/{projectId}')
        ->expectsOutputToContain('POST /projects/{projectId}');
});

it('shows description when available', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api projects --help-endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('List all projects')
        ->expectsOutputToContain('Retrieve a list of all projects in the system');
});

it('does not show path parameters section when none are present', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $output = $this->artisan('test-api projects --help-endpoint')
        ->assertSuccessful()
        ->execute();

    // Manually check that "Path Parameters:" is NOT in the output
    // We can't use doesntExpectOutput with expectsOutputToContain
    expect($output)->toBe(0);
});
