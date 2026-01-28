<?php

use Spatie\OpenApiCli\OpenApiParser;

it('can parse a YAML OpenAPI spec file', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    expect($parser)->toBeInstanceOf(OpenApiParser::class);
});

it('can extract paths from the spec', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');
    $paths = $parser->getPaths();

    expect($paths)->toBeArray()
        ->and($paths)->toHaveKey('/me')
        ->and($paths)->toHaveKey('/projects')
        ->and($paths)->toHaveKey('/projects/{project_id}/errors');
});

it('can extract server URL from the spec', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');
    $serverUrl = $parser->getServerUrl();

    expect($serverUrl)->toBe('https://flareapp.io/api');
});

it('handles specs without servers array gracefully', function () {
    $tempFile = sys_get_temp_dir().'/openapi_'.uniqid().'.yaml';
    file_put_contents($tempFile, <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
YAML
    );

    $parser = new OpenApiParser($tempFile);
    $serverUrl = $parser->getServerUrl();

    expect($serverUrl)->toBeNull();

    unlink($tempFile);
});

it('handles empty servers array gracefully', function () {
    $tempFile = sys_get_temp_dir().'/openapi_'.uniqid().'.yaml';
    file_put_contents($tempFile, <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers: []
paths:
  /test:
    get:
      summary: Test endpoint
YAML
    );

    $parser = new OpenApiParser($tempFile);
    $serverUrl = $parser->getServerUrl();

    expect($serverUrl)->toBeNull();

    unlink($tempFile);
});

it('can extract paths with their HTTP methods', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');
    $pathsWithMethods = $parser->getPathsWithMethods();

    expect($pathsWithMethods)->toBeArray()
        ->and($pathsWithMethods['/me'])->toBe(['get'])
        ->and($pathsWithMethods['/projects'])->toBe(['get', 'post'])
        ->and($pathsWithMethods['/projects/{project_id}/errors'])->toContain('get')
        ->and($pathsWithMethods['/projects/{project_id}/errors'])->toContain('delete');
});

it('can extract operation summaries', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    $summary = $parser->getOperationSummary('/me', 'get');
    expect($summary)->toBe('Get the authenticated user.');

    $summary = $parser->getOperationSummary('/projects', 'get');
    expect($summary)->toBe('Get all projects.');

    $summary = $parser->getOperationSummary('/projects', 'post');
    expect($summary)->toBe('Create a new project.');
});

it('can extract operation descriptions', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    $description = $parser->getOperationDescription('/me', 'get');
    expect($description)->toBeNull();
});

it('can extract path parameters with types', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    $params = $parser->getPathParameters('/projects/{project_id}/errors', 'get');

    expect($params)->toBeArray()
        ->and($params)->toHaveCount(1)
        ->and($params[0])->toBe([
            'name' => 'project_id',
            'type' => 'integer',
            'required' => true,
        ]);
});

it('can extract path parameters from paths with multiple parameters', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    $params = $parser->getPathParameters('/teams/{team_id}/users/{user_id}', 'delete');

    expect($params)->toBeArray()
        ->and($params)->toHaveCount(2)
        ->and($params[0]['name'])->toBe('team_id')
        ->and($params[0]['type'])->toBe('integer')
        ->and($params[0]['required'])->toBe(true)
        ->and($params[1]['name'])->toBe('user_id')
        ->and($params[1]['type'])->toBe('integer')
        ->and($params[1]['required'])->toBe(true);
});

it('can extract request body schema', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    $schema = $parser->getRequestBodySchema('/projects', 'post');

    expect($schema)->toBeArray()
        ->and($schema)->toHaveKey('required')
        ->and($schema)->toHaveKey('properties')
        ->and($schema['required'])->toContain('name')
        ->and($schema['required'])->toContain('team_id')
        ->and($schema['properties'])->toHaveKey('name')
        ->and($schema['properties'])->toHaveKey('team_id');
});

it('returns null when request body schema is not present', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');

    $schema = $parser->getRequestBodySchema('/me', 'get');

    expect($schema)->toBeNull();
});

it('throws exception when spec file does not exist', function () {
    new OpenApiParser('/path/to/nonexistent/file.yaml');
})->throws(\InvalidArgumentException::class, 'Spec file not found');

it('throws exception for invalid YAML syntax', function () {
    $tempFile = sys_get_temp_dir().'/openapi_'.uniqid().'.yaml';
    file_put_contents($tempFile, <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: [1.0.0
    invalid yaml here
YAML
    );

    try {
        new OpenApiParser($tempFile);
    } finally {
        unlink($tempFile);
    }
})->throws(\InvalidArgumentException::class);

it('can get the full spec', function () {
    $parser = new OpenApiParser(__DIR__.'/../../flare-api.yaml');
    $spec = $parser->getSpec();

    expect($spec)->toBeArray()
        ->and($spec)->toHaveKey('openapi')
        ->and($spec)->toHaveKey('info')
        ->and($spec)->toHaveKey('paths')
        ->and($spec['openapi'])->toBe('3.1.0');
});

it('handles array type in schema correctly', function () {
    $tempFile = sys_get_temp_dir().'/openapi_'.uniqid().'.yaml';
    file_put_contents($tempFile, <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test/{id}:
    get:
      summary: Test endpoint
      parameters:
        - in: path
          name: id
          required: true
          schema:
            type:
              - integer
              - string
YAML
    );

    $parser = new OpenApiParser($tempFile);
    $params = $parser->getPathParameters('/test/{id}', 'get');

    expect($params[0]['type'])->toBe('integer');

    unlink($tempFile);
});
