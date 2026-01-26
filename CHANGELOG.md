# Changelog

All notable changes to this project will be documented in this file.

## [1.2.1] - 2026-01-26

### Fixed
- Fixed `curl_close()` deprecation warning in `AsyncHttpClient` for PHP 8.2+ compatibility (handles are now auto-cleaned when out of scope)
- Fixed user agent strings: Changed from `gemserver`/`gemserver-async` to `gemvc-http-client`/`gemvc-http-client-async` for consistency
- Fixed `SwooleHttpClient` to support both Swoole and OpenSwoole extensions with automatic detection and proper fallback handling
- Improved error handling in `SwooleHttpClient` coroutine creation with try-catch blocks and fallback to synchronous execution

## [1.2.0] - 2026-01-21

### Added
- **Native Swoole Support**: `SwooleHttpClient` now uses native `Swoole\Coroutine\Http\Client` instead of cURL for true non-blocking performance
- `CurlClientTrait` to encapsulate shared cURL logic, decoupling it from the abstract base class
- Swoole Stubs for static analysis and testing on non-Swoole environments (Windows/macOS without ext)

### Changed
- Refactored `AbstractHttpClient` to be a pure configuration and state container (cURL logic moved to trait)
- `SwooleHttpClient` no longer depends on cURL; fully native implementation
- `fireAndForget()` in Swoole client now uses `Swoole\Coroutine::create()` (alias `go()`) for genuine background execution
- Updated `IHttpClient` and `AbstractHttpClient` fluent setters to return `static` for better type covariance

### Fixed
- PHPStan Level 9 compliance (fixed generic array types, return types, and missing classes via stubs)
- Resolved strict type issues in `executeSingleRequest`
- Fixed covariance return type errors in inheritance chain
- Enabled unit tests to pass on Windows by mocking Swoole classes when extension is missing

## [1.1.0] - 2026-01-18

### Added
- `AbstractHttpClient` base class to centralize common properties and methods across all HTTP client implementations
- Enhanced error handling with `$errors` array property for storing exceptions without requiring try-catch blocks
- Error management methods: `clearErrors()`, `hasErrors()`, `getErrors()`, `getLastError()`
- `throwExceptions(bool $throw)` method in `HttpClient` to control exception throwing behavior
- Network error type classification in `NetworkException` with constants: `TYPE_DNS_ERROR`, `TYPE_CONNECTION_ERROR`, `TYPE_SSL_ERROR`, `TYPE_RECEIVE_ERROR`, `TYPE_SEND_ERROR`
- Helper methods in `NetworkException`: `isDnsError()`, `isConnectionError()`, `isSslError()`, `isReceiveError()`, `isSendError()`, `getErrorTypeDescription()`
- `isConnectionTimeout()` method in `TimeoutException` to distinguish between connection and request timeouts
- Enhanced exception context: URL, HTTP code, and cURL error code are now included in all exceptions
- Exception information in async results: `exception` and `exception_type` fields added to result arrays

### Improved
- Exception handling now distinguishes between network/timeout errors (exceptions) and HTTP error codes (valid responses)
- Network error detection with automatic classification by error type
- Code organization: reduced duplication through abstract base class
- Test coverage increased to 74.56% with comprehensive error handling tests
- Exception messages now include more context (URL, error type, timeout type)

### Changed
- All HTTP client classes now extend `AbstractHttpClient` instead of directly implementing `IHttpClient`
- Exception throwing is enabled by default in `HttpClient` (can be disabled with `throwExceptions(false)`)
- Error storage: exceptions are automatically stored in `$errors` array even when thrown

### Fixed
- HTTP error codes (4xx, 5xx) are now treated as valid responses rather than exceptions
- Improved error context in exception messages

## [1.0.0] - 2026-01-18

### Added
- Initial release of gemvc/http-client package
- `IHttpClient` interface for common HTTP client methods
- `HttpClient` - Synchronous HTTP client for Apache/Nginx
- `SwooleHttpClient` - Swoole-optimized synchronous HTTP client
- `AsyncHttpClient` - Asynchronous HTTP client for Apache/Nginx
- `SwooleAsyncHttpClient` - Swoole-optimized asynchronous HTTP client
- Exception classes: `HttpClientException`, `TimeoutException`, `NetworkException`
- Comprehensive unit and integration tests
- Full backward compatibility with GEMVC framework

### Features
- Support for GET, POST, PUT requests
- Form data, multipart, and raw body support
- SSL/TLS configuration
- Retry logic with configurable backoff
- Configurable timeouts
- Concurrent request execution (async)
- Fire-and-forget mode for non-blocking background tasks
- Environment-aware implementation selection
