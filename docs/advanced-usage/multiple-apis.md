---
title: Multiple APIs
weight: 2
---

Register as many specs as you need with different namespaces:

```php
OpenApiCli::register(base_path('openapi/bookstore.yaml'), 'bookstore')
    ->baseUrl('https://api.example-bookstore.com')
    ->bearer(env('BOOKSTORE_TOKEN'));

OpenApiCli::register(base_path('openapi/stripe.yaml'), 'stripe')
    ->baseUrl('https://api.stripe.com')
    ->bearer(env('STRIPE_KEY'));
```

## Base URL resolution

The base URL is resolved in this order:

1. The URL set via `->baseUrl()` on the registration
2. The first entry in the spec's `servers` array
3. If neither is available, the command throws an error
