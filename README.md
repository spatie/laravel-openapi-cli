# Create Laravel commands for your OpenAPI specs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)

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

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-openapi-cli.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-openapi-cli)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Documentation

All documentation is available [on our documentation site](https://spatie.be/docs/laravel-openapi-cli).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Alex Vanderbist](https://github.com/AlexVanderbist)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
