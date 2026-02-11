<?php

namespace Spatie\OpenApiCli;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class OpenApiParser
{
    private array $spec;

    public function __construct(string $filePath)
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Spec file not found: {$filePath}");
        }

        if (! is_readable($filePath)) {
            throw new InvalidArgumentException("Spec file is not readable: {$filePath}");
        }

        $this->spec = $this->parseFile($filePath);
    }

    public function getPaths(): array
    {
        return $this->spec['paths'] ?? [];
    }

    public function getServerUrl(): ?string
    {
        if (! isset($this->spec['servers']) || ! is_array($this->spec['servers'])) {
            return null;
        }

        if (empty($this->spec['servers'])) {
            return null;
        }

        return $this->spec['servers'][0]['url'] ?? null;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getPathsWithMethods(): array
    {
        return collect($this->getPaths())
            ->map(fn (array $methods) => collect($methods)
                ->keys()
                ->intersect(['get', 'post', 'put', 'patch', 'delete'])
                ->values()
                ->all()
            )
            ->all();
    }

    public function getOperationSummary(string $path, string $method): ?string
    {
        $method = strtolower($method);

        return $this->spec['paths'][$path][$method]['summary'] ?? null;
    }

    public function getOperationDescription(string $path, string $method): ?string
    {
        $method = strtolower($method);

        return $this->spec['paths'][$path][$method]['description'] ?? null;
    }

    /**
     * @return array<int, array{name: string, type: string, required: bool}>
     */
    public function getPathParameters(string $path, string $method): array
    {
        $method = strtolower($method);
        $parameters = $this->spec['paths'][$path][$method]['parameters'] ?? [];

        return collect($parameters)
            ->filter(fn (array $param) => ($param['in'] ?? '') === 'path')
            ->map(fn (array $param) => [
                'name' => $param['name'] ?? '',
                'type' => $this->extractType($param['schema'] ?? []),
                'required' => $param['required'] ?? false,
            ])
            ->values()
            ->all();
    }

    public function getOperationId(string $path, string $method): ?string
    {
        $method = strtolower($method);

        return $this->spec['paths'][$path][$method]['operationId'] ?? null;
    }

    /**
     * @return array<int, array{name: string, required: bool, description: string}>
     */
    public function getQueryParameters(string $path, string $method): array
    {
        $method = strtolower($method);
        $parameters = $this->spec['paths'][$path][$method]['parameters'] ?? [];
        $resolver = new RefResolver($this->spec);

        return collect($parameters)
            ->map(fn (array $param) => $resolver->resolve($param))
            ->filter(fn (array $param) => ($param['in'] ?? '') === 'query')
            ->map(fn (array $param) => [
                'name' => $param['name'] ?? '',
                'required' => $param['required'] ?? false,
                'description' => $param['description'] ?? '',
            ])
            ->values()
            ->all();
    }

    public function getRequestBodySchema(string $path, string $method): ?array
    {
        $method = strtolower($method);
        $operation = $this->spec['paths'][$path][$method] ?? [];
        $requestBody = $operation['requestBody'] ?? null;

        if (! $requestBody) {
            return null;
        }

        $content = $requestBody['content'] ?? [];
        $jsonContent = $content['application/json'] ?? null;

        if (! $jsonContent) {
            return null;
        }

        return $jsonContent['schema'] ?? null;
    }

    /**
     * Get the request body content types for a given path and method.
     *
     * @return array<string>
     */
    public function getRequestBodyContentTypes(string $path, string $method): array
    {
        $method = strtolower($method);
        $operation = $this->spec['paths'][$path][$method] ?? [];
        $requestBody = $operation['requestBody'] ?? null;

        if (! $requestBody) {
            return [];
        }

        $content = $requestBody['content'] ?? [];

        return array_keys($content);
    }

    public function getSpec(): array
    {
        return $this->spec;
    }

    private function parseFile(string $filePath): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return match ($extension) {
            'yaml', 'yml' => $this->parseYaml($filePath),
            'json' => $this->parseJson($filePath),
            default => throw new InvalidArgumentException("Unsupported file format: {$extension}. Only YAML and JSON are supported."),
        };
    }

    private function parseYaml(string $filePath): array
    {
        try {
            $parsed = Yaml::parseFile($filePath);

            if (! is_array($parsed)) {
                throw new InvalidArgumentException("Invalid YAML file: {$filePath}");
            }

            return $parsed;
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Failed to parse YAML file: {$e->getMessage()}", 0, $e);
        }
    }

    private function parseJson(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new InvalidArgumentException("Failed to read file: {$filePath}");
        }

        try {
            $parsed = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($parsed)) {
                throw new InvalidArgumentException("Invalid JSON file: {$filePath}");
            }

            return $parsed;
        } catch (\JsonException $e) {
            throw new InvalidArgumentException("Failed to parse JSON file: {$e->getMessage()}", 0, $e);
        }
    }

    private function extractType(array $schema): string
    {
        $type = $schema['type'] ?? 'string';

        if (is_array($type)) {
            return $type[0] ?? 'string';
        }

        return $type;
    }
}
