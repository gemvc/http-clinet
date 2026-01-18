# Changelog

All notable changes to this project will be documented in this file.

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
