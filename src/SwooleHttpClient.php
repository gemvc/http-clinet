<?php

namespace Gemvc\Http\Client;

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\Barrier;

/**
 * Swoole-native Asynchronous HTTP Client
 * 
 * Uses Swoole Coroutines for high-performance non-blocking HTTP requests.
 * optimized for "fire and forget" and concurrent execution.
 */
class SwooleHttpClient extends AbstractHttpClient implements IHttpClient
{
    /**
     * Pending requests queue
     * 
     * @var array<int, array{id: string, url: string, method: string, data: mixed, headers: array<string, string>, options: array<string, mixed>}>
     */
    private array $requestQueue = [];

    /**
     * Response callbacks: ['requestId' => callable]
     * 
     * @var array<string, callable>
     */
    private array $responseCallbacks = [];

    /**
     * Maximum concurrent requests
     */
    private int $maxConcurrency = 20;

    public function __construct()
    {
        $this->userAgent = 'gemvc-swoole-client';
    }

    /**
     * Set maximum concurrent requests
     * 
     * @param int $maxConcurrency
     * @return static
     */
    public function setMaxConcurrency(int $maxConcurrency): static
    {
        $this->maxConcurrency = max(1, $maxConcurrency);
        return $this;
    }

    /**
     * Add a GET request to the queue
     * 
     * @param string $requestId
     * @param string $url
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $headers
     * @return static
     */
    public function addGet(string $requestId, string $url, array $queryParams = [], array $headers = []): static
    {
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        return $this->addRequest($requestId, $url, 'GET', [], $headers);
    }

    /**
     * Add a POST request to the queue
     * 
     * @param string $requestId
     * @param string $url
     * @param array<mixed> $postData
     * @param array<string, string> $headers
     * @return static
     */
    public function addPost(string $requestId, string $url, array $postData = [], array $headers = []): static
    {
        return $this->addRequest($requestId, $url, 'POST', $postData, $headers);
    }

    /**
     * Add a PUT request to the queue
     * 
     * @param string $requestId
     * @param string $url
     * @param array<mixed> $putData
     * @param array<string, string> $headers
     * @return static
     */
    public function addPut(string $requestId, string $url, array $putData = [], array $headers = []): static
    {
        return $this->addRequest($requestId, $url, 'PUT', $putData, $headers);
    }

