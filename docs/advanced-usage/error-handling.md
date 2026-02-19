---
title: Error handling
weight: 1
---

## Default error handling

- **4xx/5xx errors**: Displays the status code and response body in human-readable format by default (or JSON with `--json`, minified with `--minify`). HTML responses suppress the body by default (use `--output-html` to see it). Other non-JSON responses show the raw body with a content-type notice.
- **Network errors**: Shows connection failure details.
- **Missing path parameters**: Tells you which `--option` is required.
- **Invalid JSON input**: Shows the parse error.

All error cases exit with a non-zero code for scripting.

## Custom error handling

Register an `onError` callback to handle HTTP errors in a way that's specific to your API:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'))
    ->onError(function (Response $response, Command $command) {
        return match ($response->status()) {
            403 => $command->error('Your API token lacks permission for this endpoint.'),
            429 => $command->warn('Rate limited. Try again in ' . $response->header('Retry-After') . 's.'),
            500 => $command->error('Server error — try again later.'),
            default => false, // fall through to default handling
        };
    });
```

The callback receives the `Illuminate\Http\Client\Response` and the `Illuminate\Console\Command` instance, giving you access to all Artisan output methods (`line()`, `info()`, `warn()`, `error()`, `table()`, `newLine()`, etc.).

Return a truthy value to indicate "handled" — this suppresses the default "HTTP {code} Error" output. Return `false` or `null` to fall through to default error handling. The command always exits with a non-zero code regardless of whether the callback handles the error.

## Redirect handling

By default, HTTP redirects are **not followed**. This means a `301` or `302` response is returned as-is, so you can see exactly what the API responds with. To opt in to following redirects:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->followRedirects();
```
