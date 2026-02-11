<?php

namespace Spatie\OpenApiCli;

use Illuminate\Support\Str;

class CommandNameGenerator
{
    public static function fromPath(string $method, string $path): string
    {
        return Str::of($path)
            ->ltrim('/')
            ->replaceMatches('#\{[^}]*\}#', '')
            ->replaceMatches('#/+#', '/')
            ->trim('/')
            ->replace('/', '-')
            ->replace(['_', '.'], '-')
            ->prepend(strtolower($method).'-')
            ->value();
    }

    /**
     * Generate a disambiguated command name by appending trailing path parameter names.
     */
    public static function fromPathDisambiguated(string $method, string $path): string
    {
        $segments = collect(explode('/', ltrim($path, '/')));
        $lastIndex = $segments->count() - 1;

        $pathPart = $segments
            ->map(fn (string $segment, int $index) => preg_match('/^\{(.+)\}$/', $segment, $matches)
                ? ($index === $lastIndex ? self::parameterToOptionName($matches[1]) : null)
                : $segment
            )
            ->filter()
            ->implode('-');

        $pathPart = str_replace(['_', '.'], '-', $pathPart);

        return strtolower($method).'-'.$pathPart;
    }

    public static function fromOperationId(string $operationId): string
    {
        return Str::of($operationId)
            ->replaceMatches('/([a-z])([A-Z])/', '$1-$2')
            ->replaceMatches('/([A-Z]+)([A-Z][a-z])/', '$1-$2')
            ->lower()
            ->replace('_', '-')
            ->value();
    }

    public static function parameterToOptionName(string $paramName): string
    {
        return Str::of($paramName)
            ->replaceMatches('/([a-z])([A-Z])/', '$1-$2')
            ->lower()
            ->replace('_', '-')
            ->value();
    }

    public static function queryParamToOptionName(string $paramName): string
    {
        return Str::of($paramName)
            ->replaceMatches('/\[([^\]]+)\]/', '-$1')
            ->pipe(fn ($name) => self::parameterToOptionName($name->value()));
    }
}
