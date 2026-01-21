<?php

namespace Gemvc\Http\Client;

use Gemvc\Http\Client\Exception\HttpClientException;

/**
 * Asynchronous HTTP Client for Apache/Nginx environments
 * 
 * Uses curl_multi for concurrent request execution with support for:
 * - Batch request processing
 * - Concurrent execution with configurable limits
 * - Fire-and-forget mode for non-blocking background tasks
 * - Response callbacks
 * - All request types (GET, POST, PUT, form, multipart, raw)
 */
class AsyncHttpClient extends AbstractHttpClient implements IHttpClient
{
    use CurlClientTrait;
    /**
     * Pending requests queue
     * 
     * @var array<int, array{id: string, url: string, method: string, data: array<mixed>, headers: array<string>, options: array<string, mixed>}>
     */
    private array $requestQueue = [];

    /**
     * Request metadata for tracking
     * 
     * @var array<int, array{id: string, url: string, method: string, startTime: float}>
     */
    private array $requestMetadata = [];

    /**
     * Maximum concurrent requests (0 = unlimited)
     */
    private int $maxConcurrency = 10;

    /**
     * Response callbacks: ['requestId' => callable]
     * 
     * @var array<string, callable>
     */
    private array $responseCallbacks = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set async-specific defaults
        $this->userAgent = 'gemserver-async';
    }

    /**
     * Set maximum concurrent requests
     */
    public function setMaxConcurrency(int $maxConcurrency): self
    {
        $this->maxConcurrency = max(1, $maxConcurrency);
        return $this;
    }

    /**
     * Add a GET request to the queue
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<string, mixed> $queryParams Query parameters
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addGet(string $requestId, string $url, array $queryParams = [], array $headers = []): self
    {
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'GET',
            'data' => [],
            'headers' => $headers,
            'options' => []
        ];

        return $this;
    }

    /**
     * Add a POST request to the queue
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<mixed> $postData POST data (will be JSON encoded)
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPost(string $requestId, string $url, array $postData = [], array $headers = []): self
    {
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => $postData,
            'headers' => $headers,
            'options' => []
        ];

        return $this;
    }

    /**
     * Add a PUT request to the queue
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<mixed> $putData PUT data (will be JSON encoded)
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPut(string $requestId, string $url, array $putData = [], array $headers = []): self
    {
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'PUT',
            'data' => $putData,
            'headers' => $headers,
            'options' => []
        ];

        return $this;
    }

    /**
     * Add a POST form request (application/x-www-form-urlencoded)
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<string, mixed> $formFields Form fields
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPostForm(string $requestId, string $url, array $formFields = [], array $headers = []): self
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => $formFields,
            'headers' => $headers,
            'options' => ['form' => true]
        ];

        return $this;
    }

    /**
     * Add a POST multipart request with files
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param array<string, mixed> $formFields Form fields
     * @param array<string, string> $files Map of field => filePath
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPostMultipart(string $requestId, string $url, array $formFields = [], array $files = [], array $headers = []): self
    {
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => $formFields,
            'headers' => $headers,
            'options' => ['multipart' => true, 'files' => $files]
        ];

        return $this;
    }

    /**
     * Add a POST raw request
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param string $rawBody Raw request body
     * @param string $contentType Content-Type header
     * @param array<string, string> $headers Custom headers
     * @return self
     */
    public function addPostRaw(string $requestId, string $url, string $rawBody, string $contentType, array $headers = []): self
    {
        $headers['Content-Type'] = $contentType;
        $this->requestQueue[] = [
            'id' => $requestId,
            'url' => $url,
            'method' => 'POST',
            'data' => [],
            'headers' => $headers,
            'options' => ['raw' => $rawBody]
        ];

        return $this;
    }

    /**
     * Add a custom request with full control
     * 
     * @param string $requestId Unique identifier for this request
     * @param string $url Request URL
     * @param string $method HTTP method
     * @param array<mixed> $data Request data
     * @param array<string, string> $headers Custom headers
     * @param array<string, mixed> $options Custom options
     * @return self
     */
    public function addRequest(string $requestId, string $url, string $method = 'GET', array $data = [], array $headers = [], array $options = []): self
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
     * @param string $requestId Request identifier
     * @param callable $callback Callback function(response, requestId)
     * @return self
     */
    public function onResponse(string $requestId, callable $callback): self
    {
        $this->responseCallbacks[$requestId] = $callback;
        return $this;
    }

    /**
     * Execute all queued requests concurrently
     * 
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float, exception: HttpClientException|null, exception_type: string|null}>
     */
    public function executeAll(): array
    {
        if (empty($this->requestQueue)) {
            return [];
        }

        // Clear previous errors
        $this->clearErrors();

        $results = [];
        $multiHandle = curl_multi_init();

        $handleMap = []; // Map curl handle resource ID to request ID
        $queueIndex = 0;
        $activeRequests = 0;

        // Process requests in batches based on maxConcurrency
        while ($queueIndex < count($this->requestQueue) || $activeRequests > 0) {
            // Add new requests up to maxConcurrency limit
            while ($activeRequests < $this->maxConcurrency && $queueIndex < count($this->requestQueue)) {
                $request = $this->requestQueue[$queueIndex];
                $ch = $this->createCurlHandle($request);

                if ($ch !== false) {
                    $handleId = (int) $ch;
                    $handleMap[$handleId] = $request['id'];
                    $this->requestMetadata[$handleId] = [
                        'id' => $request['id'],
                        'url' => $request['url'],
                        'method' => $request['method'],
                        'startTime' => microtime(true)
                    ];

                    curl_multi_add_handle($multiHandle, $ch);
                    $activeRequests++;
                } else {
                    // Failed to create curl handle - create and store exception
                    $exception = $this->createException(
                        $request['url'],
                        "Failed to initialize cURL handle for request {$request['id']}",
                        0,
                        0
                    );
                    $this->addError($exception);

                    // Add error result for this request
                    $results[$request['id']] = [
                        'success' => false,
                        'body' => false,
                        'http_code' => 0,
                        'error' => $exception->getMessage(),
                        'duration' => 0.0,
                        'exception' => $exception,
                        'exception_type' => get_class($exception)
                    ];
                }

                $queueIndex++;
            }

            // Execute active requests
            if ($activeRequests > 0) {
                $stillRunning = 0;
                curl_multi_exec($multiHandle, $stillRunning);

                // Process completed requests
                while ($info = curl_multi_info_read($multiHandle)) {
                    if ($info['msg'] === CURLMSG_DONE) {
                        $ch = $info['handle'];
                        $handleId = (int) $ch;
                        $requestId = $handleMap[$handleId] ?? 'unknown';

                        $result = $this->processResponse($ch, $requestId, $handleId);
                        $results[$requestId] = $result;

                        // Execute callback if set
                        if (isset($this->responseCallbacks[$requestId])) {
                            ($this->responseCallbacks[$requestId])($result, $requestId);
                        }

                        curl_multi_remove_handle($multiHandle, $ch);
                        unset($this->requestMetadata[$handleId]);
                        unset($handleMap[$handleId]);
                        $activeRequests--;
                    }
                }

                // Small delay to prevent CPU spinning
                if ($stillRunning > 0) {
                    curl_multi_select($multiHandle, 0.01);
                }
            }
        }

        curl_multi_close($multiHandle);
        $this->requestQueue = [];
        $this->responseCallbacks = [];

        return $results;
    }

    /**
     * Execute requests and wait for all to complete (alias for executeAll)
     * 
     * @return array<string, array{success: bool, body: string|false, http_code: int, error: string, duration: float, exception: HttpClientException|null, exception_type: string|null}>
     */
    public function waitForAll(): array
    {
        return $this->executeAll();
    }

    /**
     * Fire and forget - Execute requests in background without blocking
     * 
     * This method is perfect for APM logging, analytics, or any non-critical
     * background tasks. It will NOT block your main application response.
     * For Apache/Nginx: Uses fastcgi_finish_request() to send response first
     * @return bool True if background execution was initiated
     */
    public function fireAndForget(): bool
    {
        if (empty($this->requestQueue)) {
            return false;
        }

        // For Apache/Nginx with PHP-FPM: finish request first, then execute
        // Note: fastcgi_finish_request() may have already been called in shutdown function
        // If it was already called, this will do nothing (safe to call multiple times)
        if (function_exists('fastcgi_finish_request')) {
            // Try to send response to client immediately (if not already sent)
            // This is safe to call even if response was already sent
            @fastcgi_finish_request();

            // Now execute requests in background (client already got or getting response)
            $this->executeAll();
            return true;
        }



        // Fallback: Execute with very short timeout and minimal blocking
        // Set aggressive timeouts to minimize blocking
        $originalTimeout = $this->timeout;
        $originalConnectTimeout = $this->connect_timeout;

        $this->timeout = 1; // 1 second max
        $this->connect_timeout = 1; // 1 second max

        // Execute but don't wait for all results
        $this->executeAll();

        // Restore original timeouts
        $this->timeout = $originalTimeout;
        $this->connect_timeout = $originalConnectTimeout;

        return true;
    }



    /**
     * Clear the request queue
     * 
     * @return self
     */
    public function clearQueue(): self
    {
        $this->requestQueue = [];
        $this->responseCallbacks = [];
        return $this;
    }

    /**
     * Get queue size
     */
    public function getQueueSize(): int
    {
        return count($this->requestQueue);
    }

    /**
     * Create a curl handle for a request
     * 
     * @param array{id: string, url: string, method: string, data: array<mixed>, headers: array<string>, options: array<string, mixed>} $request Request configuration
     * @return \CurlHandle|false
     */
    private function createCurlHandle(array $request): \CurlHandle|false
    {
        $ch = curl_init($request['url']);
        if ($ch === false) {
            return false;
        }

        // Apply common cURL options (timeouts, SSL, user agent)
        $this->applyCommonCurlOptions($ch);

        // Headers
        $headers = ['Content-Type: application/json'];
        foreach ($request['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Method and data
        $this->setMethodAndData($ch, $request);

        return $ch;
    }

    /**
     * Set HTTP method and request data
     * 
     * @param \CurlHandle $ch
     * @param array{id: string, url: string, method: string, data: array<mixed>, headers: array<string>, options: array<string, mixed>} $request
     */
    private function setMethodAndData(\CurlHandle $ch, array $request): void
    {
        $method = $request['method'];
        $data = $request['data'];
        $options = $request['options'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        } elseif ($method !== 'GET' && $method !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Set request body
        if (isset($options['raw']) && is_string($options['raw'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['raw']);
        } elseif (isset($options['multipart']) && $options['multipart'] === true) {
            $postFields = $data;
            if (isset($options['files']) && is_array($options['files'])) {
                foreach ($options['files'] as $key => $filePath) {
                    if (is_string($filePath) && is_file($filePath)) {
                        $postFields[$key] = new \CURLFile($filePath);
                    }
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } elseif (isset($options['form']) && $options['form'] === true) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'POST' || $method === 'PUT') {
            $jsonData = json_encode($data);
            if (is_string($jsonData)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            }
        }
    }

    /**
     * Process response from curl handle
     * 
     * @param \CurlHandle $ch
     * @param string $requestId
     * @param int $handleId
     * @return array{success: bool, body: string|false, http_code: int, error: string, duration: float, exception: HttpClientException|null, exception_type: string|null}
     */
    private function processResponse(\CurlHandle $ch, string $requestId, int $handleId): array
    {
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlErrorCode = $this->getCurlErrorCode($ch);
        $metadata = $this->requestMetadata[$handleId] ?? null;
        $url = $metadata['url'] ?? 'unknown';
        $duration = $metadata ? (microtime(true) - $metadata['startTime']) : 0.0;

        // Check for actual errors (network/timeout, not HTTP error codes)
        // HTTP error codes (4xx, 5xx) are valid responses
        $hasNetworkError = !is_string($body) || $error !== '';
        $success = is_string($body) && $error === '' && $httpCode >= 200 && $httpCode < 400;
        $responseBody = is_string($body) ? $body : false;

        // Create exception only for network/timeout errors, not HTTP error codes
        $exception = null;
        $exceptionType = null;
        if ($hasNetworkError) {
            $exception = $this->createException(
                $url,
                $error ?: "Request failed",
                $httpCode,
                $curlErrorCode
            );
            $exceptionType = get_class($exception);

            // Store exception in errors array
            $this->addError($exception);
        }

        return [
            'success' => $success,
            'body' => $responseBody,
            'http_code' => $httpCode,
            'error' => $error,
            'duration' => $duration,
            'exception' => $exception,
            'exception_type' => $exceptionType
        ];
    }
}
