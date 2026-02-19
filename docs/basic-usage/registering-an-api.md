---
title: Registering an API
weight: 1
---

Register your OpenAPI spec in a service provider (typically `AppServiceProvider`). You can use a local file path or a remote URL:

```php
use Spatie\OpenApiCli\Facades\OpenApiCli;

public function boot()
{
    OpenApiCli::register(base_path('openapi/bookstore-api.yaml'), 'bookstore')
        ->baseUrl('https://api.example-bookstore.com')
        ->bearer(env('BOOKSTORE_TOKEN'));
}
```

The second argument is the **namespace** - it groups all commands under a common prefix. For a spec with `GET /books`, `POST /books`, and `GET /books/{book_id}/reviews`, you get:

- `bookstore:get-books`
- `bookstore:post-books`
- `bookstore:get-books-reviews`
- `bookstore:list`

## Direct registration (no namespace)

If you omit the namespace, commands are registered directly without a prefix. This is useful for **Laravel Zero** CLI tools or any app where a single API is the primary interface:

```php
OpenApiCli::register(base_path('openapi/api.yaml'))
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'));
```

For the same spec, you get:

- `get-books`
- `post-books`
- `get-books-reviews`

Note: The `list` command is **not registered** when no namespace is set, since it would conflict with Laravel's built-in `list` command.

## Remote specs

You can register a spec directly from a URL. The spec is fetched via HTTP on every boot by default:

```php
OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->baseUrl('https://api.example.com')
    ->bearer(env('EXAMPLE_TOKEN'));
```

To enable caching (recommended for production), call `cache()`:

```php
OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->cache(); // cache for 60 seconds (default)
```

You can customize the TTL, cache store, and key prefix:

```php
OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->cache(ttl: 600); // 10 minutes

OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->cache(ttl: 600, store: 'redis', prefix: 'my-api:');
```
