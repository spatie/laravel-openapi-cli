# Changelog

All notable changes to `laravel-openapi-cli` will be documented in this file.

## 1.6.0 - 2026-11-06

### Symfony YAML 8 support

- Upgrade the runtime dependency from Symfony YAML 7 to `^8.1` (#14).
- Require PHP 8.4.1 or newer and Laravel 13, with Testbench 11 for development. Laravel 11, Laravel 12 and PHP 8.4.0 are no longer supported; applications needing those versions can remain on 1.5.0. These support-floor changes follow the maintainer's non-major release policy.
- YAML mapping keys must be unique, including when the earlier value is `null`. Such duplicates now raise the package's `InvalidArgumentException`; a public parser regression covers this upstream YAML 8 change. Valid specs and existing JSON/YAML output options remain supported. Empty maps are formatted as `{}` instead of `{  }`, without changing their values.
- Synchronize requirements, CI and installation documentation, and validate Composer metadata, platform requirements and security advisories in all test lanes.

### Maintenance

- Include the separately completed PHP 8.6 CI/Pest compatibility work (#15), retaining PHP 8.4–8.6, Linux/Windows and lowest/stable test coverage for Laravel 13.
- Preserve the Spatie-hosted README assets.

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.5.0...1.6.0

## 1.5.0 - 2026-08-14

### Non-JSON response bodies are written through untouched

A response body that could not be pretty-printed used to get a `Response is not JSON (content-type: ..., status: ...)` line, a blank line, a trailing newline, and a pass through Symfony's output formatter. All of that landed in the file when the output was redirected, so an endpoint returning a file could not be saved:

```bash
php artisan api:download-report > report.pdf   # was corrupt, now valid


```
The body is now written raw: no notice, no added newline, no formatting. Any `<...>` sequence in binary data survives instead of being read as a style tag.

The one remaining notice is for an HTML body you have not asked to see, which prints no body at all. Its wording changed to `HTML response (status: 500, content-length: 1234). Use --output-html to see it.`

If you relied on the old notice appearing in output, that is the breaking bit; everything else is unchanged.

## 1.4.1 - 2026-08-07

### What's Changed

* Add Laravel 13 support by @kayterra in https://github.com/spatie/laravel-openapi-cli/pull/8

### New Contributors

* @kayterra made their first contribution in https://github.com/spatie/laravel-openapi-cli/pull/8

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.4.0...1.4.1

## 1.4.0 - 2026-07-20

- Add `AuthenticationException` that `auth()` and `retryOn()` closures can throw to abort a command with a friendly message (and optional hint) before a request is sent

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.3.1...1.4.0

## 1.3.1 - 2026-06-26

### What's Changed

* Fix options being dropped when a parameter description is multi-line by @enricodelazzari in https://github.com/spatie/laravel-openapi-cli/pull/9

### New Contributors

* @enricodelazzari made their first contribution in https://github.com/spatie/laravel-openapi-cli/pull/9

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.3.0...1.3.1

## 1.3.0 - 2026-05-22

- Add `retryOn()` hook to re-issue a request after a >= 400 response (e.g. refresh an expired OAuth token on 401 and retry once)

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.2.0...1.3.0

## 1.2.0 - 2026-04-28

### What's Changed

* Extract EndpointResolver to remove duplication by @freekmurze in https://github.com/spatie/laravel-openapi-cli/pull/7

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.1.0...1.2.0

## 1.1.0 - 2026-04-28

### What's Changed

* build(deps): Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/spatie/laravel-openapi-cli/pull/4
* build(deps): Bump dependabot/fetch-metadata from 2.5.0 to 3.0.0 by @dependabot[bot] in https://github.com/spatie/laravel-openapi-cli/pull/5
* build(deps): Bump dependabot/fetch-metadata from 3.0.0 to 3.1.0 by @dependabot[bot] in https://github.com/spatie/laravel-openapi-cli/pull/6
* Merge path-level parameters into operation parameters by @zupolgec in https://github.com/spatie/laravel-openapi-cli/pull/3

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/spatie/laravel-openapi-cli/pull/4
* @zupolgec made their first contribution in https://github.com/spatie/laravel-openapi-cli/pull/3

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.0.2...1.1.0

## 1.0.2 - 2026-02-20

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.0.1...1.0.2

## 1.0.1 - 2026-02-20

- fix query parameters with invalid characters

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/1.0.0...1.0.1

## 1.0.0 - 2026-02-18

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/compare/0.0.1...1.0.0

## 0.0.1 - 2026-02-11

**Full Changelog**: https://github.com/spatie/laravel-openapi-cli/commits/0.0.1
