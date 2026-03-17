<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specFile = sys_get_temp_dir().'/test-path-level-params-'.uniqid().'.yaml';
});

afterEach(function () {
    if (file_exists($this->specFile)) {
        unlink($this->specFile);
    }

    Spatie\OpenApiCli\OpenApiCli::clearRegistrations();
});

it('merges path-level parameters into operation parameters', function () {
    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /companies/{company_id}/items:
    parameters:
      - name: company_id
        in: path
        required: true
        description: The company ID
        schema:
          type: integer
    get:
      operationId: listItems
      summary: List items
      parameters:
        - name: page
          in: query
          schema:
            type: integer
      responses:
        '200':
          description: Success
YAML;

    file_put_contents($this->specFile, $spec);

    Http::fake([
        'api.example.com/*' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specFile, 'test')
        ->baseUrl('https://api.example.com')
        ->useOperationIds();

    $this->artisan('test:list-items', ['--company-id' => '123', '--page' => '1'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/companies/123/items');
    });
});

it('resolves $ref path-level parameters from components', function () {
    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
components:
  parameters:
    company_id:
      name: company_id
      in: path
      required: true
      description: The company ID
      schema:
        type: integer
paths:
  /companies/{company_id}/info:
    parameters:
      - $ref: '#/components/parameters/company_id'
    get:
      operationId: getCompanyInfo
      summary: Get company info
      responses:
        '200':
          description: Success
YAML;

    file_put_contents($this->specFile, $spec);

    Http::fake([
        'api.example.com/*' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specFile, 'test')
        ->baseUrl('https://api.example.com')
        ->useOperationIds();

    $this->artisan('test:get-company-info', ['--company-id' => '456'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/companies/456/info');
    });
});

it('does not duplicate parameters already defined at operation level', function () {
    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /companies/{company_id}/items:
    parameters:
      - name: company_id
        in: path
        required: true
        description: Path-level description
        schema:
          type: integer
    get:
      operationId: listItems
      summary: List items
      parameters:
        - name: company_id
          in: path
          required: true
          description: Operation-level description
          schema:
            type: integer
      responses:
        '200':
          description: Success
YAML;

    file_put_contents($this->specFile, $spec);

    Http::fake([
        'api.example.com/*' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specFile, 'test')
        ->baseUrl('https://api.example.com')
        ->useOperationIds();

    // Should work with a single --company-id flag (not duplicated)
    $this->artisan('test:list-items', ['--company-id' => '123'])
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/companies/123/items');
    });
});

it('merges path-level parameters across multiple operations on the same path', function () {
    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /companies/{company_id}/items:
    parameters:
      - name: company_id
        in: path
        required: true
        schema:
          type: integer
    get:
      operationId: listItems
      summary: List items
      responses:
        '200':
          description: Success
    post:
      operationId: createItem
      summary: Create item
      responses:
        '201':
          description: Created
YAML;

    file_put_contents($this->specFile, $spec);

    Http::fake([
        'api.example.com/*' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specFile, 'test')
        ->baseUrl('https://api.example.com')
        ->useOperationIds();

    // Both GET and POST should have --company-id
    $this->artisan('test:list-items', ['--company-id' => '10'])
        ->assertSuccessful();

    $this->artisan('test:create-item', ['--company-id' => '10'])
        ->assertSuccessful();
});

it('fails when required path-level parameter is missing', function () {
    $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /companies/{company_id}/items:
    parameters:
      - name: company_id
        in: path
        required: true
        schema:
          type: integer
    get:
      operationId: listItems
      summary: List items
      responses:
        '200':
          description: Success
YAML;

    file_put_contents($this->specFile, $spec);

    Http::fake([
        'api.example.com/*' => Http::response(['data' => []], 200),
    ]);

    OpenApiCli::register($this->specFile, 'test')
        ->baseUrl('https://api.example.com')
        ->useOperationIds();

    $this->artisan('test:list-items')
        ->assertFailed()
        ->expectsOutputToContain('--company-id');

    Http::assertNothingSent();
});
