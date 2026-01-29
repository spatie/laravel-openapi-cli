# Laravel OpenAPI CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)

Create Laravel artisan commands from OpenAPI specifications. Execute HTTP requests directly from your terminal with full support for authentication, request bodies, file uploads, and more.

This package turns any OpenAPI spec (YAML or JSON) into interactive CLI commands, making it easy to test APIs, debug endpoints, and integrate API calls into your Laravel workflows.

```bash
# Register an OpenAPI spec as a command
php artisan api:flare projects

# Execute requests with parameters
php artisan api:flare projects/123/errors --query limit=10

# Post data as JSON
php artisan api:flare projects --field name="My Project" --field team_id=1

# Discover available endpoints
php artisan api:flare --list
```

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-openapi-cli.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-openapi-cli)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-openapi-cli
```

The package will automatically register its service provider.

You can optionally publish the config file:

```bash
php artisan vendor:publish --tag="laravel-openapi-cli-config"
```

## Usage

### Registering OpenAPI Specs

Register your OpenAPI specs in a service provider (typically `AppServiceProvider`):

```php
use Spatie\OpenApiCli\Facades\OpenApiCli;

public function boot()
{
    OpenApiCli::register(
        specFile: base_path('openapi/flare-api.yaml'),
        commandSignature: 'api:flare'
    )->baseUrl('https://flareapp.io/api');
}
```

This creates an artisan command `api:flare` that can execute any endpoint defined in your OpenAPI spec.

### Basic Requests

Execute GET requests by providing the endpoint path:

```bash
# Simple GET request
php artisan api:flare projects

# With path parameters
php artisan api:flare projects/123

# With query parameters
php artisan api:flare projects --query status=active&limit=10
```

### Sending Data

#### Form Fields

Use `--field` to send form data (auto-detects POST method):

```bash
php artisan api:flare projects --field name="My Project" --field team_id=1
```

#### JSON Input

Send raw JSON with `--input`:

```bash
php artisan api:flare projects --input '{"name":"My Project","settings":{"color":"blue"}}'
```

#### File Uploads

Upload files using the `@` prefix:

```bash
php artisan api:flare projects/123/sourcemaps --field file=@/path/to/sourcemap.js.map
```

Mix files with regular fields:

```bash
php artisan api:flare projects/123/logo --field file=@/path/to/logo.png --field alt="Company Logo"
```

### HTTP Methods

Specify the HTTP method explicitly:

```bash
# Explicit method
php artisan api:flare projects/123 --method DELETE

# Auto-detected from data
php artisan api:flare projects --field name="Test"  # Uses POST
```

### Authentication

#### Bearer Token

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api:service')
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'));
```

#### API Key

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api:service')
    ->baseUrl('https://api.example.com')
    ->apiKey('X-API-Key', env('API_KEY'));
```

#### Basic Auth

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api:service')
    ->baseUrl('https://api.example.com')
    ->basic('username', 'password');
```

#### Dynamic Authentication

Use a closure for dynamic token retrieval (e.g., from cache):

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api:service')
    ->baseUrl('https://api.example.com')
    ->auth(fn() => Cache::get('api_token'));
```

The closure is invoked fresh on each request, perfect for rotating tokens.

### Discovery Commands

#### List All Endpoints

```bash
php artisan api:flare --list
```

Output:
```
GET     /projects                    List all projects
POST    /projects                    Create a new project
GET     /projects/{projectId}        Get project details
DELETE  /projects/{projectId}        Delete a project
```

#### View Full Schema

```bash
php artisan api:flare --schema
```

Outputs the complete OpenAPI spec as pretty-printed JSON.

#### Endpoint Help

Get detailed information about a specific endpoint:

```bash
php artisan api:flare projects/{projectId} --help-endpoint
```

Shows:
- HTTP methods available
- Path parameters with types
- Request body schema
- Content-Type information

### Output Formatting

#### Pretty-Print (Default)

JSON responses are automatically formatted for readability:

```bash
php artisan api:flare projects/123
# {
#     "id": 123,
#     "name": "My Project"
# }
```

#### Minified Output

```bash
php artisan api:flare projects/123 --minify
# {"id":123,"name":"My Project"}
```

#### Include Headers

```bash
php artisan api:flare projects --include
# HTTP/1.1 200 OK
# Content-Type: application/json
# X-RateLimit-Remaining: 99
#
# {
#     "data": [...]
# }
```

### Error Handling

The command provides helpful error messages:

- **Endpoint not found**: Lists all available endpoints
- **Method not allowed**: Shows which methods are available for that endpoint
- **4xx/5xx errors**: Displays status code and response body
- **Network errors**: Shows connection failure details
- **Invalid spec**: Provides parsing errors with context

### Multiple Specs

Register multiple OpenAPI specs with different command names:

```php
OpenApiCli::register(base_path('openapi/flare.yaml'), 'api:flare')
    ->baseUrl('https://flareapp.io/api')
    ->bearer(env('FLARE_TOKEN'));

OpenApiCli::register(base_path('openapi/stripe.yaml'), 'api:stripe')
    ->baseUrl('https://api.stripe.com')
    ->bearer(env('STRIPE_KEY'));

OpenApiCli::register(base_path('openapi/github.yaml'), 'api:github')
    ->baseUrl('https://api.github.com')
    ->apiKey('Authorization', 'token '.env('GITHUB_TOKEN'));
```

## Command Reference

### Arguments

- `endpoint` - The API endpoint path (e.g., `projects`, `projects/123`)

### Options

- `--method=METHOD` - HTTP method (GET, POST, PUT, PATCH, DELETE)
- `--field=KEY=VALUE` - Form field (can be used multiple times)
- `--input=JSON` - Raw JSON input
- `--query=PARAMS` - Query string (e.g., `status=active&limit=10`)
- `--list` - List all available endpoints
- `--schema` - Output the full OpenAPI spec
- `--help-endpoint` - Show detailed help for a specific endpoint
- `--minify` - Minify JSON output
- `--include` - Include response headers in output

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
