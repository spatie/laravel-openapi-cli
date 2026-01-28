<?php

namespace Spatie\OpenApiCli;

class PathMatcher
{
    /**
     * Convert an OpenAPI path template to a regex pattern.
     * Example: /projects/{id} -> /projects/(?P<id>[^/]+)
     *
     * @param  string  $path  OpenAPI path template
     * @return string Regex pattern
     */
    public function convertToRegex(string $path): string
    {
        // Escape special regex characters in the path except for {}
        // Use ~ as delimiter to avoid escaping forward slashes
        $pattern = preg_quote($path, '~');

        // Convert {param} to named capture groups (?P<param>[^/]+)
        $pattern = preg_replace_callback(
            '/\\\{([^}]+)\\\}/',
            function ($matches) {
                $paramName = $matches[1];

                return '(?P<'.$paramName.'>[^/]+)';
            },
            $pattern
        );

        // Return the full regex pattern with delimiters and anchors
        return '~^'.rtrim($pattern, '/').'/?$~';
    }

    /**
     * Match user input against OpenAPI spec paths and return all matches.
     *
     * @param  string  $userInput  User-provided path (e.g., "projects/123")
     * @param  array<string, array<string, mixed>>  $specPaths  OpenAPI paths with their operations
     * @return array<int, array{path: string, parameters: array<string, string>, methods: array<int, string>, isExact: bool}> Array of matches
     */
    public function matchPath(string $userInput, array $specPaths): array
    {
        // Normalize user input: ensure it starts with / and remove trailing /
        $normalizedInput = '/'.trim($userInput, '/');

        $matches = [];

        foreach ($specPaths as $specPath => $operations) {
            // Convert spec path to regex
            $regex = $this->convertToRegex($specPath);

            // Try to match
            if (preg_match($regex, $normalizedInput, $captures)) {
                // Extract only named parameters (not numeric indices)
                $parameters = array_filter(
                    $captures,
                    fn ($key) => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );

                // Get HTTP methods for this path
                $methods = array_map('strtoupper', array_keys($operations));

                // Check if this is an exact match (no parameters)
                $isExact = empty($parameters);

                $matches[] = [
                    'path' => $specPath,
                    'parameters' => $parameters,
                    'methods' => $methods,
                    'isExact' => $isExact,
                ];
            }
        }

        // Prioritize exact matches over parameterized matches
        usort($matches, function ($a, $b) {
            if ($a['isExact'] === $b['isExact']) {
                return 0;
            }

            return $a['isExact'] ? -1 : 1;
        });

        return $matches;
    }
}
