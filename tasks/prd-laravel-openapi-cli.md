# PRD: Laravel OpenAPI CLI Package

## Introduction

Build `spatie/laravel-openapi-cli`, a Laravel package that provides artisan commands for executing HTTP requests defined in OpenAPI specifications. This allows developers to interact with any API that has an OpenAPI spec directly from the command line, similar to how `gh api` works for GitHub's API.

The package enables developers to register OpenAPI specs in their service provider, automatically creating custom artisan commands that can execute HTTP requests to endpoints defined in the spec, with authentication, path parameter extraction, request body handling, and comprehensive validation.

## Goals

- Enable registration of OpenAPI specs as custom artisan commands
- Match user-provided endpoint paths against OpenAPI spec definitions
- Execute HTTP requests to matched endpoints with proper authentication
- Support multiple authentication methods (Bearer, API Key, Basic, dynamic)
- Handle path parameters, query strings, request bodies, and file uploads
- Provide discovery features (list endpoints, show schema, endpoint-specific help)
- Validate that requests match paths defined in the OpenAPI spec
- Maintain the Spatie package skeleton structure
- Achieve 90%+ test coverage
- Follow Spatie package conventions

## User Stories

### US-001: Package Foundation & Structure
**Description:** As a developer, I need the package to maintain Spatie's standard package structure so it integrates seamlessly with Laravel and follows established conventions.

**Acceptance Criteria:**
- [ ] Maintain existing directory structure (src/, tests/, config/, database/)
- [ ] Keep OpenApiCliServiceProvider extending PackageServiceProvider
- [ ] Keep Facade pattern in src/Facades/
- [ ] Ensure Pint, PHPStan, and Pest configurations remain functional
- [ ] All existing composer scripts (test, analyse, format) still work
- [ ] Package namespace remains `Spatie\OpenApiCli`
- [ ] Typecheck passes (PHPStan)
- [ ] Code formatting passes (Pint)

### US-002: OpenAPI Parser - YAML Support
**Description:** As a developer, I need to parse YAML OpenAPI spec files so I can register them as commands.

**Acceptance Criteria:**
- [ ] Create `OpenApiParser` class in `src/`
- [ ] Parse YAML files using `symfony/yaml` (add to composer.json)
- [ ] Extract paths with their HTTP methods
- [ ] Extract operation summaries and descriptions
- [ ] Extract path parameters with types
- [ ] Extract request body schemas
- [ ] Extract `servers[0].url` as default base URL
- [ ] Handle specs without servers array gracefully
- [ ] Support OpenAPI 3.x only
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-003: OpenAPI Parser - JSON Support
**Description:** As a developer, I need to parse JSON OpenAPI spec files so I can support both common spec formats.

**Acceptance Criteria:**
- [ ] Parse JSON files using native `json_decode`
- [ ] Auto-detect format based on file extension
- [ ] Same extraction capabilities as YAML parser
- [ ] Throw clear error for invalid JSON
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-004: Internal $ref Resolution
**Description:** As a developer, I need the parser to resolve internal `$ref` pointers so that schema definitions are fully accessible.

**Acceptance Criteria:**
- [ ] Create `RefResolver` class in `src/`
- [ ] Resolve simple refs like `$ref: '#/components/schemas/User'`
- [ ] Resolve nested refs (refs within resolved objects)
- [ ] Handle refs in arrays
- [ ] Handle refs in request bodies
- [ ] Implement JSON pointer lookup (split on '/', navigate object)
- [ ] Return original value when no ref present
- [ ] Throw descriptive error on invalid ref pointer
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-005: Path Matcher - Pattern Conversion
**Description:** As a developer, I need to convert OpenAPI path templates to regex patterns so I can match user input against spec paths.

