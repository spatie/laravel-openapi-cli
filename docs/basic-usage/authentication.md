---
title: Authentication
weight: 6
---

## Bearer token

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'));
```

## API key header

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->apiKey('X-API-Key', env('API_KEY'));
```

## Basic auth

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->basic('username', 'password');
```

## Dynamic authentication

Use a closure for tokens that may rotate or need to be fetched dynamically:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->auth(fn () => Cache::get('api_token'));
```

The closure is called fresh on each request.
