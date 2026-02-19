---
title: Introduction
weight: 1
---

Turn any OpenAPI spec into dedicated Laravel artisan commands. Each endpoint gets its own command with typed options for path parameters, query parameters, and request bodies. Combined with [Laravel Zero](https://laravel-zero.com), this is a great way to build standalone CLI tools for any API that has an OpenAPI spec.

```php
use Spatie\OpenApiCli\Facades\OpenApiCli;

OpenApiCli::register('https://api.bookstore.io/openapi.yaml', 'bookstore')
    ->baseUrl('https://api.bookstore.io')
    ->bearer(env('BOOKSTORE_TOKEN'))
    ->banner('Bookstore API v2')
    ->cache(ttl: 600)
    ->followRedirects()
    ->yamlOutput()
    ->showHtmlBody()
    ->useOperationIds()
    ->onError(function (Response $response, Command $command) {
        return match ($response->status()) {
            429 => $command->warn('Rate limited. Retry after '.$response->header('Retry-After').'s.'),
            default => false,
        };
    });
```

For a spec with `GET /books`, `POST /books`, `GET /books/{book_id}/reviews`, and `DELETE /books/{book_id}`, you get these commands:

- `bookstore:get-books`
- `bookstore:post-books`
- `bookstore:get-books-reviews`
- `bookstore:delete-books`
- `bookstore:list`

List all endpoints:

```bash
php artisan bookstore:list
```
```
Bookstore API v2

GET    bookstore:get-books             List all books
POST   bookstore:post-books            Add a new book
GET    bookstore:get-books-reviews     List reviews for a book
DELETE bookstore:delete-books          Delete a book
```

Human-readable output (default):

```bash
php artisan bookstore:get-books --limit=2
```
```
# Data

| id | title                    | author          |
|----|--------------------------|-----------------|
| 1  | The Great Gatsby         | F. Fitzgerald   |
| 2  | To Kill a Mockingbird    | Harper Lee      |

# Meta

total: 2
```

YAML output:

```bash
php artisan bookstore:get-books --limit=2 --yaml
```
```yaml
data:
  -
    id: 1
    title: 'The Great Gatsby'
    author: 'F. Fitzgerald'
  -
    id: 2
    title: 'To Kill a Mockingbird'
    author: 'Harper Lee'
meta:
  total: 2
```

## We got badges

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)