**Acceptance Criteria:**
- [ ] Create `PathMatcher` class in `src/`
- [ ] Convert `/projects/{id}` to `/projects/(?P<id>[^/]+)`
- [ ] Convert `/projects/{projectId}/errors/{errorId}` to regex with named groups
- [ ] Handle multiple path parameters correctly
- [ ] Escape special regex characters in static path segments
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-006: Path Matcher - User Input Matching
**Description:** As a developer, I need to match user input against all spec paths so the correct endpoint is identified.

**Acceptance Criteria:**
- [ ] Match simple paths: `/projects` matches input `projects`
- [ ] Match paths with parameters: `/projects/{id}` matches `projects/123`
- [ ] Extract named parameters from matches
- [ ] Handle leading slashes equivalently (`/projects` and `projects`)
- [ ] Handle trailing slashes equivalently (`projects/` and `projects`)
- [ ] Return HTTP methods available for matched path
- [ ] Prioritize exact matches over parameterized matches
- [ ] When multiple paths match, return all matches (don't auto-select)
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-007: Ambiguous Path Resolution
**Description:** As a user, when my input matches multiple OpenAPI paths, I want to see all possible matches with a clear error so I can specify which one I meant (likely with --method flag).

**Acceptance Criteria:**
- [ ] Detect when input matches multiple spec paths
- [ ] Display error message listing all matching paths
- [ ] Show HTTP methods available for each matching path
- [ ] Suggest using `--method` flag to disambiguate
- [ ] Show example: `php artisan api-name endpoint --method POST`
- [ ] Exit with error code when ambiguous
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-008: Command Registration System
**Description:** As a developer, I need to register OpenAPI specs as artisan commands so users can interact with the API from CLI.

**Acceptance Criteria:**
- [ ] Create static `register()` method on main `OpenApiCli` class
- [ ] Accept spec file path and command signature
- [ ] Create dynamic artisan command with given signature
- [ ] Store registrations in service provider
- [ ] Return fluent configuration object for chaining
- [ ] Commands appear in `php artisan list`
- [ ] Multiple specs can be registered with different command names
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-009: Authentication - Bearer Token
**Description:** As a developer, I need to configure Bearer token authentication so API requests are authenticated.

**Acceptance Criteria:**
- [ ] Create `->bearer($token)` fluent method
- [ ] Add `Authorization: Bearer {token}` header to requests
- [ ] Support static token strings
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-010: Authentication - API Key
**Description:** As a developer, I need to configure custom API key authentication so I can use APIs with non-standard auth headers.

**Acceptance Criteria:**
- [ ] Create `->apiKey($headerName, $value)` fluent method
- [ ] Add custom header to requests with specified name and value
- [ ] Support any header name (e.g., `X-API-Key`, `Authorization`)
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-011: Authentication - Basic Auth
**Description:** As a developer, I need to configure HTTP Basic authentication so I can authenticate with username/password.

**Acceptance Criteria:**
- [ ] Create `->basic($username, $password)` fluent method
- [ ] Encode credentials as base64
- [ ] Add `Authorization: Basic {encoded}` header to requests
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-012: Authentication - Dynamic/Callable Auth
**Description:** As a developer, I need to configure dynamic authentication so I can fetch fresh tokens from cache or other sources on each request.

**Acceptance Criteria:**
- [ ] Create `->auth(callable)` fluent method
- [ ] Invoke callable on each request (not just once at registration)
- [ ] Use returned value as Bearer token
- [ ] Support closures and invokable objects
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-013: Base URL Configuration
**Description:** As a developer, I need to override the base URL so I can use different environments (dev, staging, prod).

**Acceptance Criteria:**
- [ ] Create `->baseUrl($url)` fluent method
- [ ] Override spec's `servers[0].url` when provided
- [ ] Use spec's server URL as fallback if not overridden
- [ ] Throw error if no base URL available (no override and no servers in spec)
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-014: Command Execution - GET Requests
**Description:** As a user, I want to execute GET requests to API endpoints by providing the path.

**Acceptance Criteria:**
- [ ] Accept endpoint path as first argument
- [ ] Default to GET method when no `--field` or `--method` provided
- [ ] Match path against OpenAPI spec
- [ ] Build full URL with base URL + path
- [ ] Execute HTTP request using Laravel HTTP Client
- [ ] Apply configured authentication
- [ ] Output response body
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-015: Command Execution - Path Parameters
**Description:** As a user, I want to provide path parameters inline so I can access specific resources.

**Acceptance Criteria:**
- [ ] Extract path parameters from user input: `projects/123/errors/456`
- [ ] Match against parameterized paths: `/projects/{projectId}/errors/{errorId}`
- [ ] Substitute parameters into final URL
- [ ] Validate all required path parameters are provided
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-016: Command Execution - Query Parameters
**Description:** As a user, I want to add query parameters to requests so I can filter and paginate results.

**Acceptance Criteria:**
- [ ] Add `--query` option to command
- [ ] Parse query string format: `status=active&limit=10`
- [ ] Append query parameters to URL
- [ ] URL-encode parameter values
- [ ] Support multiple parameters
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-017: Command Execution - Explicit Method Override
**Description:** As a user, I want to explicitly specify HTTP method so I can execute DELETE, PUT, PATCH requests.

**Acceptance Criteria:**
- [ ] Add `--method` option to command
- [ ] Accept method names: GET, POST, PUT, PATCH, DELETE
- [ ] Case insensitive: `--method delete` works
- [ ] Override auto-detected method
- [ ] Validate method is allowed for matched path in spec
- [ ] Show error if method not defined for path in spec
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-018: Command Execution - Form Fields (POST Data)
**Description:** As a user, I want to send form data in POST requests so I can create and update resources.

**Acceptance Criteria:**
- [ ] Add `--field` option (can be used multiple times)
- [ ] Parse format: `--field name=value`
- [ ] Auto-detect POST method when `--field` is used
- [ ] Send as form-data or JSON based on spec content-type
- [ ] Support multiple fields: `--field name=Test --field team_id=1`
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-019: Command Execution - JSON Input
**Description:** As a user, I want to send raw JSON bodies so I can submit complex nested data.

**Acceptance Criteria:**
- [ ] Add `--input` option
- [ ] Accept JSON string: `--input '{"name":"Test","data":{"nested":true}}'`
- [ ] Auto-detect POST method when `--input` is used
- [ ] Validate JSON is valid before sending
- [ ] Send with `Content-Type: application/json`
- [ ] Override `--field` if both provided (or show error)
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-020: Command Execution - File Uploads
**Description:** As a user, I want to upload files using the @ prefix so I can submit multipart form data with files.

**Acceptance Criteria:**
- [ ] Detect `@` prefix in field values: `--field file=@/path/to/file.txt`
- [ ] Read file from filesystem
- [ ] Use Laravel HTTP Client's `attach()` method
- [ ] Send as multipart/form-data
- [ ] Support multiple files
- [ ] Mix file fields with regular fields
- [ ] Show error if file path doesn't exist
- [ ] Show error if file is not readable
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-021: Endpoint Validation
**Description:** As a user, when I try to access an endpoint not in the spec, I want to see an error listing available endpoints so I know what's valid.

**Acceptance Criteria:**
- [ ] Reject requests to paths not defined in spec
- [ ] Show error message: "Endpoint not found in OpenAPI spec"
- [ ] List all available endpoints from spec
- [ ] Include HTTP methods for each endpoint
- [ ] Exit with non-zero code
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-022: Discovery - List All Endpoints
**Description:** As a user, I want to list all available endpoints from the spec so I can discover what the API offers.

**Acceptance Criteria:**
- [ ] Add `--list` flag to command
- [ ] Output format: `GET     /projects                     List all projects`
- [ ] Show HTTP method (left-aligned, fixed width)
- [ ] Show path (middle column)
- [ ] Show description from `summary` or `description` field
- [ ] Sort endpoints logically (by path, then method)
- [ ] Format as readable table
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-023: Discovery - Output Full Schema
**Description:** As a user, I want to output the full OpenAPI spec as JSON so I can inspect it or pipe it to other tools.

**Acceptance Criteria:**
- [ ] Add `--schema` flag to command
- [ ] Output complete OpenAPI spec as JSON
- [ ] Pretty-print by default (readable formatting)
- [ ] Include all paths, components, schemas
- [ ] Valid JSON output
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-024: Discovery - Endpoint-Specific Help
**Description:** As a user, I want to see detailed help for a specific endpoint so I know what parameters and body it expects.

**Acceptance Criteria:**
- [ ] Add `--help` flag (used with endpoint path)
- [ ] Show HTTP method and path
- [ ] Show endpoint description
- [ ] List path parameters with types and required/optional
- [ ] List request body schema when present
- [ ] Show content-type for request body
- [ ] Format example: `POST /projects/{projectId}/sourcemaps`
- [ ] Work with parameterized paths: `projects/{id} --help`
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-025: Output Formatting - Pretty JSON (Default)
**Description:** As a user, I want response JSON to be pretty-printed by default so it's readable in the terminal.

**Acceptance Criteria:**
- [ ] Pretty-print JSON responses with indentation
- [ ] Use JSON_PRETTY_PRINT flag
- [ ] Apply syntax highlighting if terminal supports it (optional)
- [ ] Show raw body for non-JSON responses
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-026: Output Formatting - Minified JSON
**Description:** As a user, I want to minify JSON output for piping or compactness.

**Acceptance Criteria:**
- [ ] Add `--minify` flag
- [ ] Output JSON on single line
- [ ] No extra whitespace
- [ ] Still valid JSON
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-027: Output Formatting - Include Response Headers
**Description:** As a user, I want to see response headers so I can debug and inspect API behavior.

**Acceptance Criteria:**
- [ ] Add `--include` flag
- [ ] Show HTTP status line: `HTTP/1.1 200 OK`
- [ ] Show all response headers
- [ ] Show headers before body
- [ ] Separate headers from body with blank line
- [ ] Format: `Header-Name: value`
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-028: Output Formatting - Non-JSON Responses
**Description:** As a user, when an API returns non-JSON content, I want to see the raw body with content-type indication.

**Acceptance Criteria:**
- [ ] Detect non-JSON content types
- [ ] Show notice: `Response is not JSON (content-type: text/html)`
- [ ] Output raw response body
- [ ] Don't attempt to parse as JSON
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-029: Output Formatting - Empty Responses
**Description:** As a user, when an API returns 204 No Content, I want graceful handling without errors.

**Acceptance Criteria:**
- [ ] Detect 204 No Content responses
- [ ] Show message: `No content (204)`
- [ ] Don't attempt to parse body
- [ ] Exit successfully
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-030: Error Handling - HTTP 4xx Errors
**Description:** As a user, when a request fails with 4xx, I want to see the status code and response body to understand what went wrong.

**Acceptance Criteria:**
- [ ] Detect 4xx status codes
- [ ] Display status code in output
- [ ] Display response body (often contains error details)
- [ ] Use red color for error output (if terminal supports)
- [ ] Exit with non-zero code
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-031: Error Handling - HTTP 5xx Errors
**Description:** As a user, when a server error occurs, I want to see the status code and response so I can report issues.

**Acceptance Criteria:**
- [ ] Detect 5xx status codes
- [ ] Display status code in output
- [ ] Display response body
- [ ] Use red color for error output
- [ ] Exit with non-zero code
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-032: Error Handling - Network Errors
**Description:** As a user, when network issues occur, I want helpful error messages so I can troubleshoot connectivity.

**Acceptance Criteria:**
- [ ] Catch connection exceptions
- [ ] Show message: `Network error: Could not connect to {url}`
- [ ] Show underlying error message if available
- [ ] Exit with non-zero code
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-033: Error Handling - Invalid Spec Files
**Description:** As a developer, when an OpenAPI spec file is invalid, I want clear errors so I can fix the spec.

**Acceptance Criteria:**
- [ ] Validate spec file exists
- [ ] Validate spec file is readable
- [ ] Validate YAML/JSON syntax
- [ ] Show parsing errors with line numbers when available
- [ ] Show message: `Invalid OpenAPI spec: {reason}`
- [ ] Exit with non-zero code
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-034: README Documentation
**Description:** As a package user, I need comprehensive README documentation following Spatie's structure so I can learn to use the package.

**Acceptance Criteria:**
- [ ] Update title and description (lines 1-8 of existing README)
- [ ] Keep all existing README sections (Support us, Installation, Usage, Testing, etc.)
- [ ] Remove migrations section (not applicable)
- [ ] Remove views section (not applicable)
- [ ] Update Installation section with actual setup steps
- [ ] Add comprehensive Usage section with registration examples
- [ ] Show examples of all authentication methods
- [ ] Show examples of all command usage patterns (GET, POST, --field, --query, etc.)
- [ ] Show examples of discovery commands (--list, --schema, --help)
- [ ] Include example of file upload
- [ ] Document all available options/flags
- [ ] Keep Testing section with `composer test`
- [ ] Keep all footer sections (Changelog, Contributing, Security, Credits, License)
- [ ] Update Credits author name if needed

### US-035: Test Suite - Unit Tests (Part 1)
**Description:** As a developer, I need comprehensive unit tests for the OpenAPI parser so parsing logic is verified.

**Acceptance Criteria:**
- [ ] Create `tests/Unit/OpenApiParserTest.php`
- [ ] Test parsing YAML spec files correctly
- [ ] Test parsing JSON spec files correctly
- [ ] Test extracting paths with methods
- [ ] Test extracting server URLs
- [ ] Test handling specs without servers array
- [ ] Test extracting operation summaries and descriptions
- [ ] Test extracting path parameters with types
- [ ] Test extracting request body schemas
- [ ] Use `flare-api.yaml` as primary fixture
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-036: Test Suite - Unit Tests (Part 2)
**Description:** As a developer, I need comprehensive unit tests for path matching so endpoint resolution is verified.

**Acceptance Criteria:**
- [ ] Create `tests/Unit/PathMatcherTest.php`
- [ ] Test matching simple paths: `/projects` matches `projects`
- [ ] Test matching single parameter: `/projects/{id}` matches `projects/123`
- [ ] Test matching multiple parameters
- [ ] Test extracting named parameters correctly
- [ ] Test rejecting paths not in spec
- [ ] Test trailing slash equivalence
- [ ] Test leading slash equivalence
- [ ] Test returning correct HTTP methods for matched path
- [ ] Test prioritizing exact matches over parameterized
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-037: Test Suite - Unit Tests (Part 3)
**Description:** As a developer, I need unit tests for $ref resolution and authentication so those critical features are verified.

**Acceptance Criteria:**
- [ ] Create `tests/Unit/RefResolverTest.php`
- [ ] Test resolving simple `$ref: '#/components/schemas/User'`
- [ ] Test resolving nested refs
- [ ] Test handling refs in arrays
- [ ] Test handling refs in request bodies
- [ ] Test returning original value when no ref present
- [ ] Test throwing on invalid ref pointer
- [ ] Create `tests/Unit/AuthenticationTest.php`
- [ ] Test bearer token adds correct Authorization header
- [ ] Test API key adds custom header correctly
- [ ] Test basic auth encodes credentials correctly
- [ ] Test callable auth invokes function and uses returned value
- [ ] Test callable auth is invoked fresh on each request
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-038: Test Suite - Feature Tests (Part 1)
**Description:** As a developer, I need feature tests for command registration and discovery so user-facing features work correctly.

**Acceptance Criteria:**
- [ ] Create `tests/Feature/CommandRegistrationTest.php`
- [ ] Test command registers with specified signature
- [ ] Test multiple specs register separate commands
- [ ] Test command appears in artisan list
- [ ] Test fluent methods are chainable
- [ ] Test baseUrl override works
- [ ] Test auth configuration persists
- [ ] Create `tests/Feature/ListEndpointsTest.php`
- [ ] Test `--list` outputs all endpoints from spec
- [ ] Test output includes method, path, description
- [ ] Test output is formatted in columns
- [ ] Test endpoints are sorted logically
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-039: Test Suite - Feature Tests (Part 2)
**Description:** As a developer, I need feature tests for schema output and endpoint help.

**Acceptance Criteria:**
- [ ] Create `tests/Feature/SchemaOutputTest.php`
- [ ] Test `--schema` outputs valid JSON
- [ ] Test output contains all paths from spec
- [ ] Test output contains components/schemas
- [ ] Test `--schema --minify` outputs minified JSON
- [ ] Create `tests/Feature/EndpointHelpTest.php`
- [ ] Test `--help` with endpoint shows details
- [ ] Test shows path parameters with types
- [ ] Test shows request body schema when present
- [ ] Test shows description when available
- [ ] Test works with parameterized paths using braces
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-040: Test Suite - Feature Tests (Part 3)
**Description:** As a developer, I need feature tests for request execution so HTTP requests work correctly with mocking.

**Acceptance Criteria:**
- [ ] Create `tests/Feature/RequestExecutionTest.php`
- [ ] Test executes GET request to valid endpoint (use `Http::fake()`)
- [ ] Test executes POST request when `--field` provided
- [ ] Test executes explicit method with `--method`
- [ ] Test sends query parameters with `--query`
- [ ] Test sends JSON body with `--input`
- [ ] Test sends form fields with `--field`
- [ ] Test rejects request to endpoint not in spec
- [ ] Test rejects request with method not allowed for endpoint
- [ ] Test extracts path parameters and includes in request
- [ ] Test applies bearer authentication
- [ ] Test applies API key authentication
- [ ] Test applies basic authentication
- [ ] All tests use `Http::assertSent()` for verification
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-041: Test Suite - Feature Tests (Part 4)
**Description:** As a developer, I need feature tests for output formatting, file uploads, and error handling.

**Acceptance Criteria:**
- [ ] Create `tests/Feature/OutputFormattingTest.php`
- [ ] Test default output is pretty-printed JSON
- [ ] Test `--minify` outputs minified JSON
- [ ] Test `--include` shows response headers and status line
- [ ] Test non-JSON response shows raw body with content-type notice
- [ ] Test empty response (204) handled gracefully
- [ ] Create `tests/Feature/FileUploadTest.php`
- [ ] Test `--field file=@/path` triggers multipart upload
- [ ] Test file contents sent correctly
- [ ] Test multiple files can be uploaded
- [ ] Test file field mixed with regular fields
- [ ] Test non-existent file path shows helpful error
- [ ] Create `tests/Feature/ErrorHandlingTest.php`
- [ ] Test 4xx response shows error with status code
- [ ] Test 5xx response shows error with status code
- [ ] Test error response body displayed
- [ ] Test network errors show helpful message
- [ ] Test invalid spec file shows helpful error
- [ ] Test missing spec file shows helpful error
- [ ] All tests pass
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-042: Test Coverage Verification
**Description:** As a developer, I need to verify test coverage meets 90%+ so the package is thoroughly tested.

**Acceptance Criteria:**
- [ ] Run `composer test-coverage`
- [ ] Overall coverage is 90% or higher
- [ ] Path matching logic has 95%+ coverage (critical for security)
- [ ] OpenAPI parsing has 90%+ coverage
- [ ] Authentication has 95%+ coverage
- [ ] Output formatting has 85%+ coverage (lower priority)
- [ ] Generate coverage report
- [ ] Typecheck passes
- [ ] Code formatting passes

### US-043: Dependencies & Composer Configuration
**Description:** As a developer, I need to add required dependencies so all features work.

**Acceptance Criteria:**
- [ ] Add `symfony/yaml` to composer.json require section
- [ ] Ensure `illuminate/http` is available (already via illuminate/contracts)
- [ ] Run `composer update`
- [ ] Verify all dependencies install correctly
- [ ] Update composer.json minimum PHP version if needed (spec says 8.2+, currently 8.4)
- [ ] Run `composer validate`
- [ ] Typecheck passes
- [ ] Code formatting passes

## Functional Requirements

**Spec Registration:**
- FR-1: Package must provide `OpenApiCli::register($path, $signature)` static method
- FR-2: Registration must support fluent configuration methods: `->baseUrl()`, `->bearer()`, `->apiKey()`, `->basic()`, `->auth()`
- FR-3: Registered commands must appear in `php artisan list`
- FR-4: Multiple specs can be registered with different command signatures

**OpenAPI Parsing:**
- FR-5: Parser must support YAML format using `symfony/yaml`
- FR-6: Parser must support JSON format using native `json_decode`
- FR-7: Parser must extract paths, methods, parameters, request bodies, and descriptions
- FR-8: Parser must extract server URLs from `servers[0].url`
- FR-9: Parser must resolve internal `$ref` pointers using JSON pointer syntax
- FR-10: Parser must support OpenAPI 3.x only

**Path Matching:**
- FR-11: PathMatcher must convert OpenAPI path templates to regex with named capture groups
- FR-12: PathMatcher must match user input against all spec paths
- FR-13: PathMatcher must extract path parameters from user input
- FR-14: PathMatcher must treat leading/trailing slashes equivalently
- FR-15: PathMatcher must prioritize exact matches over parameterized matches
- FR-16: PathMatcher must detect ambiguous matches (multiple paths match)
- FR-17: When ambiguous, system must show all matching paths with methods and suggest using `--method`

**Authentication:**
- FR-18: System must support Bearer token authentication via `Authorization: Bearer` header
- FR-19: System must support custom API key authentication via custom headers
- FR-20: System must support HTTP Basic authentication with base64 encoding
- FR-21: System must support dynamic authentication via callable (invoked per request)

**Request Execution:**
- FR-22: System must execute GET requests by default
- FR-23: System must auto-detect POST when `--field` or `--input` provided
- FR-24: System must support explicit method override with `--method` flag (case insensitive)
- FR-25: System must support query parameters via `--query` flag
- FR-26: System must support form fields via `--field` flag (repeatable)
- FR-27: System must support JSON body via `--input` flag
- FR-28: System must support file uploads when field value starts with `@`
- FR-29: System must validate endpoint path exists in spec before executing
- FR-30: System must validate HTTP method is allowed for endpoint before executing
- FR-31: System must use Laravel HTTP Client for all requests

**Discovery:**
- FR-32: Command must support `--list` flag to show all endpoints
- FR-33: `--list` output must show method, path, and description in tabular format
- FR-34: Command must support `--schema` flag to output full OpenAPI spec as JSON
- FR-35: Command must support `--help` with endpoint to show endpoint-specific details
- FR-36: `--help` must show path parameters, request body schema, and description

**Output Formatting:**
- FR-37: JSON responses must be pretty-printed by default
- FR-38: `--minify` flag must output minified JSON
- FR-39: `--include` flag must output response headers and status line
- FR-40: Non-JSON responses must show raw body with content-type indication
- FR-41: Empty responses (204) must be handled gracefully with message

**Error Handling:**
- FR-42: 4xx responses must show status code and body in red (if terminal supports color)
- FR-43: 5xx responses must show status code and body in red
- FR-44: Network errors must show helpful message with URL
- FR-45: Invalid spec files must show parsing errors
- FR-46: Missing spec files must show clear error message
- FR-47: All errors must exit with non-zero code
- FR-48: Endpoint not in spec must list available endpoints

**Package Structure:**
- FR-49: Package must maintain Spatie package skeleton structure
- FR-50: Package must use Spatie's laravel-package-tools
- FR-51: Package must have OpenApiCliServiceProvider extending PackageServiceProvider
- FR-52: Package must have OpenApiCli facade
- FR-53: Package must use Pest for testing
- FR-54: Package must use Pint for code formatting
- FR-55: Package must use PHPStan for static analysis

## Non-Goals (Out of Scope for v1)

- No Swagger 2.0 support (OpenAPI 3.x only)
- No external `$ref` resolution (files referencing other files)
- No request payload validation against schema (only endpoint path validation)
- No stdin input piping
- No JQ-style filtering (`--jq` flag) - users can pipe to `jq` themselves
- No custom headers via CLI flags (only via auth configuration)
- No OAuth flows (only static tokens and callable auth)
- No response caching
- No retry logic for failed requests
- No progress indicators for file uploads
- No request/response history
- No interactive mode or REPL

## Technical Considerations

**Laravel HTTP Client:**
- Use `Http::` facade from `Illuminate\Support\Facades\Http`
- Use `Http::fake()` in tests for request mocking
- Use `attach()` method for multipart file uploads
- Use `asForm()` for form-data, `asJson()` for JSON bodies

**OpenAPI Spec Handling:**
- No external library needed for OpenAPI parsing
- Use `symfony/yaml` for YAML parsing
- Implement custom $ref resolution (JSON pointer lookup)
- Handle missing or malformed specs gracefully with clear errors

**Package Structure:**
- Follow Spatie's laravel-package-tools conventions
- Use dynamic command registration via service provider
- Store command configurations in-memory during registration
- Use Laravel's command infrastructure for all CLI interactions

**Testing with flare-api.yaml:**
- Use provided `flare-api.yaml` as primary test fixture
- Create minimal additional fixtures in `tests/fixtures/` as needed
- Aim for 90%+ overall coverage, 95%+ for critical paths

**Code Quality:**
- All code must pass PHPStan analysis
- All code must pass Pint formatting checks
- PHP 8.2+ required (update from 8.4 if needed for compatibility)
- Laravel 10+ support

## Success Metrics

- Package successfully registered and published to Packagist
- Developers can register OpenAPI specs with < 5 lines of code
- Users can execute API requests with single artisan command
- Test coverage achieves 90%+ overall
- PHPStan level passes with zero errors
- Pint formatting passes with zero violations
- README is comprehensive and follows Spatie structure exactly
- Package structure remains unchanged from skeleton (src/, tests/, config/, etc.)
- All existing composer scripts (test, analyse, format) continue working

## Open Questions

None - all requirements clarified based on user responses.

## Implementation Notes

**Quality Checks (REQUIRED):**
Every user story MUST pass all three quality checks before being marked complete:
1. `composer analyse` - PHPStan static analysis must pass with ZERO errors
2. `composer format` - Pint code formatting must pass
3. `composer test` - Pest test suite must pass (all tests green)

Do NOT commit code that fails any quality check. Do NOT skip quality checks.

**Maintain Package Skeleton:**
- Do NOT remove or restructure existing directories
- Keep `src/Commands/`, `src/Facades/`, `config/`, `database/`, `tests/` structure
- Remove unused example files but maintain structure
- Keep `.gitignore`, `.editorconfig`, `phpstan.neon.dist`, `phpunit.xml.dist`, etc.
- Migrations and views sections are not needed - remove from service provider and README

**README Structure to Preserve:**
1. Title and description (update content, keep structure)
2. Badges (keep as-is)
3. Short description paragraph
4. Support us section (keep as-is)
5. Installation section (update, remove migrations and views)
6. Usage section (expand significantly with examples)
7. Testing section (keep)
8. Changelog section (keep)
9. Contributing section (keep)
10. Security section (keep)
11. Credits section (keep, update author if needed)
12. License section (keep)

**Testing Priority:**
- Focus first on path matching (security critical - prevents arbitrary requests)
- Then OpenAPI parsing (handles user-provided files)
- Then authentication (security critical)
- Then request execution and output formatting

**Dependencies to Add:**
- `symfony/yaml` for YAML parsing (required)
- Consider `symfony/console` output helpers for colored output (optional, may already be available via Laravel)
