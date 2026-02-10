<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specPath = sys_get_temp_dir().'/test-spec-path-params-'.uniqid().'.yaml';

    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /projects/{project_id}:
    get:
      summary: Get a project
      parameters:
        - name: project_id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Success
  /teams/{team_id}/users/{user_id}:
    delete:
      summary: Remove user from team
      parameters:
        - name: team_id
          in: path
          required: true
          schema:
            type: integer
        - name: user_id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '204':
          description: No Content
YAML;

    file_put_contents($this->specPath, $spec);

    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'success'], 200),
    ]);
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }

    \Spatie\OpenApiCli\OpenApiCli::clearRegistrations();
});

it('succeeds when required path parameter is provided', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:get-projects', ['--project-id' => '123'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/projects/123');
    });
});

it('fails when required path parameter is missing', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:get-projects')
        ->assertFailed()
        ->expectsOutputToContain('--project-id');

    Http::assertNothingSent();
});

it('succeeds when all required path parameters are provided for multi-param endpoint', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:delete-teams-users', ['--team-id' => '1', '--user-id' => '2'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/teams/1/users/2');
    });
});

it('fails when one of multiple required path parameters is missing', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:delete-teams-users', ['--team-id' => '1'])
        ->assertFailed()
        ->expectsOutputToContain('--user-id');

    Http::assertNothingSent();
});

it('substitutes path parameters into the URL correctly', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com');

    $this->artisan('test-api:delete-teams-users', ['--team-id' => '42', '--user-id' => '99'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/teams/42/users/99';
    });
});
