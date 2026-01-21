# Release Notes

## Version 1.2.0 - January 21, 2026

**Native Swoole Coroutines & Architectural Refactoring** - This release brings true native performance for Swoole environments and cleans up the internal architecture.

### What's New

#### 🚀 Native Swoole Support
- **`SwooleHttpClient`** has been completely rewritten to use `Swoole\Coroutine\Http\Client` directly.
- **True Non-Blocking**: No longer relies on cURL hooks; uses lightweight Swoole coroutines.
- **`fireAndForget()`**: Now spawns a genuine background coroutine using `go()`, ensuring zero blocking for background tasks.
- **Concurrent Execution**: Uses `Swoole\Coroutine\Barrier` and `Channel` for efficient concurrent request processing.

#### 🏗️ Architectural Improvements
- **Decoupled cURL**: cURL-specific logic has been moved out of `AbstractHttpClient` into a reusable `CurlClientTrait`.
- **Cleaner Inheritance**: `AbstractHttpClient` is now a pure base class for state and configuration, making it easier to implement non-cURL clients (like the new Swoole client).
- **Type Safety**: Achieved **PHPStan Level 9** compliance across the entire package.

#### 🧪 Testing & Stability
- Added **Swoole Stubs** to allow running static analysis and unit tests on Windows/macOS where the Swoole extension might be missing.
- Improved test coverage and reliability.

---

## Version 1.1.0 - January 18, 2026

**Enhanced Error Handling & Code Organization** - This release introduces significant improvements to error handling, code organization, and developer experience.

### What's New

#### 🏗️ Architecture Improvements
- **`AbstractHttpClient`** - New abstract base class that centralizes common properties and methods across all HTTP client implementations
- Reduced code duplication through shared base functionality
- Improved maintainability and consistency across all client types

#### 🛡️ Enhanced Error Handling

**Error Storage & Management**
- **`$errors` array property** - All exceptions are automatically stored, allowing inspection without try-catch blocks
- **`clearErrors()`** - Clear all stored errors
- **`hasErrors()`** - Check if any errors occurred
- **`getErrors()`** - Get all stored exceptions
- **`getLastError()`** - Get the most recent exception

**Exception Control**
- **`throwExceptions(bool $throw)`** - Control whether exceptions are thrown (default: `true`)
- When disabled, errors are stored in `$errors` array and methods return `false`
- Perfect for legacy code or when you prefer error checking over exception handling

**Enhanced Exception Classes**
- **`NetworkException`** now includes error type classification:
  - `TYPE_DNS_ERROR` - DNS resolution failures
  - `TYPE_CONNECTION_ERROR` - Connection failures
  - `TYPE_SSL_ERROR` - SSL/TLS handshake failures
  - `TYPE_RECEIVE_ERROR` - Data receive errors
  - `TYPE_SEND_ERROR` - Data send errors
- Helper methods: `isDnsError()`, `isConnectionError()`, `isSslError()`, etc.
- **`TimeoutException`** now distinguishes between connection and request timeouts via `isConnectionTimeout()`
- All exceptions now include: URL, HTTP code, and cURL error code for better debugging

**Async Results Enhancement**
- Result arrays now include `exception` and `exception_type` fields
- Easier error handling in async workflows

### Usage Examples

#### Error Handling Without Try-Catch

```php
use Gemvc\Http\Client\HttpClient;

$client = new HttpClient();
$client->throwExceptions(false); // Store errors instead of throwing

$response = $client->get('https://api.example.com/data');

// Check for errors
if ($client->hasErrors()) {
    $error = $client->getLastError();
    echo "Error: {$error->getMessage()}\n";
    echo "URL: {$error->getUrl()}\n";
    echo "cURL Code: {$error->getCurlErrorCode()}\n";
    
    // Check error type if it's a NetworkException
    if ($error instanceof \Gemvc\Http\Client\Exception\NetworkException) {
        if ($error->isDnsError()) {
            echo "DNS resolution failed\n";
        }
    }
}

// Clear errors for next request
$client->clearErrors();
```

#### Network Error Classification

```php
use Gemvc\Http\Client\HttpClient;
use Gemvc\Http\Client\Exception\NetworkException;

$client = new HttpClient();

try {
    $client->get('https://api.example.com/data');
} catch (NetworkException $e) {
    // Get specific error type
    $errorType = $e->getErrorType();
    $description = $e->getErrorTypeDescription();
    
    // Use helper methods
    if ($e->isDnsError()) {
        // Handle DNS error
    } elseif ($e->isSslError()) {
        // Handle SSL error
    }
}
```

#### Async Error Handling

```php
use Gemvc\Http\Client\AsyncHttpClient;

$async = new AsyncHttpClient();
$async->addGet('req1', 'https://api.example.com/data');

$results = $async->executeAll();

foreach ($results as $requestId => $result) {
    if (!$result['success']) {
        // Check for exception
        if ($result['exception']) {
            echo "Exception type: {$result['exception_type']}\n";
            echo "Error: {$result['exception']->getMessage()}\n";
        }
    }
}

// Check all errors
if ($async->hasErrors()) {
    foreach ($async->getErrors() as $error) {
        echo "Error: {$error->getMessage()}\n";
    }
}
```

### Improvements

✅ **Better Error Context** - All exceptions now include URL, HTTP code, and cURL error code  
✅ **Error Type Classification** - Network errors are automatically classified by type  
✅ **Flexible Error Handling** - Choose between exceptions or error storage  
✅ **Improved Code Organization** - Abstract base class reduces duplication  
✅ **Enhanced Test Coverage** - 74.56% coverage with comprehensive error handling tests  

### Migration Guide

**No breaking changes!** All existing code continues to work. New features are opt-in:

- To use error storage instead of exceptions: `$client->throwExceptions(false)`
- To check for errors: `if ($client->hasErrors()) { ... }`
- Existing exception handling continues to work as before

### Requirements

- PHP 8.1 or higher
- cURL extension
- OpenSwoole extension (optional, for optimized async in Swoole)

---

## Version 1.0.0 - January 18, 2026

 **Initial Release** of `gemvc/http-client` - A multi-environment PHP HTTP client package providing both synchronous and asynchronous API call capabilities.

### What's New

#### Core Components
- **`IHttpClient`** interface for common HTTP client methods
- **`HttpClient`** - Synchronous HTTP client for Apache/Nginx environments
- **`SwooleHttpClient`** - Swoole-optimized synchronous HTTP client
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
