<?php

namespace Spatie\OpenApiCli;

use RuntimeException;

class RefResolver
{
    public function __construct(
        private array $document
    ) {}

    /**
     * Resolve all $ref references in the given data recursively.
     */
    public function resolve(mixed $data): mixed
    {
        if (is_array($data)) {
            // Check if this is a $ref object
            if (isset($data['$ref']) && is_string($data['$ref'])) {
                $resolved = $this->resolveReference($data['$ref']);

                // Recursively resolve the resolved reference in case it contains more refs
                return $this->resolve($resolved);
            }

            // Recursively resolve all array elements
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->resolve($value);
            }

            return $result;
        }

        return $data;
    }

    /**
     * Resolve a JSON pointer reference like '#/components/schemas/User'.
     */
    private function resolveReference(string $ref): mixed
    {
        // Only handle internal references starting with '#/'
        if (! str_starts_with($ref, '#/')) {
            throw new RuntimeException("External references are not supported: {$ref}");
        }

        // Remove the '#/' prefix and split by '/'
        $pointer = substr($ref, 2);
        $parts = explode('/', $pointer);

        // Navigate through the document using the pointer parts
        $current = $this->document;

        foreach ($parts as $part) {
            // Decode JSON pointer escaped characters
            // In JSON pointer: ~0 represents ~ and ~1 represents /
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (! is_array($current) || ! array_key_exists($part, $current)) {
                throw new RuntimeException("Invalid reference pointer: {$ref}");
            }

            $current = $current[$part];
        }

        return $current;
    }
}
