---
title: Output formatting
weight: 7
---

## Human-readable (default)

```bash
php artisan bookstore:get-books
# # Data
#
# | ID | Title             |
# |----|-------------------|
# | 1  | The Great Gatsby  |
#
# # Meta
#
# Total: 1
```

JSON responses are automatically converted into readable markdown-style output: tables for arrays of objects, key-value lines for simple objects, and section headings for wrapper patterns like `{"data": [...], "meta": {...}}`.

## JSON output

```bash
php artisan bookstore:get-books --json
# {
#     "data": [
#         { "id": 1, "title": "The Great Gatsby" }
#     ]
# }
```

The `--json` flag outputs raw JSON (pretty-printed by default).

## YAML output

```bash
php artisan bookstore:get-books --yaml
# data:
#   -
#     id: 1
#     title: 'The Great Gatsby'
```

The `--yaml` flag converts JSON responses to YAML. If `--json` or `--minify` is also passed, those take priority.

## Minified output

```bash
php artisan bookstore:get-books --minify
# {"data":[{"id":1,"title":"The Great Gatsby"}]}
```

The `--minify` flag implies `--json` — no need to pass both.

## YAML as default

If you prefer YAML output as the default for a specific registration, use the `yamlOutput()` config method:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->yamlOutput();
```

With `yamlOutput()`, commands output YAML by default. The `--json` and `--minify` flags override this and produce JSON output instead.

## JSON as default

If you prefer JSON output as the default for a specific registration, use the `jsonOutput()` config method:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->jsonOutput();
```

With `jsonOutput()`, commands output pretty-printed JSON by default. The `--json` and `--minify` flags still work as expected.

## Response headers

```bash
php artisan bookstore:get-books --headers
# HTTP/1.1 200 OK
# Content-Type: application/json
# X-RateLimit-Remaining: 99
#
# # Data
#
# | ID | Title             |
# |----|-------------------|
# | 1  | The Great Gatsby  |
```

## HTML responses

When an API returns HTML (e.g., an error page), the body is hidden by default to avoid flooding the terminal. You'll see a hint instead:

```
HTML response (status: 500, content-length: 1234). Use --output-html to see it.
```

Pass `--output-html` to show the body:

```bash
php artisan bookstore:get-books --output-html
```

## Binary and other non-JSON responses

Anything else that isn't JSON is written straight through: no notice, no trailing newline, and no formatting. So an endpoint that returns a file can be redirected into one:

```bash
php artisan bookstore:get-book-cover --book=1 > cover.png
```

To always show HTML bodies for a specific registration, use the `showHtmlBody()` config method:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->showHtmlBody();
```

Non-HTML non-JSON responses (e.g., `text/plain`) are always shown in full.

## Syntax highlighting

JSON and human-readable output are syntax-highlighted by default when running in a terminal. JSON output gets keyword/value coloring via [tempest/highlight](https://github.com/tempestphp/highlight), and human-readable output gets colored headings, keys, and table formatting.

To disable highlighting (e.g. when piping output), use the built-in `--no-ansi` flag:

```bash
php artisan bookstore:get-books --no-ansi
php artisan bookstore:get-books --json --no-ansi
```

Highlighting is automatically disabled when output is not a TTY (e.g. piped to a file or another command).
