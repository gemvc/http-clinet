# Release Notes

## Version 1.0.0 - January 18, 2026

 **Initial Release** of `gemvc/http-client` - A multi-environment PHP HTTP client package providing both synchronous and asynchronous API call capabilities.

### What's New

#### Core Components
- **`IHttpClient`** interface for common HTTP client methods
- **`SyncHttpClient`** - Synchronous HTTP client for Apache/Nginx environments
- **`SwooleSyncHttpClient`** - Swoole-optimized synchronous HTTP client
- **`AsyncHttpClient`** - Asynchronous HTTP client for Apache/Nginx environments
- **`SwooleAsyncHttpClient`** - Swoole-optimized asynchronous HTTP client

#### Exception Handling
- `HttpClientException` - Base exception for HTTP client errors
- `TimeoutException` - For timeout-related errors
- `NetworkException` - For network-related errors

### Key Features

✅ **HTTP Methods Support**
- GET, POST, PUT requests
- Form data submission
- Multipart form data with file uploads
- Raw body support with custom content types

✅ **Advanced Configuration**
- SSL/TLS certificate configuration
- Configurable connection and execution timeouts
- Retry logic with configurable backoff and HTTP status codes
- Network error retry support

✅ **Asynchronous Capabilities**
- Concurrent request execution with configurable concurrency limits
- Fire-and-forget mode for non-blocking background tasks
- Response callbacks for async requests

✅ **Environment Awareness**
- Automatic implementation selection based on runtime environment
- Optimized for Apache, Nginx, and Swoole

✅ **Quality & Compatibility**
- Comprehensive unit and integration tests
- Full backward compatibility with GEMVC framework
- Framework-independent design

### Installation

```bash
composer require gemvc/http-client
```

### Requirements

- PHP 8.2 or higher
- cURL extension
- OpenSwoole extension (optional, for optimized async in Swoole)

### Documentation

See [README.md](README.md) for full documentation and usage examples.

### Changelog

For detailed changes, see [CHANGELOG.md](CHANGELOG.md).

---

**Note:** This file contains release notes for the current version. For historical changes, please refer to [CHANGELOG.md](CHANGELOG.md).
