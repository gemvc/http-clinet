# gemvc/http-client

**Multi-Environment PHP HTTP Client Package**  
Synchronous and Asynchronous API Calls for Apache, Nginx, and Swoole

Part of [Gemvc , a lightweight PHP Framework for microservices](https://gemvc.de) 

## Overview

A framework-independent HTTP client package providing both synchronous and asynchronous API call capabilities. Automatically adapts to your runtime environment (Apache, Nginx, or Swoole) for optimal performance.

## Features

- ✅ **Synchronous API calls** with retry logic and SSL support
- ✅ **Asynchronous concurrent requests** with configurable concurrency
- ✅ **Environment-aware execution** - optimized for each runtime
- ✅ **Framework-independent** - use in any PHP project
- ✅ **Backward compatible** with GEMVC framework

## Installation

```bash
composer require gemvc/http-client
```

## Requirements

- PHP 8.1 or higher
- cURL extension
- OpenSwoole extension (optional, for optimized async in Swoole)

## Quick Start

### Synchronous Client

```php
use Gemvc\Http\Client\SyncHttpClient;

$client = new SyncHttpClient();
$client->setTimeouts(10, 30)
       ->setRetries(3, 200, [500, 502, 503]);

$response = $client->get('https://api.example.com/users', ['page' => 1]);
$data = $client->post('https://api.example.com/users', ['name' => 'John']);
```

### Asynchronous Client

```php
use Gemvc\Http\Client\AsyncHttpClient;

$async = new AsyncHttpClient();
$async->setMaxConcurrency(5)
      ->setTimeouts(10, 30);

$async->addGet('users', 'https://api.example.com/users', ['page' => 1])
      ->addGet('posts', 'https://api.example.com/posts', ['limit' => 10])
      ->addPost('create', 'https://api.example.com/create', ['name' => 'Test']);

$results = $async->executeAll();

foreach ($results as $requestId => $result) {
    if ($result['success']) {
        echo "Request {$requestId}: {$result['body']}\n";
    }
}
```

### Fire-and-Forget (Non-Blocking)

```php
use Gemvc\Http\Client\AsyncHttpClient;

// Perfect for APM logging, analytics, or background tasks
$apm = new AsyncHttpClient();
$apm->setTimeouts(2, 5)
    ->addPost('apm-log', 'https://apm.example.com/log', [
        'endpoint' => '/api/User/list',
        'duration' => 0.123,
        'status' => 200
    ])
    ->fireAndForget(); // ⚡ Does NOT block!
```

## Environment-Specific Implementations

The package provides environment-specific implementations:

- **SyncHttpClient** - Apache/Nginx synchronous implementation
- **SwooleSyncHttpClient** - Swoole-optimized synchronous implementation
- **AsyncHttpClient** - Apache/Nginx asynchronous implementation
- **SwooleAsyncHttpClient** - Swoole-optimized asynchronous implementation

## API Reference

### SyncHttpClient Methods

```php
// HTTP Methods
get(string $url, array $queryParams = []): string|false
post(string $url, array $data = []): string|false
put(string $url, array $data = []): string|false
postForm(string $url, array $fields = []): string|false
postMultipart(string $url, array $fields = [], array $files = []): string|false
postRaw(string $url, string $body, string $contentType): string|false

// Configuration
setTimeouts(int $connectTimeout, int $timeout): self
setSsl(?string $cert, ?string $key, ?string $ca = null, bool $verifyPeer = true, int $verifyHost = 2): self
setRetries(int $maxRetries, int $retryDelayMs = 200, array $retryOnHttpCodes = []): self
retryOnNetworkError(bool $retry): self
```

### AsyncHttpClient Methods

```php
// Request Building
addGet(string $requestId, string $url, array $queryParams = [], array $headers = []): self
addPost(string $requestId, string $url, array $data = [], array $headers = []): self
addPut(string $requestId, string $url, array $data = [], array $headers = []): self
addPostForm(string $requestId, string $url, array $fields = [], array $headers = []): self
addPostMultipart(string $requestId, string $url, array $fields = [], array $files = [], array $headers = []): self
addPostRaw(string $requestId, string $url, string $body, string $contentType, array $headers = []): self

// Execution
executeAll(): array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float}>
fireAndForget(): bool
waitForAll(): array

// Configuration
setMaxConcurrency(int $max): self
setTimeouts(int $connectTimeout, int $timeout): self
setSsl(?string $cert, ?string $key, ?string $ca = null, bool $verifyPeer = true, int $verifyHost = 2): self
onResponse(string $requestId, callable $callback): self
clearQueue(): self
getQueueSize(): int
```

## Framework Integration (GEMVC)

In GEMVC framework, the package is automatically used via wrapper classes:

```php
// Framework automatically selects implementation based on environment
$api = new \Gemvc\Http\ApiCall(); // Uses package internally
$api->get('https://api.example.com/data');
```

The framework provides:
- Environment detection via `WebserverDetector`
- Seamless integration with existing `ApiCall` and `AsyncApiCall` classes
- 100% backward compatibility

## Testing

```bash
composer test
```

## License

MIT License - See LICENSE file for details

## Contributing

Contributions welcome! Please follow:
- PSR-12 coding standards
- PHPStan Level 9 type safety
- Comprehensive test coverage

---

Made with ❤️ by Ali Khorsandfard