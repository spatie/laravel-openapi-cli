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

### Testing Patterns

This package uses Orchestra Testbench with a custom TestCase that provides automatic command registration. Follow these patterns to write reliable tests:

#### Feature Test Structure

Feature tests should follow this pattern:

```php
use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    // CRITICAL: Always clear registrations to prevent test pollution
    OpenApiCli::clearRegistrations();

    // Create your test spec file
    $this->specFile = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';
    file_put_contents($this->specFile, '...');
});

afterEach(function () {
    // Clean up temp files
    if (file_exists($this->specFile)) {
        unlink($this->specFile);
    }
});

it('does something', function () {
    // 1. Setup HTTP fakes FIRST (before registration)
    Http::fake([
        'https://api.example.com/*' => Http::response(['data' => 'value'], 200),
    ]);

    // 2. Register the command with the spec
    OpenApiCli::register($this->specFile, 'test-command')
        ->baseUrl('https://api.example.com');

    // 3. Execute the command using artisan()
    // IMPORTANT: Always chain assertions immediately - NEVER assign to a variable
    $this->artisan('test-command endpoint')
        ->assertSuccessful()
        ->expectsOutputToContain('expected output');

    // 4. Verify HTTP requests were made
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/endpoint';
    });
});
```

#### Critical Testing Rules

1. **Always call `OpenApiCli::clearRegistrations()` in `beforeEach()`**
   - Prevents spec file path pollution between tests
   - Each test starts with a clean slate
   - Without this, old spec paths leak into new tests causing failures

2. **NEVER assign artisan() to a variable**
   ```php
   // ❌ WRONG - PendingCommand destructor runs when binding is lost
   $exitCode = $this->artisan('test-command endpoint');

   // ✅ CORRECT - Chain assertions immediately
   $this->artisan('test-command endpoint')->assertSuccessful();
   $this->artisan('test-command endpoint')->assertFailed();
   ```

3. **Call `Http::fake()` BEFORE `OpenApiCli::register()`**
   - Ensures HTTP client is mocked before command registration
   - Prevents accidental real HTTP requests during registration

4. **Use unique temp file names**
   ```php
   $this->specFile = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';
   ```

5. **Always clean up temp files in `afterEach()`**

#### TestCase Infrastructure

The `tests/TestCase.php` provides:

1. **`resolveApplicationConsoleKernel()`** - Properly binds the Console Kernel interface to prevent "Target [Illuminate\Contracts\Console\Kernel] is not instantiable" errors

2. **Automatic command registration** - The `artisan()` method is overridden to automatically register OpenApiCli commands before executing them

3. **`getPackageProviders()`** - Registers the OpenApiCliServiceProvider automatically

You should NOT need to:
- Manually call `$this->app->register(OpenApiCliServiceProvider::class)`
- Manually bind the Console Kernel
- Create custom registration methods in your tests

#### Common Pitfalls and Solutions

**Pitfall**: Tests fail with "Target [Illuminate\Contracts\Console\Kernel] is not instantiable"
- **Cause**: Assigning `$this->artisan()` to a variable or missing clearRegistrations()
- **Solution**: Chain assertions immediately and always clear registrations in beforeEach()

**Pitfall**: Tests pass individually but fail when run in full suite
- **Cause**: Test pollution - spec file paths from one test leak into another
- **Solution**: Call `OpenApiCli::clearRegistrations()` in EVERY beforeEach() hook

**Pitfall**: HTTP assertions fail even though the code looks correct
- **Cause**: Http::fake() called after OpenApiCli::register()
- **Solution**: Always call Http::fake() BEFORE registration

**Pitfall**: Can't verify command appears in artisan list
- **Cause**: Commands aren't registered yet
- **Solution**: Call `$this->artisan('list')` - the artisan() override handles registration automatically

#### Testing Exit Codes

For testing command failures (4xx/5xx errors):

```php
// Test successful execution
$this->artisan('test-command endpoint')
    ->assertSuccessful();

// Test failed execution
$this->artisan('test-command endpoint')
    ->assertFailed();
```

#### Orchestra Testbench Resources

- [Orchestra Testbench Documentation](https://packages.tools/testbench/)
- [Testing Artisan Commands](https://laravel.com/docs/10.x/console-tests)
- [HTTP Testing](https://laravel.com/docs/10.x/http-tests)

### General Testing Guidelines
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
- When adding or changing user-facing features, always update README.md to reflect the changes
