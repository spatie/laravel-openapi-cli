---
title: Sending data
weight: 4
---

## Form fields

Use `--field` to send key-value data:

```bash
php artisan bookstore:post-books --field title="The Great Gatsby" --field author_id=1
```

Fields are sent as JSON by default. If the spec declares `application/x-www-form-urlencoded` as the content type, fields are sent as form data instead.

## JSON input

Send raw JSON with `--input`:

```bash
php artisan bookstore:post-books --input '{"title":"The Great Gatsby","metadata":{"genre":"fiction","year":1925}}'
```

`--field` and `--input` cannot be used together.

## File uploads

Upload files using the `@` prefix on field values:

```bash
php artisan bookstore:post-books-cover --book-id=42 --field cover=@/path/to/cover.jpg
php artisan bookstore:post-books-cover --book-id=42 --field cover=@/path/to/cover.jpg --field alt="Book Cover"
```

When any field contains a file, the request is sent as `multipart/form-data`.
