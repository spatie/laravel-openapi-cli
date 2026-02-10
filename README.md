# Laravel OpenAPI CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)

Turn any OpenAPI spec into dedicated Laravel artisan commands. Each endpoint gets its own command with typed options for path parameters, query parameters, and request bodies.

```bash
# List all available commands for an API
php artisan flare:list

# GET request
php artisan flare:get-projects

# GET with path parameters
php artisan flare:get-projects-errors --project-id=123

# POST with JSON fields
php artisan flare:post-projects --field name="My Project" --field team_id=1

# Include response headers
php artisan flare:get-projects --include
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

### Registering an API

Register your OpenAPI spec in a service provider (typically `AppServiceProvider`):

```php
use Spatie\OpenApiCli\Facades\OpenApiCli;

public function boot()
{
    OpenApiCli::register(base_path('openapi/flare-api.yaml'), 'flare')
        ->baseUrl('https://flareapp.io/api')
        ->bearer(env('FLARE_TOKEN'));
}
```

This reads the spec and registers one artisan command per endpoint. For a spec with `GET /projects`, `POST /projects`, and `GET /projects/{project_id}/errors`, you get:

- `flare:get-projects`
- `flare:post-projects`
- `flare:get-projects-errors`
- `flare:list`

### Command naming

Commands are named `{prefix}:{method}-{path}` where path parameters are stripped:

| Method | Path | Command |
|--------|------|---------|
| GET | `/projects` | `flare:get-projects` |
| POST | `/projects` | `flare:post-projects` |
| GET | `/projects/{project_id}/errors` | `flare:get-projects-errors` |
| DELETE | `/teams/{team_id}/users/{user_id}` | `flare:delete-teams-users` |

When two paths would produce the same command name (e.g. `/projects` and `/projects/{id}` both yield `get-projects`), the trailing path parameter is appended to disambiguate:

| Path | Command |
|------|---------|
| `/projects` | `flare:get-projects` |
| `/projects/{id}` | `flare:get-projects-id` |

### Path parameters

Path parameters become required `--options`:

```bash
php artisan flare:get-projects-errors --project-id=123
php artisan flare:delete-teams-users --team-id=1 --user-id=2
```

Parameter names are converted to kebab-case (`project_id` becomes `--project-id`, `projectId` becomes `--project-id`).

### Query parameters

Query parameters defined in the spec become optional `--options`:

```bash
# If the spec defines ?status and ?limit query params for GET /projects
php artisan flare:get-projects --status=active --limit=10
```

Query parameters named `include` are automatically renamed to `--include-param` to avoid conflicting with the built-in `--include` flag.

### Sending data

#### Form fields

Use `--field` to send key-value data:

```bash
php artisan flare:post-projects --field name="My Project" --field team_id=1
```

Fields are sent as JSON by default. If the spec declares `application/x-www-form-urlencoded` as the content type, fields are sent as form data instead.

#### JSON input

Send raw JSON with `--input`:

```bash
php artisan flare:post-projects --input '{"name":"My Project","settings":{"color":"blue"}}'
```

`--field` and `--input` cannot be used together.

#### File uploads

Upload files using the `@` prefix on field values:

```bash
php artisan flare:post-upload --field file=@/path/to/document.pdf
php artisan flare:post-upload --field file=@/path/to/logo.png --field alt="Company Logo"
```

When any field contains a file, the request is sent as `multipart/form-data`.

### Listing endpoints

Every registered API gets a `{prefix}:list` command:

```bash
php artisan flare:list
```

```
GET    flare:get-me                            Get the authenticated user.
GET    flare:get-projects                      List all projects
POST   flare:post-projects                     Create a new project
GET    flare:get-projects-errors               List errors for a project
DELETE flare:delete-projects-errors             Delete all errors for a project
```

### Authentication

#### Bearer token

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'));
```

#### API key header

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->apiKey('X-API-Key', env('API_KEY'));
```

#### Basic auth

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->basic('username', 'password');
```

#### Dynamic authentication

Use a closure for tokens that may rotate or need to be fetched dynamically:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->auth(fn () => Cache::get('api_token'));
```

The closure is called fresh on each request.

### Output formatting

#### Pretty-print (default)

```bash
php artisan flare:get-projects
# {
#     "data": [
#         { "id": 1, "name": "My Project" }
#     ]
# }
```

#### Minified output

```bash
php artisan flare:get-projects --minify
# {"data":[{"id":1,"name":"My Project"}]}
```

#### Response headers

```bash
php artisan flare:get-projects --include
# HTTP/1.1 200 OK
# Content-Type: application/json
# X-RateLimit-Remaining: 99
#
# {
#     "data": [...]
# }
```

### Operation ID mode

By default, commands are named from the URL path. If your spec includes `operationId` fields, you can use those instead:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->useOperationIds();
```

With `operationId: listProjects` in the spec, the command becomes `api:list-projects` instead of `api:get-projects`. Endpoints without an `operationId` fall back to path-based naming.

### Error handling

- **4xx/5xx errors**: Displays the status code and response body. JSON responses are pretty-printed (or minified with `--minify`). Non-JSON responses show the raw body with a content-type notice.
- **Network errors**: Shows connection failure details.
- **Missing path parameters**: Tells you which `--option` is required.
- **Invalid JSON input**: Shows the parse error.

All error cases exit with a non-zero code for scripting.

### Multiple APIs

Register as many specs as you need with different prefixes:

```php
OpenApiCli::register(base_path('openapi/flare.yaml'), 'flare')
    ->baseUrl('https://flareapp.io/api')
    ->bearer(env('FLARE_TOKEN'));

OpenApiCli::register(base_path('openapi/stripe.yaml'), 'stripe')
    ->baseUrl('https://api.stripe.com')
    ->bearer(env('STRIPE_KEY'));
```

### Base URL resolution

The base URL is resolved in this order:

1. The URL set via `->baseUrl()` on the registration
2. The first entry in the spec's `servers` array
3. If neither is available, the command throws an error

## Command reference

Every endpoint command supports these universal options:

| Option | Description |
|--------|-------------|
| `--field=key=value` | Send a form field (repeatable) |
| `--input=JSON` | Send raw JSON body |
| `--minify` | Minify JSON output |
| `--include` | Include response headers in output |

Path and query parameter options are generated from the spec and shown in each command's `--help` output.

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
