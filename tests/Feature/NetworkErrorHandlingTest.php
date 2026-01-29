<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-network-errors-'.uniqid().'.yaml';

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
      responses:
        '200':
          description: Success
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
      responses:
        '201':
          description: Created
YAML;

    file_put_contents($this->specPath, $spec);
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }
});

it('shows network error message when connection fails', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api projects')
        ->assertFailed()
        ->expectsOutputToContain('Network error: Could not connect to')
        ->expectsOutputToContain('Connection refused');
});

it('shows network error with underlying exception message', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 7: Failed to connect to host');
    });

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api projects')
        ->assertFailed()
        ->expectsOutputToContain('Network error: Could not connect to')
        ->expectsOutputToContain('cURL error 7: Failed to connect to host');
});

it('shows network error for POST requests with data', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api projects --field name=Test')
        ->assertFailed()
        ->expectsOutputToContain('Network error: Could not connect to')
        ->expectsOutputToContain('Connection timed out');
});

it('exits with non-zero code on network errors', function () {
    Http::fake(function () {
        throw new ConnectionException('Network unreachable');
    });

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api projects')
        ->assertFailed();
});
