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
}
