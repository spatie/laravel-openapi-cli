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

## Aborting on authentication failure

When the `auth()` closure cannot produce a valid token — for example an OAuth refresh token turns out to be revoked — throw a `Spatie\OpenApiCli\Exceptions\AuthenticationException`. The command aborts with your message (and optional hint) before any HTTP request is sent:

```php
use Spatie\OpenApiCli\Exceptions\AuthenticationException;

OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->auth(function () {
        $token = app(OAuthTokenManager::class)->token();

        if ($token === null) {
            throw new AuthenticationException(
                'Your session has expired.',
                hint: 'Run `login` to authenticate again.',
            );
        }

        return $token;
    });
```

The exception may also be thrown from a `retryOn()` closure — the retry is abandoned and the response is not passed to `onError()`; the exception's message and hint replace the default error output. The command exits with a failure code either way.
