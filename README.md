# Create Laravel commands for your OpenAPI specs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-openapi-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/spatie/laravel-openapi-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-openapi-cli.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-openapi-cli)

Turn any OpenAPI spec into dedicated Laravel artisan commands. Each endpoint gets its own command with typed options for path parameters, query parameters, and request bodies.

```php
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

List all endpoints:

```bash
php artisan bookstore:list
```
```
Bookstore API v2

GET    bookstore:get-books             List all books
POST   bookstore:post-books            Add a new book
GET    bookstore:get-books-reviews     List reviews for a book
DELETE bookstore:delete-books          Delete a book
```

Human-readable output (default):

```bash
php artisan bookstore:get-books --limit=2
```
```
# Data

| id | title                    | author          |
|----|--------------------------|-----------------|
| 1  | The Great Gatsby         | F. Fitzgerald   |
| 2  | To Kill a Mockingbird    | Harper Lee      |

# Meta

total: 2
```

YAML output:

```bash
php artisan bookstore:get-books --limit=2 --yaml
```
```yaml
data:
  -
    id: 1
    title: 'The Great Gatsby'
    author: 'F. Fitzgerald'
  -
    id: 2
    title: 'To Kill a Mockingbird'
    author: 'Harper Lee'
meta:
  total: 2
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

## Usage

### Registering an API

Register your OpenAPI spec in a service provider (typically `AppServiceProvider`). You can use a local file path or a remote URL:

```php
use Spatie\OpenApiCli\Facades\OpenApiCli;

public function boot()
{
    OpenApiCli::register(base_path('openapi/bookstore-api.yaml'), 'bookstore')
        ->baseUrl('https://api.example-bookstore.com')
        ->bearer(env('BOOKSTORE_TOKEN'));
}
```

The second argument is the **namespace** - it groups all commands under a common prefix. For a spec with `GET /books`, `POST /books`, and `GET /books/{book_id}/reviews`, you get:

- `bookstore:get-books`
- `bookstore:post-books`
- `bookstore:get-books-reviews`
- `bookstore:list`

### Direct registration (no namespace)

If you omit the namespace, commands are registered directly without a prefix. This is useful for **Laravel Zero** CLI tools or any app where a single API is the primary interface:

```php
OpenApiCli::register(base_path('openapi/api.yaml'))
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'));
```

For the same spec, you get:

- `get-books`
- `post-books`
- `get-books-reviews`

Note: The `list` command is **not registered** when no namespace is set, since it would conflict with Laravel's built-in `list` command.

### Remote specs

You can register a spec directly from a URL. The spec is fetched via HTTP on every boot by default:

```php
OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->baseUrl('https://api.example.com')
    ->bearer(env('EXAMPLE_TOKEN'));
```

To enable caching (recommended for production), call `cache()`:

```php
OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->cache(); // cache for 60 seconds (default)
```

You can customize the TTL, cache store, and key prefix:

```php
OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->cache(ttl: 600); // 10 minutes

OpenApiCli::register('https://api.example.com/openapi.yaml', 'example')
    ->cache(ttl: 600, store: 'redis', prefix: 'my-api:');
```

### Command naming

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

#### Operation ID mode

If your spec includes `operationId` fields, you can use those for command names instead of the URL path:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->useOperationIds();
```

With `operationId: listBooks` in the spec, the command becomes `api:list-books` instead of `api:get-books`. Endpoints without an `operationId` fall back to path-based naming.

### Path parameters

Path parameters become required `--options`:

```bash
php artisan bookstore:get-books-reviews --book-id=42
php artisan bookstore:delete-authors-books --author-id=1 --book-id=7
```

Parameter names are converted to kebab-case (`book_id` becomes `--book-id`, `bookId` becomes `--book-id`).

### Query parameters

Query parameters defined in the spec become optional `--options`:

```bash
# If the spec defines ?genre and ?limit query params for GET /books
php artisan bookstore:get-books --genre=fiction --limit=10
```

Bracket notation is converted to kebab-case (e.g. `filter[id]` becomes `--filter-id`).

### Sending data

#### Form fields

Use `--field` to send key-value data:

```bash
php artisan bookstore:post-books --field title="The Great Gatsby" --field author_id=1
```

Fields are sent as JSON by default. If the spec declares `application/x-www-form-urlencoded` as the content type, fields are sent as form data instead.

#### JSON input

Send raw JSON with `--input`:

```bash
php artisan bookstore:post-books --input '{"title":"The Great Gatsby","metadata":{"genre":"fiction","year":1925}}'
```

`--field` and `--input` cannot be used together.

#### File uploads

Upload files using the `@` prefix on field values:

```bash
php artisan bookstore:post-books-cover --book-id=42 --field cover=@/path/to/cover.jpg
php artisan bookstore:post-books-cover --book-id=42 --field cover=@/path/to/cover.jpg --field alt="Book Cover"
```

When any field contains a file, the request is sent as `multipart/form-data`.

### Listing endpoints

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

### Custom banner

Add a banner that displays above the endpoint list when running `{namespace}:list`. This is useful for branding, ASCII art logos, or contextual information.

#### String banner

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->banner('Bookstore API v1.0');
```

#### Callable banner

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

#### Human-readable (default)

```bash
php artisan bookstore:get-books
# # Data
#
# | ID | Title             |
# |----|-------------------|
# | 1  | The Great Gatsby  |
#
# # Meta
#
# Total: 1
```

JSON responses are automatically converted into readable markdown-style output: tables for arrays of objects, key-value lines for simple objects, and section headings for wrapper patterns like `{"data": [...], "meta": {...}}`.

#### JSON output

