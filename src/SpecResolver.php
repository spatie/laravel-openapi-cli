<?php

namespace Spatie\OpenApiCli;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpecResolver
{
    public static function resolve(string $specSource, ?CommandConfiguration $config = null): string
    {
        if (! static::isUrl($specSource)) {
            return $specSource;
        }

        return static::resolveUrl($specSource, $config);
    }

    public static function isUrl(string $source): bool
    {
        return str_starts_with($source, 'http://') || str_starts_with($source, 'https://');
    }

    protected static function resolveUrl(string $url, ?CommandConfiguration $config = null): string
    {
        $useCache = $config?->shouldCache() ?? false;
        $prefix = $config?->getCachePrefix() ?? 'openapi-cli-spec:';
        $cacheStore = $config?->getCacheStore();
        $cacheKey = $prefix.md5($url);

        if ($useCache) {
            $cached = Cache::store($cacheStore)->get($cacheKey);

            if ($cached !== null) {
                return static::writeTempFile($cached['content'], $cached['extension']);
            }
        }

        $response = Http::get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch remote spec from {$url}: HTTP {$response->status()}");
        }

        $content = $response->body();
        $extension = static::detectExtension($url, $response->header('Content-Type'), $content);

        if ($useCache) {
            $ttl = $config?->getCacheTtl() ?? 60;

            Cache::store($cacheStore)->put($cacheKey, [
                'content' => $content,
                'extension' => $extension,
            ], $ttl);
        }

        return static::writeTempFile($content, $extension);
    }

    protected static function detectExtension(string $url, ?string $contentType, string $content): string
    {
        // 1. URL path extension (strip query params)
        $urlPath = parse_url($url, PHP_URL_PATH);
        if ($urlPath !== null) {
            $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['yaml', 'yml', 'json'])) {
                return $ext === 'yml' ? 'yaml' : $ext;
            }
        }

        // 2. Content-Type header
        if ($contentType !== null) {
            $contentType = strtolower($contentType);
            if (str_contains($contentType, 'json')) {
                return 'json';
            }
            if (str_contains($contentType, 'yaml') || str_contains($contentType, 'yml')) {
                return 'yaml';
            }
        }

        // 3. Content sniffing
        $trimmed = ltrim($content);
        if ($trimmed !== '') {
            if ($trimmed[0] === '{' || $trimmed[0] === '[') {
                return 'json';
            }
        }

        // 4. Default to YAML
        return 'yaml';
    }

    protected static function writeTempFile(string $content, string $extension): string
    {
        $hash = md5($content);
        $path = sys_get_temp_dir()."/openapi-cli-{$hash}.{$extension}";

        if (! file_exists($path) || file_get_contents($path) !== $content) {
            file_put_contents($path, $content);
        }

        return $path;
    }
}
