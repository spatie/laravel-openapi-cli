<?php

namespace Spatie\OpenApiCli;

class HumanReadableFormatter
{
    private const MAX_DEPTH = 4;

    private const MAX_CELL_WIDTH = 40;

    public function format(mixed $data, int $depth = 0): string
    {
        if ($depth >= self::MAX_DEPTH) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        if ($data === null) {
            return '(empty)';
        }

        if (is_bool($data)) {
            return $data ? 'Yes' : 'No';
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        if (! is_array($data)) {
            return (string) $data;
        }

        if ($data === []) {
            return $this->isAssociative($data) ? '(empty)' : '(empty list)';
        }

        if ($this->isAssociative($data)) {
            return $this->formatObject($data, $depth);
        }

        return $this->formatArray($data, $depth);
    }

    private function formatObject(array $data, int $depth): string
    {
        $hasNestedValues = false;

        foreach ($data as $value) {
            if (is_array($value)) {
                $hasNestedValues = true;
                break;
            }
        }

        if (! $hasNestedValues) {
            return $this->formatSimpleObject($data);
        }

        return $this->formatNestedObject($data, $depth);
    }

    private function formatSimpleObject(array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            $lines[] = $this->humanizeKey((string) $key).': '.$this->formatScalar($value);
        }

        return implode("\n", $lines);
    }

    private function formatNestedObject(array $data, int $depth): string
    {
        $sections = [];
        $headingPrefix = str_repeat('#', min($depth + 1, 6));

        foreach ($data as $key => $value) {
            $heading = "{$headingPrefix} ".$this->humanizeKey((string) $key);

            if (is_array($value)) {
                $formatted = $this->format($value, $depth + 1);
                $sections[] = "{$heading}\n\n{$formatted}";
            } else {
                $sections[] = "{$heading}\n\n".$this->formatScalar($value);
            }
        }

        return implode("\n\n", $sections);
    }

    private function formatArray(array $data, int $depth): string
    {
        if ($this->isScalarList($data)) {
            return $this->formatBulletList($data);
        }

        if ($this->isHomogeneousObjectList($data)) {
            return $this->formatTable($data);
        }

        return $this->formatHeterogeneousList($data, $depth);
    }

    private function formatBulletList(array $data): string
    {
        $lines = [];

        foreach ($data as $item) {
            $lines[] = '- '.$this->formatScalar($item);
        }

        return implode("\n", $lines);
    }

    private function formatTable(array $data): string
    {
        $keys = array_keys($data[0]);

        $headers = array_map(fn (string $key) => $this->humanizeKey($key), $keys);

        /** @var array<int, array<int, string>> $rows */
        $rows = [];

        foreach ($data as $item) {
            $row = [];

            foreach ($keys as $key) {
                $value = $item[$key] ?? null;

                if (is_array($value)) {
                    $cell = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                } else {
                    $cell = $this->formatScalar($value);
                }

                $row[] = $cell;
            }

            $rows[] = $row;
        }

        $columnWidths = [];

        foreach ($headers as $i => $header) {
            $columnWidths[$i] = mb_strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $columnWidths[$i] = max($columnWidths[$i], mb_strlen($cell));
            }
        }

        foreach ($columnWidths as $i => $width) {
            $columnWidths[$i] = min($width, self::MAX_CELL_WIDTH);
        }

        $lines = [];

        $headerCells = [];

        foreach ($headers as $i => $header) {
            $headerCells[] = $this->padCell($header, $columnWidths[$i]);
        }

        $lines[] = '| '.implode(' | ', $headerCells).' |';

        $separatorCells = [];

        foreach ($columnWidths as $width) {
            $separatorCells[] = str_repeat('-', $width);
        }

        $lines[] = '| '.implode(' | ', $separatorCells).' |';

        foreach ($rows as $row) {
            $rowCells = [];

            foreach ($row as $i => $cell) {
                $rowCells[] = $this->padCell($cell, $columnWidths[$i]);
            }

            $lines[] = '| '.implode(' | ', $rowCells).' |';
        }

        return implode("\n", $lines);
    }

    private function formatHeterogeneousList(array $data, int $depth): string
    {
        $sections = [];
        $headingPrefix = str_repeat('#', min($depth + 1, 6));

        foreach ($data as $index => $item) {
            $number = $index + 1;
            $heading = "{$headingPrefix} Item {$number}";
            $formatted = $this->format($item, $depth + 1);
            $sections[] = "{$heading}\n\n{$formatted}";
        }

        return implode("\n\n", $sections);
    }

    public function formatScalar(mixed $value): string
    {
        if ($value === null) {
            return '(empty)';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    public function humanizeKey(string $key): string
    {
        $abbreviations = [
            'id' => 'ID',
            'url' => 'URL',
            'uri' => 'URI',
            'api' => 'API',
            'ip' => 'IP',
            'uuid' => 'UUID',
            'html' => 'HTML',
            'css' => 'CSS',
            'json' => 'JSON',
            'xml' => 'XML',
            'sql' => 'SQL',
            'http' => 'HTTP',
            'https' => 'HTTPS',
            'ssh' => 'SSH',
            'ftp' => 'FTP',
            'cpu' => 'CPU',
            'gpu' => 'GPU',
            'ram' => 'RAM',
            'os' => 'OS',
            'io' => 'IO',
        ];

        if (isset($abbreviations[strtolower($key)])) {
            return $abbreviations[strtolower($key)];
        }

        // Convert camelCase to spaces
        $result = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key) ?? $key;

        // Convert snake_case/kebab-case to spaces
        $result = str_replace(['_', '-'], ' ', $result);

        // Title case each word, handling abbreviations
        $words = explode(' ', $result);
        $titleCased = array_map(function (string $word) use ($abbreviations) {
            $lower = strtolower($word);

            if (isset($abbreviations[$lower])) {
                return $abbreviations[$lower];
            }

            return ucfirst($lower);
        }, $words);

        return implode(' ', $titleCased);
    }

    private function isAssociative(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function isScalarList(array $data): bool
    {
        foreach ($data as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private function isHomogeneousObjectList(array $data): bool
    {
        if (count($data) === 0) {
            return false;
        }

        $firstKeys = null;

        foreach ($data as $item) {
            if (! is_array($item) || ! $this->isAssociative($item)) {
                return false;
            }

            $keys = array_keys($item);
            sort($keys);

            if ($firstKeys === null) {
                $firstKeys = $keys;
            } elseif ($keys !== $firstKeys) {
                return false;
            }
        }

        return true;
    }

    private function padCell(string $value, int $width): string
    {
        if (mb_strlen($value) > $width) {
            return mb_substr($value, 0, $width - 3).'...';
        }

        return str_pad($value, $width);
    }
}