    /**
     * Add a generic request
     * 
     * @param string $requestId
     * @param string $url
     * @param string $method
     * @param mixed $data
     * @param array<string, string> $headers
     * @param array<string, mixed> $options
     * @return static
     */
    public function addRequest(string $requestId, string $url, string $method = 'GET', mixed $data = [], array $headers = [], array $options = []): static
    {
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => strtoupper($method),
            'data' => $data,
            'headers' => $headers,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Set response callback for a specific request
     * 
     * @param string $requestId
     * @param callable $callback
     * @return static
     */
    public function onResponse(string $requestId, callable $callback): static
    {
        $this->responseCallbacks[$requestId] = $callback;
        return $this;
    }

    /**
     * Fire and forget - Execute requests in background using Coroutines
     * 
     * Spawns a new coroutine to process the queue, allowing the main 
     * execution flow to continue immediately.
     * 
     * @return bool
     */
    public function fireAndForget(): bool
    {
        if (empty($this->requestQueue)) {
            return false;
        }

        // Copy queue to closure scope
        $queue = $this->requestQueue;
        $callbacks = $this->responseCallbacks;

        // Reset local queue immediately
        $this->clearQueue();

        // Spawn coroutine
        Coroutine::create(function () use ($queue, $callbacks) {
            $this->processQueue($queue, $callbacks);
        });

        return true;
    }

    /**
     * Execute all queued requests and wait for results
     * 
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float}>
     */
    public function executeAll(): array
    {
        if (empty($this->requestQueue)) {
            return [];
        }

        $queue = $this->requestQueue;
        $callbacks = $this->responseCallbacks;
        $this->clearQueue();

        return $this->processQueue($queue, $callbacks);
    }

    public function clearQueue(): static
    {
        $this->requestQueue = [];
        $this->responseCallbacks = [];
        return $this;
    }

    public function getQueueSize(): int
    {
        return count($this->requestQueue);
    }

    /**
     * Process a queue of requests using Coroutines
     * 
     * @param array<int, array{id: string, url: string, method: string, data: mixed, headers: array<string, string>, options: array<string, mixed>}> $queue
     * @param array<string, callable> $callbacks
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float}>
     */
    private function processQueue(array $queue, array $callbacks): array
    {
        $results = [];
        $barrier = Barrier::make();
        $concurrency = $this->maxConcurrency;

        // Channel to limit concurrency
        $channel = new Coroutine\Channel($concurrency);

        foreach ($queue as $request) {

            // Acquire slot
            $channel->push(true);

            Coroutine::create(function () use ($request, $callbacks, $barrier, $channel, &$results) {
                try {
                    $result = $this->executeSingleRequest($request);
                    $results[$request['id']] = $result;

                    if (isset($callbacks[$request['id']])) {
                        try {
                            ($callbacks[$request['id']])($result, $request['id']);
                        } catch (\Throwable $e) {
                            // Callback error ignored
                        }
                    }
                } finally {
                    $channel->pop(); // Release slot
                }
            });
        }

        Barrier::wait($barrier);
        return $results;
    }

    /**
     * Execute a single request with Safe Retry Logic
     * 
     * @param array{id: string, url: string, method: string, data: mixed, headers: array<string, string>, options: array<string, mixed>} $request
     * @return array{success: bool, body: string, http_code: int, error: string, duration: float}
     */
    private function executeSingleRequest(array $request): array
    {
        $urlParts = parse_url($request['url']);
        if (!$urlParts || !isset($urlParts['host'])) {
            return $this->createErrorResult("Invalid URL: {$request['url']}");
        }

        $host = $urlParts['host'];
        $scheme = $urlParts['scheme'] ?? 'http';
        $port = $urlParts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $ssl = ($scheme === 'https');
        $path = ($urlParts['path'] ?? '/') . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');

        // Retry Loop Control Variables
        $attempt = 0;
        // Defaults to 0 from AbstractHttpClient. If not set by user, loop runs exactly once.
        $maxRetries = $this->max_retries;

        do {
            $client = new Client($host, $port, $ssl);

            // 1. Apply Configuration
            $settings = [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connect_timeout,
            ];

            // 2. Apply SSL Context (Critical for mTLS)
            if ($ssl) {
                $settings = array_merge($settings, [
                    'ssl_cert_file' => $this->ssl_cert,
                    'ssl_key_file' => $this->ssl_key,
                    'ssl_cafile' => $this->ssl_ca,
                    'ssl_verify_peer' => $this->ssl_verify_peer,
                ]);
            }
            $client->set($settings);

            // 3. Prepare Headers & Method
            $headers = $request['headers'];
            if (!isset($headers['User-Agent'])) {
                $headers['User-Agent'] = $this->userAgent;
            }
            $client->setHeaders($headers);
            $client->setMethod($request['method']);

            // 4. Prepare Body (JSON Safety)
            if (!empty($request['data'])) {
                if (isset($headers['Content-Type']) && str_contains($headers['Content-Type'], 'application/json')) {
                    try {
                        $jsonData = json_encode($request['data'], JSON_THROW_ON_ERROR);
                        $client->setData($jsonData);
                    } catch (\JsonException $e) {
                        $client->close();
                        // Fatal Error: Malformed JSON cannot be fixed by retrying.
                        return $this->createErrorResult("JSON Encoding Failed: " . $e->getMessage());
                    }
                } else {
                    $client->setData($request['data']);
                }
            }

            // 5. Execute Request
            $startTime = microtime(true);
            $success = $client->execute($path);
            $duration = microtime(true) - $startTime;

            // Capture State
            $statusCode = (int) $client->getStatusCode();
            $body = (string) $client->getBody();
            $errCode = $client->errCode;
            $errMsg = $client->errMsg;

            // Clean up connection immediately
            $client->close();

            // --- Exit Condition 1: Success ---
            if ($success && $statusCode > 0) {
                return [
                    'success' => $statusCode >= 200 && $statusCode < 400,
                    'body' => $body,
                    'http_code' => $statusCode,
                    'error' => '',
                    'duration' => $duration
                ];
            }

            // --- Retry Evaluation ---
            // Check parent logic: is this error type retriable?
            if ($this->shouldRetry($errMsg, $statusCode)) {
                $attempt++;

                // --- Exit Condition 2: Max Retries Limit ---
                if ($attempt <= $maxRetries) {
                    $this->waitForRetry(); // Non-blocking Coroutine Sleep
                    continue; // Restart Loop
                }
            }

            // --- Exit Condition 3: Final Failure ---
            return $this->createErrorResult(
                "Swoole Client Error ($errCode): $errMsg. HTTP: $statusCode",
                $duration
            );

        } while ($attempt <= $maxRetries); // Final safety guard

        return $this->createErrorResult("Unknown Execution Error");
    }

    /**
     * Create error result
     * 
     * @return array{success: bool, body: string, http_code: int, error: string, duration: float}
     */
    private function createErrorResult(string $message, float $duration = 0.0): array
    {
        // Add to main error list
        // Note: Thread safety might be an issue if we modify $this->errors directly from coroutines?
        // PHP Arrays are Copy-On-Write, but $this is shared.
        // For fireAndForget, errors might not be retrievable.

        return [
            'success' => false,
            'body' => '',
            'http_code' => 0,
            'error' => $message,
            'duration' => $duration
        ];
    }

    /**
     * Sleep for retry delay using Coroutine::sleep
     * 
     * Overrides parent::waitForRetry to be non-blocking in Swoole
     */
    protected function waitForRetry(): void
    {
        if ($this->retry_delay_ms > 0) {
            // Use Coroutine::sleep (seconds) instead of usleep to avoid blocking
            Coroutine::sleep($this->retry_delay_ms / 1000);
        }
    }
}
