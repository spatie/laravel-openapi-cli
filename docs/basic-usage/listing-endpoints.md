---
title: Listing endpoints
weight: 5
---

Every namespaced API gets a `{namespace}:list` command:

```bash
php artisan bookstore:list
```

```
GET    bookstore:get-books                      List all books
POST   bookstore:post-books                     Add a new book
GET    bookstore:get-books-reviews              List reviews for a book
DELETE bookstore:delete-books                   Delete a book
POST   bookstore:post-books-cover               Upload a cover image
```

Note: The `list` command is only registered when a namespace is provided. Direct registrations (without a namespace) do not get a `list` command.

## Custom banner

Add a banner that displays above the endpoint list when running `{namespace}:list`. This is useful for branding, ASCII art logos, or contextual information.

### String banner

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->banner('Bookstore API v1.0');
```

### Callable banner

For full control over styling, pass a callable that receives the `Command` instance:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->banner(function ($command) {
        $command->info('=== My API ===');
        $command->comment('Environment: ' . app()->environment());
    });
```

The banner only appears in the `list` command output, not when running individual endpoint commands.