```bash
php artisan bookstore:get-books --json
# {
#     "data": [
#         { "id": 1, "title": "The Great Gatsby" }
#     ]
# }
```

The `--json` flag outputs raw JSON (pretty-printed by default).

#### YAML output

```bash
php artisan bookstore:get-books --yaml
# data:
#   -
#     id: 1
#     title: 'The Great Gatsby'
```

The `--yaml` flag converts JSON responses to YAML. If `--json` or `--minify` is also passed, those take priority.

#### Minified output

```bash
php artisan bookstore:get-books --minify
# {"data":[{"id":1,"title":"The Great Gatsby"}]}
```

The `--minify` flag implies `--json` — no need to pass both.

#### YAML as default

If you prefer YAML output as the default for a specific registration, use the `yamlOutput()` config method:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->yamlOutput();
```

With `yamlOutput()`, commands output YAML by default. The `--json` and `--minify` flags override this and produce JSON output instead.

#### JSON as default

If you prefer JSON output as the default for a specific registration, use the `jsonOutput()` config method:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->jsonOutput();
```

With `jsonOutput()`, commands output pretty-printed JSON by default. The `--json` and `--minify` flags still work as expected.

#### Response headers

```bash
php artisan bookstore:get-books --headers
# HTTP/1.1 200 OK
# Content-Type: application/json
# X-RateLimit-Remaining: 99
#
# # Data
#
# | ID | Title             |
# |----|-------------------|
# | 1  | The Great Gatsby  |
```

#### HTML responses

When an API returns HTML (e.g., an error page), the body is hidden by default to avoid flooding the terminal. You'll see a hint instead:

```
Response is not JSON (content-type: text/html, status: 500, content-length: 1234)

Use --output-html to see the full response body.
```

Pass `--output-html` to show the body:

```bash
php artisan bookstore:get-books --output-html
```

To always show HTML bodies for a specific registration, use the `showHtmlBody()` config method:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->showHtmlBody();
```

Non-HTML non-JSON responses (e.g., `text/plain`) are always shown in full.

#### Syntax highlighting

JSON and human-readable output are syntax-highlighted by default when running in a terminal. JSON output gets keyword/value coloring via [tempest/highlight](https://github.com/tempestphp/highlight), and human-readable output gets colored headings, keys, and table formatting.

To disable highlighting (e.g. when piping output), use the built-in `--no-ansi` flag:

```bash
php artisan bookstore:get-books --no-ansi
php artisan bookstore:get-books --json --no-ansi
```

Highlighting is automatically disabled when output is not a TTY (e.g. piped to a file or another command).

### Redirect handling

By default, HTTP redirects are **not followed**. This means a `301` or `302` response is returned as-is, so you can see exactly what the API responds with. To opt in to following redirects:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->followRedirects();
```

### Error handling

- **4xx/5xx errors**: Displays the status code and response body in human-readable format by default (or JSON with `--json`, minified with `--minify`). HTML responses suppress the body by default (use `--output-html` to see it). Other non-JSON responses show the raw body with a content-type notice.
- **Network errors**: Shows connection failure details.
- **Missing path parameters**: Tells you which `--option` is required.
- **Invalid JSON input**: Shows the parse error.

All error cases exit with a non-zero code for scripting.

### Custom error handling

Register an `onError` callback to handle HTTP errors in a way that's specific to your API:

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->bearer(env('API_TOKEN'))
    ->onError(function (Response $response, Command $command) {
        return match ($response->status()) {
            403 => $command->error('Your API token lacks permission for this endpoint.'),
            429 => $command->warn('Rate limited. Try again in ' . $response->header('Retry-After') . 's.'),
            500 => $command->error('Server error — try again later.'),
            default => false, // fall through to default handling
        };
    });
```

The callback receives the `Illuminate\Http\Client\Response` and the `Illuminate\Console\Command` instance, giving you access to all Artisan output methods (`line()`, `info()`, `warn()`, `error()`, `table()`, `newLine()`, etc.).

Return a truthy value to indicate "handled" — this suppresses the default "HTTP {code} Error" output. Return `false` or `null` to fall through to default error handling. The command always exits with a non-zero code regardless of whether the callback handles the error.

### Multiple APIs

Register as many specs as you need with different namespaces:

```php
OpenApiCli::register(base_path('openapi/bookstore.yaml'), 'bookstore')
    ->baseUrl('https://api.example-bookstore.com')
    ->bearer(env('BOOKSTORE_TOKEN'));

OpenApiCli::register(base_path('openapi/stripe.yaml'), 'stripe')
    ->baseUrl('https://api.stripe.com')
    ->bearer(env('STRIPE_KEY'));
```

### Base URL resolution

The base URL is resolved in this order:

1. The URL set via `->baseUrl()` on the registration
2. The first entry in the spec's `servers` array
3. If neither is available, the command throws an error

### Debugging

Pass `-vvv` to any command to see the full request before it's sent:

```bash
php artisan bookstore:get-books -vvv
```

```
  Request
  -------
  GET https://api.example-bookstore.com/books

  Request Headers
  ---------------
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer sk-abc...
```

This shows the HTTP method, resolved URL, request headers (including authentication), and request body when present.

## Command reference

Every endpoint command supports these universal options:

| Option | Description |
|--------|-------------|
| `--field=key=value` | Send a form field (repeatable) |
| `--input=JSON` | Send raw JSON body |
| `--json` | Output raw JSON instead of human-readable format |
| `--yaml` | Output as YAML |
| `--minify` | Minify JSON output (implies `--json`) |
| `-H`, `--headers` | Include response headers in output |
| `--output-html` | Show the full response body when content-type is text/html |

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
