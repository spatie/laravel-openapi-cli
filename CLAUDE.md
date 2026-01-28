# Project Context for AI Agents

## Package Type
Laravel package (not an app) - maintains Spatie package skeleton structure.

## Commands
- **Test**: `composer test` (uses Pest)
- **Format**: `composer format` (uses Pint)
- **Analyze**: `composer analyse` (uses PHPStan)
- **Coverage**: `composer test-coverage`

## Key Files & Structure
- **Source**: `src/` - all package code
- **Tests**: `tests/` - Pest tests (Unit/ and Feature/ subdirs)
- **Config**: `config/openapi-cli.php` - package configuration
- **Test fixture**: `flare-api.yaml` - use this for OpenAPI-related tests
- **Service Provider**: `src/OpenApiCliServiceProvider.php` - extends PackageServiceProvider
- **Facade**: `src/Facades/OpenApiCli.php`

## Package Conventions
- Namespace: `Spatie\OpenApiCli`
- Follow Spatie package conventions (uses laravel-package-tools)
- Keep existing directory structure intact
- No migrations or views needed for this package

## Dependencies
- PHP 8.2+ (composer.json shows 8.4, may need adjustment)
- Laravel 10+
- `symfony/yaml` required (add to composer.json)
- Laravel HTTP Client for requests
- Pest for testing, Pint for formatting, PHPStan for static analysis

## Testing
- Use `Http::fake()` to mock HTTP requests in tests
- Use `flare-api.yaml` as primary test fixture
- Organize tests: `tests/Unit/` for unit tests, `tests/Feature/` for feature tests
- All tests must pass typecheck and formatting

## Quality Checks (MUST PASS)
Before committing any code, run ALL of these:
1. `composer analyse` - PHPStan static analysis (MUST pass with zero errors)
2. `composer format` - Pint code formatting (MUST pass)
3. `composer test` - Pest test suite (MUST pass all tests)

## Implementation Notes
- Use Laravel HTTP Client (`Http::` facade), not Guzzle directly
- Don't remove or restructure existing skeleton files
- All code must pass all quality checks before committing
- Aim for 90%+ test coverage
