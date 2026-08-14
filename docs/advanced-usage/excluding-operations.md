---
title: Excluding operations
weight: 5
---

Every operation in the spec becomes a command. Use `exclude()` to leave one out, so you can ship a hand-written command for it instead.

```php
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->baseUrl('https://api.example.com')
    ->exclude('download-file');
```

The name you pass is the command name without the namespace, exactly as it would have been registered. Exclusions are matched after disambiguation, so pass `get-books-id` rather than `get-books` if that is the name the command would have ended up with.

Pass an array to exclude several at once:

```php
->exclude(['download-file', 'upload-file'])
```

Or a callable, which receives the method, the path, and the operation data:

```php
->exclude(fn (string $method, string $path, array $operation) => str_starts_with($path, '/internal'));
```

## Why you would

Generated commands format their response for reading: JSON is pretty-printed, and anything else comes back behind a `Response is not JSON (content-type: ..., status: ...)` line. That is what you want for an API you are exploring, and wrong for an endpoint that returns a file, where the caller wants to redirect the output into something they can open.

Excluding the operation frees the name, so a command of your own can take it:

```php
// In your service provider
OpenApiCli::register(base_path('openapi/api.yaml'), 'api')
    ->exclude('download-file');
```

```php
namespace App\Commands;

class DownloadFileCommand extends Command
{
    protected $signature = 'api:download-file {--file=}';

    // Stream the body straight to stdout, so `> report.pdf` produces a valid file.
}
```

Without the exclusion both commands register under the same name and the last one registered silently wins, which is not something to rely on.
