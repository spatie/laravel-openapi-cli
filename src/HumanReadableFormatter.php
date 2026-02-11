<?php

namespace Spatie\OpenApiCli;

class HumanReadableFormatter
{
    private const MAX_DEPTH = 4;

    public function __construct(
        private ?int $terminalWidth = null,
    ) {}

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
        if (! collect($data)->contains(fn ($value) => is_array($value))) {
            return $this->formatSimpleObject($data);
        }

        return $this->formatNestedObject($data, $depth);
    }

    private function formatSimpleObject(array $data): string
    {
        $collection = collect($data);

        return $this->formatKeyValueTable(
            $collection->keys()->map(fn (string $key) => $this->humanizeKey($key))->all(),
            $collection->values()->map(fn ($value) => $this->formatScalar($value))->all(),
        );
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $values
     */
    private function formatKeyValueTable(array $keys, array $values): string
    {
        $keyWidth = collect($keys)->max(fn (string $key) => mb_strlen($key));
        $valueWidth = collect($values)->max(fn (string $value) => mb_strlen($value));

        $totalWidth = $keyWidth + $valueWidth + 7; // "| " + " | " + " |"

        if ($this->terminalWidth !== null && $totalWidth > $this->terminalWidth) {
            return collect($keys)
                ->map(fn (string $key, int $i) => $key.': '.$values[$i])
                ->implode("\n");
        }

        return collect($keys)
            ->map(fn (string $key, int $i) => '| '.$this->padCell($key, $keyWidth).' | '.$this->padCell($values[$i], $valueWidth).' |')
            ->implode("\n");
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

        $totalWidth = array_sum($columnWidths) + 3 * (count($columnWidths) - 1) + 4;

        if ($this->terminalWidth !== null && $totalWidth > $this->terminalWidth) {
            return $this->formatVerticalCards($keys, $headers, $rows);
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

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function formatVerticalCards(array $keys, array $headers, array $rows): string
    {
        $keyWidth = max(array_map('mb_strlen', $headers));

        $valueWidth = 0;

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $valueWidth = max($valueWidth, mb_strlen($cell));
            }
        }

        $totalWidth = $keyWidth + $valueWidth + 7;
        $useTable = $this->terminalWidth === null || $totalWidth <= $this->terminalWidth;

        $cards = [];

        foreach ($rows as $row) {
            $lines = [];

            foreach (array_values($row) as $i => $cell) {
                if ($useTable) {
                    $lines[] = '| '.$this->padCell($headers[$i], $keyWidth).' | '.$this->padCell($cell, $valueWidth).' |';
                } else {
                    $lines[] = $headers[$i].': '.$cell;
                }
            }

            $cards[] = implode("\n", $lines);
        }

        if ($useTable) {
            $separator = str_repeat('-', $totalWidth);

            return implode("\n".$separator."\n", $cards);
        }

        return implode("\n\n", $cards);
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
        return str_pad($value, $width);
    }
}
