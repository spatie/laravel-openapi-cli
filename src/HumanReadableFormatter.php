<?php

namespace Spatie\OpenApiCli;

use Illuminate\Support\Collection;

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
        $headingPrefix = str_repeat('#', min($depth + 1, 6));

        return collect($data)
            ->map(function ($value, string $key) use ($headingPrefix, $depth) {
                $heading = "{$headingPrefix} ".$this->humanizeKey($key);
                $body = is_array($value) ? $this->format($value, $depth + 1) : $this->formatScalar($value);

                return "{$heading}\n\n{$body}";
            })
            ->implode("\n\n");
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
        return collect($data)
            ->map(fn ($item) => '- '.$this->formatScalar($item))
            ->implode("\n");
    }

    private function formatTable(array $data): string
    {
        $keys = collect(array_keys($data[0]));
        $headers = $keys->map(fn (string $key) => $this->humanizeKey($key));

        $rows = collect($data)->map(fn (array $item) => $keys->map(function (string $key) use ($item) {
            $value = $item[$key] ?? null;

            return is_array($value)
                ? (json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
                : $this->formatScalar($value);
        }));

        $columnWidths = $headers->map(fn (string $header, int $i) => max(
            mb_strlen($header),
            $rows->max(fn ($row) => mb_strlen($row[$i]))
        ));

        $totalWidth = $columnWidths->sum() + 3 * ($columnWidths->count() - 1) + 4;

        if ($this->terminalWidth !== null && $totalWidth > $this->terminalWidth) {
            return $this->formatVerticalCards($headers, $rows);
        }

        $formatRow = fn ($cells) => '| '.$cells->map(fn (string $cell, int $i) => $this->padCell($cell, $columnWidths[$i]))->implode(' | ').' |';

        return collect([
            $formatRow($headers),
            '| '.$columnWidths->map(fn (int $width) => str_repeat('-', $width))->implode(' | ').' |',
            ...$rows->map($formatRow),
        ])->implode("\n");
    }

    private function formatVerticalCards(Collection $headers, Collection $rows): string
    {
        $keyWidth = $headers->max(fn (string $header) => mb_strlen($header));
        $valueWidth = $rows->flatMap->values()->max(fn (string $cell) => mb_strlen($cell));

        $totalWidth = $keyWidth + $valueWidth + 7;
        $useTable = $this->terminalWidth === null || $totalWidth <= $this->terminalWidth;

        $cards = $rows->map(fn (Collection $row) => $row->values()
            ->map(fn (string $cell, int $i) => $useTable
                ? '| '.$this->padCell($headers[$i], $keyWidth).' | '.$this->padCell($cell, $valueWidth).' |'
                : $headers[$i].': '.$cell
            )
            ->implode("\n")
        );

        return $useTable
            ? $cards->implode("\n".str_repeat('-', $totalWidth)."\n")
            : $cards->implode("\n\n");
    }

    private function formatHeterogeneousList(array $data, int $depth): string
    {
        $headingPrefix = str_repeat('#', min($depth + 1, 6));

        return collect($data)
            ->map(fn ($item, int $index) => "{$headingPrefix} Item ".($index + 1)."\n\n".$this->format($item, $depth + 1))
            ->implode("\n\n");
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
