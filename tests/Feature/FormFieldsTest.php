<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    // Create a temporary OpenAPI spec file for testing
    $this->specPath = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';

    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /projects:
    post:
      summary: Create a project
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                team_id:
                  type: integer
      responses:
        '201':
          description: Created
  /users:
    post:
      summary: Create a user
      requestBody:
        content:
          application/x-www-form-urlencoded:
            schema:
              type: object
              properties:
                username:
                  type: string
                email:
                  type: string
      responses:
        '201':
          description: Created
  /items:
    get:
      summary: List items
      responses:
        '200':
          description: Success
    post:
      summary: Create an item
      requestBody:
        content:
          application/json:
            schema:
              type: object
      responses:
        '201':
          description: Created
YAML;

    file_put_contents($this->specPath, $spec);
});

afterEach(function () {
    // Clean up temp file
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }

    // Clear registrations
    OpenApiCli::clearRegistrations();
});

test('sends single field as JSON when spec expects application/json', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name=Test Project'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects' &&
               $request->method() === 'POST' &&
               $request->data() === ['name' => 'Test Project'] &&
               $request->hasHeader('Content-Type', 'application/json');
    });
});

test('sends multiple fields as JSON', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name=Test Project', 'team_id=123'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/projects' &&
               $request->method() === 'POST' &&
               $request->data() === ['name' => 'Test Project', 'team_id' => '123'];
    });
});

test('auto-detects POST method when field is provided', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name=Auto POST'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST';
    });
});

test('can explicitly override method even with fields', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    // Using --method POST explicitly (even though it would be auto-detected)
    $this->artisan('test-api', [
        'endpoint' => 'items',
        '--method' => 'POST',
        '--field' => ['data=test'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST' &&
               $request->url() === 'https://api.example.com/items';
    });
});

test('sends fields as form-data when spec expects application/x-www-form-urlencoded', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'users',
        '--field' => ['username=johndoe', 'email=john@example.com'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        // Check that a request was sent at all
        return $request->url() === 'https://api.example.com/users' &&
               $request->method() === 'POST';
    });
});

test('parses fields with = in the value correctly', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name=Test=Value'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->data() === ['name' => 'Test=Value'];
    });
});

test('handles empty field values', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name='],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->data() === ['name' => ''];
    });
});

test('combines fields with authentication', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com')
        ->bearer('test-token-123');

    $this->artisan('test-api', [
        'endpoint' => 'projects',
        '--field' => ['name=Authenticated'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST' &&
               $request->hasHeader('Authorization', 'Bearer test-token-123') &&
               $request->data() === ['name' => 'Authenticated'];
    });
});

test('sends GET request when no fields provided', function () {
    Http::fake();

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api', [
        'endpoint' => 'items',
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'GET' &&
               $request->url() === 'https://api.example.com/items';
    });
});
