<?php

namespace Spatie\OpenApiCli;

class CommandNameGenerator
{
    public static function fromPath(string $method, string $path): string
    {
        $method = strtolower($method);

        // Remove leading slash
        $path = ltrim($path, '/');

        // Split by /
        $segments = explode('/', $path);

        // Filter out path parameter segments (those wrapped in {})
        $segments = array_filter($segments, fn (string $segment) => ! preg_match('/^\{.*\}$/', $segment));

        // Join with dashes
        $pathPart = implode('-', $segments);

        // Replace any remaining underscores or dots with dashes
        $pathPart = str_replace(['_', '.'], '-', $pathPart);

        return $method.'-'.$pathPart;
    }

    /**
     * Generate a disambiguated command name by appending trailing path parameter names.
     */
    public static function fromPathDisambiguated(string $method, string $path): string
    {
        $method = strtolower($method);

        // Remove leading slash
        $path = ltrim($path, '/');

        // Split by /
        $segments = explode('/', $path);

        // Process segments: strip middle path parameters but keep trailing ones
        $processed = [];
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if (preg_match('/^\{(.+)\}$/', $segment, $matches)) {
                if ($index === $lastIndex) {
                    $processed[] = self::parameterToOptionName($matches[1]);
                }
            } else {
                $processed[] = $segment;
            }
        }

        // Join with dashes
        $pathPart = implode('-', $processed);

        // Replace any remaining underscores or dots with dashes
        $pathPart = str_replace(['_', '.'], '-', $pathPart);

        return $method.'-'.$pathPart;
    }

    public static function fromOperationId(string $operationId): string
    {
        // Convert camelCase to kebab-case
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $operationId);
        $kebab = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $kebab);
        $kebab = strtolower($kebab);

        // Replace underscores with dashes
        return str_replace('_', '-', $kebab);
    }

    public static function parameterToOptionName(string $paramName): string
    {
        // Convert snake_case and camelCase to kebab-case
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $paramName);
        $kebab = strtolower($kebab);
        $kebab = str_replace('_', '-', $kebab);

        return $kebab;
    }

    public static function queryParamToOptionName(string $paramName): string
    {
        // Handle bracket notation: filter[id] -> filter-id, page[number] -> page-number
        $name = preg_replace('/\[([^\]]+)\]/', '-$1', $paramName);

        // Convert to kebab-case
        return self::parameterToOptionName($name);
    }
}
