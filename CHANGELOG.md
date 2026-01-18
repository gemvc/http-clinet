# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-01-18

### Added
- `AbstractHttpClient` base class to centralize common properties and methods across all HTTP client implementations
- Enhanced error handling with `$errors` array property for storing exceptions without requiring try-catch blocks
- Error management methods: `clearErrors()`, `hasErrors()`, `getErrors()`, `getLastError()`
- `throwExceptions(bool $throw)` method in `SyncHttpClient` to control exception throwing behavior
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
- Exception throwing is enabled by default in `SyncHttpClient` (can be disabled with `throwExceptions(false)`)
- Error storage: exceptions are automatically stored in `$errors` array even when thrown

### Fixed
- HTTP error codes (4xx, 5xx) are now treated as valid responses rather than exceptions
- Improved error context in exception messages

## [1.0.0] - 2026-01-18

### Added
- Initial release of gemvc/http-client package
- `IHttpClient` interface for common HTTP client methods
- `SyncHttpClient` - Synchronous HTTP client for Apache/Nginx
- `SwooleSyncHttpClient` - Swoole-optimized synchronous HTTP client
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
