---
title: Command naming
weight: 2
---

Commands are named `{namespace}:{method}-{path}` where path parameters are stripped. Without a namespace, the command is just `{method}-{path}`:

| Method | Path | Command (namespaced) | Command (direct) |
|--------|------|----------------------|-------------------|
| GET | `/books` | `bookstore:get-books` | `get-books` |
| POST | `/books` | `bookstore:post-books` | `post-books` |
| GET | `/books/{book_id}/reviews` | `bookstore:get-books-reviews` | `get-books-reviews` |
| DELETE | `/authors/{author_id}/books/{book_id}` | `bookstore:delete-authors-books` | `delete-authors-books` |

When two paths would produce the same command name (e.g. `/books` and `/books/{id}` both yield `get-books`), the trailing path parameter is appended to disambiguate:

| Path | Command (namespaced) | Command (direct) |
|------|----------------------|-------------------|
| `/books` | `bookstore:get-books` | `get-books` |
| `/books/{id}` | `bookstore:get-books-id` | `get-books-id` |

## Operation ID mode

If your spec includes `operationId` fields, you can use those for command names instead of the URL path:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->useOperationIds();
```

With `operationId: listBooks` in the spec, the command becomes `api:list-books` instead of `api:get-books`. Endpoints without an `operationId` fall back to path-based naming.
