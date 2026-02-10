<?php

use Spatie\OpenApiCli\HumanReadableFormatter;

beforeEach(function () {
    $this->formatter = new HumanReadableFormatter;
});

// Scalar values

it('formats null as empty', function () {
    expect($this->formatter->format(null))->toBe('(empty)');
});

it('formats true as Yes', function () {
    expect($this->formatter->format(true))->toBe('Yes');
});

it('formats false as No', function () {
    expect($this->formatter->format(false))->toBe('No');
});

it('formats strings as-is', function () {
    expect($this->formatter->format('hello world'))->toBe('hello world');
});

it('formats integers as strings', function () {
    expect($this->formatter->format(42))->toBe('42');
});

it('formats floats as strings', function () {
    expect($this->formatter->format(3.14))->toBe('3.14');
});

// Key humanization

it('humanizes snake_case keys', function () {
    expect($this->formatter->humanizeKey('created_at'))->toBe('Created At');
    expect($this->formatter->humanizeKey('first_name'))->toBe('First Name');
});

it('humanizes camelCase keys', function () {
    expect($this->formatter->humanizeKey('createdAt'))->toBe('Created At');
    expect($this->formatter->humanizeKey('firstName'))->toBe('First Name');
});

it('humanizes id to ID', function () {
    expect($this->formatter->humanizeKey('id'))->toBe('ID');
});

it('humanizes url to URL', function () {
    expect($this->formatter->humanizeKey('url'))->toBe('URL');
});

it('humanizes compound keys with abbreviations', function () {
    expect($this->formatter->humanizeKey('project_id'))->toBe('Project ID');
    expect($this->formatter->humanizeKey('base_url'))->toBe('Base URL');
    expect($this->formatter->humanizeKey('api_key'))->toBe('API Key');
});

// Simple objects (all scalar values)

it('formats simple objects as key-value lines', function () {
    $data = ['name' => 'John', 'email' => 'john@example.com', 'active' => true];

    $expected = <<<'TEXT'
Name: John
Email: john@example.com
Active: Yes
TEXT;

    expect($this->formatter->format($data))->toBe($expected);
});

it('formats simple object with null values', function () {
    $data = ['name' => 'John', 'nickname' => null];

    $expected = <<<'TEXT'
Name: John
Nickname: (empty)
TEXT;

    expect($this->formatter->format($data))->toBe($expected);
});

// Empty containers

it('formats empty array as empty list', function () {
    expect($this->formatter->format([]))->toBe('(empty list)');
});

// Arrays of scalars

it('formats array of strings as bullet list', function () {
    $data = ['apple', 'banana', 'cherry'];

    $expected = <<<'TEXT'
- apple
- banana
- cherry
TEXT;

    expect($this->formatter->format($data))->toBe($expected);
});

it('formats array of mixed scalars as bullet list', function () {
    $data = ['hello', 42, true, null];

    $expected = <<<'TEXT'
- hello
- 42
- Yes
- (empty)
TEXT;

    expect($this->formatter->format($data))->toBe($expected);
});

// Homogeneous object arrays (tables)

it('formats homogeneous object array as table', function () {
    $data = [
        ['id' => 1, 'name' => 'Foo'],
        ['id' => 2, 'name' => 'Bar'],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('| ID')
        ->toContain('| Name')
        ->toContain('| 1')
        ->toContain('| Foo')
        ->toContain('| 2')
        ->toContain('| Bar')
        ->toContain('| --');
});

it('formats table with proper column alignment', function () {
    $data = [
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'BB'],
    ];

    $lines = explode("\n", $this->formatter->format($data));

    // Header row
    expect($lines[0])->toContain('| ID');
    expect($lines[0])->toContain('| Name');

    // Separator row
    expect($lines[1])->toMatch('/^\|[\s\-|]+\|$/');

    // Data rows
    expect(count($lines))->toBe(4);
});

it('truncates long cell values in tables', function () {
    $data = [
        ['id' => 1, 'description' => str_repeat('A', 50)],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('...');
});

it('formats nested values in table cells as compact JSON', function () {
    $data = [
        ['id' => 1, 'tags' => ['api', 'test']],
        ['id' => 2, 'tags' => ['web']],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('["api","test"]');
});

// Wrapper patterns (data/meta sections)

it('formats wrapper pattern with data and meta sections', function () {
    $data = [
        'data' => [
            ['id' => 1, 'name' => 'Foo'],
            ['id' => 2, 'name' => 'Bar'],
        ],
        'meta' => [
            'total' => 2,
        ],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('# Data')
        ->toContain('| ID')
        ->toContain('| Foo')
        ->toContain('# Meta')
        ->toContain('Total: 2');
});

// Nested objects (heading hierarchy)

it('formats nested objects with heading hierarchy', function () {
    $data = [
        'project' => [
            'name' => 'Test',
            'metadata' => [
                'created' => '2024-01-01',
            ],
        ],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('# Project')
        ->toContain('## Name')
        ->toContain('Test')
        ->toContain('## Metadata')
        ->toContain('Created: 2024-01-01');
});

// Depth limit

it('falls back to JSON at depth limit', function () {
    $data = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'level4' => ['deep' => 'value'],
                ],
            ],
        ],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('{"deep":"value"}');
});

// Heterogeneous arrays

it('formats heterogeneous object arrays as numbered items', function () {
    $data = [
        ['id' => 1, 'name' => 'Foo'],
        ['id' => 2, 'name' => 'Bar', 'extra' => true],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('# Item 1')
        ->toContain('# Item 2')
        ->toContain('Name: Foo')
        ->toContain('Name: Bar')
        ->toContain('Extra: Yes');
});

// Mixed nested structures

it('formats object with mix of scalar and nested values', function () {
    $data = [
        'name' => 'Project',
        'settings' => [
            'debug' => true,
            'timeout' => 30,
        ],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('# Name')
        ->toContain('Project')
        ->toContain('# Settings')
        ->toContain('Debug: Yes')
        ->toContain('Timeout: 30');
});

it('formats single-item array as table', function () {
    $data = [
        ['id' => 1, 'name' => 'Only'],
    ];

    $result = $this->formatter->format($data);

    expect($result)->toContain('| ID')
        ->toContain('| Name')
        ->toContain('| 1')
        ->toContain('| Only');
});
