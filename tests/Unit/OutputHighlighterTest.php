<?php

use Spatie\OpenApiCli\OutputHighlighter;

// JSON highlighting

it('highlights JSON when enabled', function () {
    $highlighter = new OutputHighlighter(enabled: true);
    $json = '{"name": "test"}';

    $result = $highlighter->highlightJson($json);

    expect($result)->not->toBe($json);
    expect($result)->toContain('name');
    expect($result)->toContain('test');
});

it('passes through JSON when disabled', function () {
    $highlighter = new OutputHighlighter(enabled: false);
    $json = '{"name": "test"}';

    expect($highlighter->highlightJson($json))->toBe($json);
});

it('highlights pretty-printed JSON', function () {
    $highlighter = new OutputHighlighter(enabled: true);
    $json = json_encode(['id' => 1, 'name' => 'test'], JSON_PRETTY_PRINT);

    $result = $highlighter->highlightJson($json);

    expect($result)->not->toBe($json);
    expect(strip_ansi($result))->toBe($json);
});

// Human-readable highlighting

it('highlights headings when enabled', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    $result = $highlighter->highlightHumanReadable('# Data');

    expect($result)->toContain("\033[1;36m");
    expect($result)->toContain('Data');
});

it('highlights second-level headings', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    $result = $highlighter->highlightHumanReadable('## Settings');

    expect($result)->toContain("\033[1;36m");
    expect($result)->toContain('Settings');
});

it('highlights key-value lines when enabled', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    $result = $highlighter->highlightHumanReadable('Name: John');

    expect($result)->toContain("\033[32m");
    expect($result)->toContain('Name:');
    expect($result)->toContain('John');
});

it('highlights bullet list items', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    $result = $highlighter->highlightHumanReadable('- apple');

    expect($result)->toContain("\033[33m");
    expect($result)->toContain('apple');
});

it('dims table separators', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    $result = $highlighter->highlightHumanReadable('| --- | --- |');

    expect($result)->toContain("\033[2m");
});

it('dims table row pipes', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    $result = $highlighter->highlightHumanReadable('| 1   | Foo |');

    expect($result)->toContain("\033[2m");
    expect($result)->toContain('Foo');
});

it('passes through human-readable text when disabled', function () {
    $highlighter = new OutputHighlighter(enabled: false);
    $text = "# Data\n\nName: John\n- item";

    expect($highlighter->highlightHumanReadable($text))->toBe($text);
});

it('handles multiline human-readable content', function () {
    $highlighter = new OutputHighlighter(enabled: true);
    $text = "# Data\n\n| ID | Name |\n| -- | ---- |\n| 1  | Foo  |\n\nTotal: 2";

    $result = $highlighter->highlightHumanReadable($text);

    expect($result)->toContain("\033[1;36m");
    expect($result)->toContain("\033[32m");
    expect($result)->toContain("\033[2m");
});

it('recovers original text after stripping ANSI from human-readable', function () {
    $highlighter = new OutputHighlighter(enabled: true);
    $text = "# Data\n\nName: John\n- apple";

    $result = $highlighter->highlightHumanReadable($text);

    expect(strip_ansi($result))->toBe($text);
});

it('leaves plain lines unchanged', function () {
    $highlighter = new OutputHighlighter(enabled: true);

    expect($highlighter->highlightHumanReadable('just plain text'))->toBe('just plain text');
});

// Helper

function strip_ansi(string $text): string
{
    return preg_replace('/\033\[[0-9;]*m/', '', $text);
}
