<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\SpecResolver;

it('returns local paths unchanged', function () {
    $path = '/some/local/path/spec.yaml';

    expect(SpecResolver::resolve($path))->toBe($path);
});

it('returns local paths unchanged even with config', function () {
    $path = '/some/local/path/spec.json';
    $config = new CommandConfiguration($path, 'test');

    expect(SpecResolver::resolve($path, $config))->toBe($path);
});

it('detects http URLs', function () {
    expect(SpecResolver::isUrl('http://example.com/spec.yaml'))->toBeTrue();
    expect(SpecResolver::isUrl('https://example.com/spec.yaml'))->toBeTrue();
});

it('does not detect local paths as URLs', function () {
    expect(SpecResolver::isUrl('/path/to/spec.yaml'))->toBeFalse();
    expect(SpecResolver::isUrl('relative/spec.yaml'))->toBeFalse();
    expect(SpecResolver::isUrl('C:\\path\\spec.yaml'))->toBeFalse();
});

it('fetches remote spec and returns temp file path', function () {
    $yamlContent = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
YAML;

    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response($yamlContent, 200, [
            'Content-Type' => 'text/yaml',
        ]),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/spec.yaml');

    expect($result)->toStartWith(sys_get_temp_dir().'/openapi-cli-')
        ->and($result)->toEndWith('.yaml')
        ->and(file_exists($result))->toBeTrue()
        ->and(file_get_contents($result))->toBe($yamlContent);

    @unlink($result);
});

it('detects json extension from URL', function () {
    $jsonContent = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0.0"},"paths":{}}';

    Http::fake([
        'https://api.example.com/spec.json' => Http::response($jsonContent, 200),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/spec.json');

    expect($result)->toEndWith('.json');

    @unlink($result);
});

it('detects yaml extension from URL', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response($yamlContent, 200),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/spec.yaml');

    expect($result)->toEndWith('.yaml');

    @unlink($result);
});

it('detects yml extension from URL and normalizes to yaml', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/spec.yml' => Http::response($yamlContent, 200),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/spec.yml');

    expect($result)->toEndWith('.yaml');

    @unlink($result);
});

it('strips query params when detecting extension from URL', function () {
    $jsonContent = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0.0"},"paths":{}}';

    Http::fake([
        'https://api.example.com/spec.json*' => Http::response($jsonContent, 200),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/spec.json?token=abc123');

    expect($result)->toEndWith('.json');

    @unlink($result);
});

it('detects json from Content-Type header when URL has no extension', function () {
    $jsonContent = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0.0"},"paths":{}}';

    Http::fake([
        'https://api.example.com/openapi' => Http::response($jsonContent, 200, [
            'Content-Type' => 'application/json',
        ]),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/openapi');

    expect($result)->toEndWith('.json');

    @unlink($result);
});

it('detects yaml from Content-Type header when URL has no extension', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/openapi' => Http::response($yamlContent, 200, [
            'Content-Type' => 'application/x-yaml',
        ]),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/openapi');

    expect($result)->toEndWith('.yaml');

    @unlink($result);
});

it('detects json by content sniffing when no other signals', function () {
    $jsonContent = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0.0"},"paths":{}}';

    Http::fake([
        'https://api.example.com/openapi' => Http::response($jsonContent, 200, [
            'Content-Type' => 'text/plain',
        ]),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/openapi');

    expect($result)->toEndWith('.json');

    @unlink($result);
});

it('defaults to yaml when no format signals are available', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/openapi' => Http::response($yamlContent, 200, [
            'Content-Type' => 'text/plain',
        ]),
    ]);

    $result = SpecResolver::resolve('https://api.example.com/openapi');

    expect($result)->toEndWith('.yaml');

    @unlink($result);
});

it('caches remote spec content', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response($yamlContent, 200),
    ]);

    // First call - fetches from HTTP
    $result1 = SpecResolver::resolve('https://api.example.com/spec.yaml');

    // Second call - should use cache (no additional HTTP call)
    $result2 = SpecResolver::resolve('https://api.example.com/spec.yaml');

    expect($result1)->toBe($result2);

    Http::assertSentCount(1);

    @unlink($result1);
});

it('skips cache when noCache is set', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response($yamlContent, 200),
    ]);

    $config = new CommandConfiguration('https://api.example.com/spec.yaml', 'test');
    $config->noCache();

    // First call
    $result1 = SpecResolver::resolve('https://api.example.com/spec.yaml', $config);

    // Second call - should fetch again because noCache
    $result2 = SpecResolver::resolve('https://api.example.com/spec.yaml', $config);

    Http::assertSentCount(2);

    @unlink($result1);
});

it('uses custom TTL from config', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response($yamlContent, 200),
    ]);

    Cache::spy();

    $config = new CommandConfiguration('https://api.example.com/spec.yaml', 'test');
    $config->cacheTtl(600);

    SpecResolver::resolve('https://api.example.com/spec.yaml', $config);

    Cache::shouldHaveReceived('store')->atLeast()->once();
});

it('throws RuntimeException on HTTP failure', function () {
    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response('Not Found', 404),
    ]);

    SpecResolver::resolve('https://api.example.com/spec.yaml');
})->throws(\RuntimeException::class, 'Failed to fetch remote spec');

it('throws RuntimeException on server error', function () {
    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response('Internal Server Error', 500),
    ]);

    SpecResolver::resolve('https://api.example.com/spec.yaml');
})->throws(\RuntimeException::class, 'Failed to fetch remote spec');

it('uses content-hash-based temp file names for same content', function () {
    $yamlContent = "openapi: 3.0.0\ninfo:\n  title: Test\n  version: 1.0.0\npaths: {}";

    Http::fake([
        'https://api.example.com/spec.yaml' => Http::response($yamlContent, 200),
        'https://api.other.com/spec.yaml' => Http::response($yamlContent, 200),
    ]);

    $result1 = SpecResolver::resolve('https://api.example.com/spec.yaml');

    // Clear cache to force fresh fetch for second URL
    Cache::flush();

    $result2 = SpecResolver::resolve('https://api.other.com/spec.yaml');

    // Same content should produce same temp file path
    expect($result1)->toBe($result2);

    @unlink($result1);
});
