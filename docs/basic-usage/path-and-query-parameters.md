---
title: Path & query parameters
weight: 3
---

## Path parameters

Path parameters become required `--options`:

```bash
php artisan bookstore:get-books-reviews --book-id=42
php artisan bookstore:delete-authors-books --author-id=1 --book-id=7
```

Parameter names are converted to kebab-case (`book_id` becomes `--book-id`, `bookId` becomes `--book-id`).

## Query parameters

Query parameters defined in the spec become optional `--options`:

```bash
# If the spec defines ?genre and ?limit query params for GET /books
php artisan bookstore:get-books --genre=fiction --limit=10
```

Bracket notation is converted to kebab-case (e.g. `filter[id]` becomes `--filter-id`).
