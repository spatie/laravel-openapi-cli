<?php

namespace Spatie\OpenApiCli;

use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\LightTerminalTheme;

class OutputHighlighter
{
    private ?Highlighter $highlighter = null;

    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function highlightJson(string $json): string
    {
        if (! $this->enabled) {
            return $json;
        }

        return $this->getHighlighter()->parse($json, 'json');
    }

    public function highlightYaml(string $yaml): string
    {
        if (! $this->enabled) {
            return $yaml;
        }

        return $this->getHighlighter()->parse($yaml, 'yaml');
    }

    public function highlightHumanReadable(string $text): string
    {
        if (! $this->enabled) {
            return $text;
        }

        $lines = explode("\n", $text);
        $highlighted = array_map(fn (string $line) => $this->highlightHumanReadableLine($line), $lines);

        return implode("\n", $highlighted);
    }

    private function highlightHumanReadableLine(string $line): string
    {
        // Headings: # Heading, ## Heading, etc.
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
            return "\033[1;36m{$matches[1]} {$matches[2]}\033[0m";
        }

        // Table separator: | --- | --- |
        if (preg_match('/^\|[\s\-|]+\|$/', $line)) {
            return "\033[2m{$line}\033[0m";
        }

        // Table row: | value | value |
        if (preg_match('/^\|.*\|$/', $line)) {
            return preg_replace('/(\|)/', "\033[2m$1\033[0m", $line);
        }

        // Bullet list: - item
        if (preg_match('/^(-\s)(.+)$/', $line, $matches)) {
            return "\033[33m{$matches[1]}\033[0m{$matches[2]}";
        }

        // Key-value: Key: value
        if (preg_match('/^([^:]+):\s(.+)$/', $line, $matches)) {
            return "\033[32m{$matches[1]}:\033[0m {$matches[2]}";
        }

        return $line;
    }

    private function getHighlighter(): Highlighter
    {
        if ($this->highlighter === null) {
            $this->highlighter = new Highlighter(new LightTerminalTheme);
        }

        return $this->highlighter;
    }
}
