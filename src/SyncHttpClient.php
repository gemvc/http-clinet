<?php

namespace Gemvc\Http\Client;

use Gemvc\Http\Client\Exception\HttpClientException;
use Gemvc\Http\Client\Exception\NetworkException;
use Gemvc\Http\Client\Exception\TimeoutException;

/**
 * Synchronous HTTP Client for Apache/Nginx environments
 * 
 * Uses cURL for synchronous HTTP requests with support for:
 * - GET, POST, PUT requests
 * - Form data, multipart, and raw body
 * - SSL/TLS configuration
 * - Retry logic with exponential backoff
 * - Configurable timeouts
 */
class SyncHttpClient extends AbstractHttpClient
{
    /**
     * Last cURL error message (empty string if none).
     * Defaults to 'call not initialized' until call() runs.
     */
    public ?string $error;

    /**
     * HTTP response code from last request (0 if not executed).
     */
    public int $http_response_code;

    /**
     * User headers as an associative array: ['Header-Name' => 'value']
     * Legacy name and type kept for backward compatibility.
     *
     * @var array<string>
     */
    public array $header;

    /**
     * HTTP method. One of GET, POST, PUT, or custom.
     */
    public string $method;

    /**
     * User payload for legacy JSON flow.
     *
     * @var array<mixed>
     */
    public array $data;

    /**
     * Authorization header (legacy behavior):
     * - If string: setAuthorization() will overwrite previous header list
     * - If array|string[]: not used by legacy logic; kept for compatibility
     *
     * @var null|string|array<string>
     */
    public null|string|array $authorizationHeader;

    /**
     * Response body as string on success, or false on failure.
     */
    public bool|string $responseBody;

    /**
     * Files for legacy multipart flow: ['field' => '/path/to/file']
     *
     * @var array<mixed>
     */
    public array $files;

    /**
     * Raw request body (when using postRaw()).
     */
    private ?string $rawBody = null;

    /**
     * Form fields (application/x-www-form-urlencoded or multipart/form-data).
     *
     * @var array<string,mixed>|null
     */
    private ?array $formFields = null;

    /**
     * Whether to throw exceptions on errors (default: true for better error handling)
     */
    private bool $throwExceptions = true;

    /**
     * Control whether exceptions are thrown on errors
     * 
     * @param bool $throw If true, exceptions will be thrown. If false, errors are stored in $errors array.
     * @return self
     */
    public function throwExceptions(bool $throw): self
    {
        $this->throwExceptions = $throw;
        return $this;
    }

    public function __construct()
    {
        // Set legacy defaults (0 = no timeout, uses cURL defaults)
        $this->connect_timeout = 0;
        $this->timeout = 0;
        $this->userAgent = 'gemserver';
        
        // Initialize legacy public properties
        $this->error = 'call not initialized';
        $this->http_response_code = 0;
        $this->data = [];
        $this->authorizationHeader = null;
        $this->header = [];
        $this->files = [];
        $this->responseBody = false;
        $this->method = 'GET';
    }

    /**
     * POST with application/x-www-form-urlencoded body (opt-in).
     *
     * @param array<string, mixed> $fields
     */
    public function postForm(string $remoteApiUrl, array $fields = []): string|false
    {
        $this->method = 'POST';
        $this->formFields = $fields;
        $this->rawBody = null;
        return $this->call($remoteApiUrl);
    }

    /**
     * POST multipart/form-data with files (opt-in).
     *
     * @param array<string, mixed> $fields
     * @param array<string, string> $files
     */
    public function postMultipart(string $remoteApiUrl, array $fields = [], array $files = []): string|false
    {
        $this->method = 'POST';
        $this->formFields = $fields;
        $this->files = $files;
        $this->rawBody = null;
        return $this->call($remoteApiUrl);
    }

    /**
     * POST with raw body and explicit content type (opt-in).
     */
    public function postRaw(string $remoteApiUrl, string $rawBody, string $contentType): string|false
    {
        $this->method = 'POST';
        $this->rawBody = $rawBody;
        $this->formFields = null;
        $this->header['Content-Type'] = $contentType;
        return $this->call($remoteApiUrl);
    }

    /**
     * Perform a GET request.
     *
     * @param string $remoteApiUrl
     * @param array<string> $queryParams
     */
    public function get(string $remoteApiUrl, array $queryParams = []): string|false
    {
        $this->method = 'GET';
        $this->data = $queryParams;
        $this->rawBody = null;
        $this->formFields = null;

        if (!empty($queryParams)) {
            $remoteApiUrl .= '?' . http_build_query($queryParams);
        }

        return $this->call($remoteApiUrl);
    }

    /**
     * Perform a POST request (legacy JSON behavior preserved).
     *
     * @param string $remoteApiUrl
     * @param array<mixed> $postData
     */
    public function post(string $remoteApiUrl, array $postData = []): string|false
    {
        $this->method = 'POST';
        $this->data = $postData;
        $this->rawBody = null;
        $this->formFields = null;
        return $this->call($remoteApiUrl);
    }

    /**
     * Perform a PUT request (legacy JSON behavior preserved).
     *
     * @param string $remoteApiUrl
     * @param array<mixed> $putData
     */
    public function put(string $remoteApiUrl, array $putData = []): string|false
    {
        $this->method = 'PUT';
        $this->data = $putData;
        $this->rawBody = null;
        $this->formFields = null;
        return $this->call($remoteApiUrl);
    }

    /**
     * Perform the API call.
     * Applies optional timeouts/SSL/retries if configured; otherwise preserves legacy behavior.
     * 
     * @throws \Gemvc\Http\Client\Exception\HttpClientException
     * @throws \Gemvc\Http\Client\Exception\NetworkException
     * @throws \Gemvc\Http\Client\Exception\TimeoutException
     */
    private function call(string $remoteApiUrl): string|false
    {
        // Reset per call
        $this->responseBody = false;
        $this->http_response_code = 0;
        $this->error = '';
        $this->clearErrors(); // Clear previous errors

        $attempts = $this->max_retries + 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $ch = curl_init($remoteApiUrl);
            if ($ch === false) {
                $this->http_response_code = 500;
                $this->error = "remote api $remoteApiUrl is not responding";
                $exception = $this->createException(
                    $remoteApiUrl,
                    $this->error,
                    500,
                    0
                );
                
                // Store exception in errors array
                $this->addError($exception);
                
                if ($this->throwExceptions) {
                    throw $exception;
                }
                return false;
            }

            // Apply common cURL options (timeouts, SSL, user agent)
            $this->applyCommonCurlOptions($ch);

            $this->setMethod($ch);
            $this->setHeaders($ch);
            $this->setAuthorization($ch);
            $this->setData($ch);
            $this->setFiles($ch);

            $this->responseBody = curl_exec($ch);
            $this->http_response_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->error = curl_error($ch);
            $curlErrorCode = $this->getCurlErrorCode($ch);

            curl_close($ch);

            // Check for actual errors (network/timeout, not HTTP error codes)
            // HTTP error codes (4xx, 5xx) are valid responses and should return the body
            $hasNetworkError = !is_string($this->responseBody) || $this->error !== '';

            if ($hasNetworkError) {
                $exception = $this->createException(
                    $remoteApiUrl,
                    $this->error ?: "Request failed",
                    $this->http_response_code,
                    $curlErrorCode
                );
                $lastException = $exception;
                
                // Store exception in errors array
                $this->addError($exception);

                // Use inherited retry logic
                if ($this->shouldRetry($this->error, $this->http_response_code) && $attempt < $attempts) {
                    $this->waitForRetry();
                    continue;
                }

                // All retries exhausted or not retryable
                if ($this->throwExceptions) {
                    throw $exception;
                }
                return false;
            }

            // Success - return response body even if HTTP code is 4xx/5xx
            // HTTP error codes are valid responses, not exceptions
            return is_string($this->responseBody) ? $this->responseBody : false;
        }

        // All retries exhausted
        if ($lastException !== null) {
            // Store last exception if not already stored
            if (!in_array($lastException, $this->errors, true)) {
                $this->addError($lastException);
            }
            
            if ($this->throwExceptions) {
                throw $lastException;
            }
        }
        return false;
    }

    /**
     * Set the HTTP method for the request (legacy behavior preserved).
     *
     * @param \CurlHandle $ch
     */
    private function setMethod($ch): void
    {
        if ($this->method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($this->method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        } elseif ($this->method !== 'GET' && $this->method !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        }
    }

    /**
     * Set the headers for the request (legacy default JSON header preserved).
     *
     * @param \CurlHandle $ch
     */
    private function setHeaders(\CurlHandle $ch): void
    {
        // Legacy default Content-Type
        $headers = ['Content-Type: application/json'];
        foreach ($this->header as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Set the authorization header if present (legacy overwrite behavior preserved).
     *
     * @param \CurlHandle $ch
     */
    private function setAuthorization(\CurlHandle $ch): void
    {
        // Preserve legacy overwrite behavior if string is provided
        if (is_string($this->authorizationHeader)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $this->authorizationHeader]);
        }
    }

    /**
     * Set the data for the request.
     * Priority (opt-in first):
     *  - Raw body (postRaw)
     *  - Form/multipart (postForm/postMultipart)
     *  - Legacy JSON using $this->data (for POST/PUT)
     *
     * @param \CurlHandle $ch
     * @throws HttpClientException when JSON encoding fails in legacy flow
     */
    private function setData(\CurlHandle $ch): void
    {
        // Raw body path (opt-in)
        if ($this->rawBody !== null && ($this->method === 'POST' || $this->method === 'PUT' || $this->method === 'PATCH' || $this->method === 'DELETE')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->rawBody);
            return;
        }

        // Form or multipart (opt-in)
        if (($this->method === 'POST' || $this->method === 'PUT') && ($this->formFields !== null || !empty($this->files))) {
            $postFields = $this->formFields ?? [];
            if (!empty($this->files)) {
                foreach ($this->files as $key => $value) {
                    if (is_string($value) && is_file($value)) {
                        $postFields[$key] = new \CURLFile($value);
                    }
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            return;
        }

        // Legacy JSON path for POST/PUT
        if ($this->method === 'POST' || $this->method === 'PUT') {
            $data_to_send = json_encode($this->data);
            if (!is_string($data_to_send)) {
                $jsonError = json_last_error_msg();
                throw new HttpClientException(
                    "Failed to encode data to JSON format: {$jsonError}",
                    0,
                    null,
                    null,
                    0,
                    0
                );
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_to_send);
        }
    }

    /**
     * Set the files for the request if any (legacy multipart path).
     * Preserved for backward compatibility when only $files is provided.
     *
     * @param \CurlHandle $ch
     */
    private function setFiles(\CurlHandle $ch): bool
    {
        if (!empty($this->files) && ($this->formFields === null)) {
            $postFields = $this->data;
            foreach ($this->files as $key => $value) {
                if (is_string($value)) {
                    $postFields[$key] = new \CURLFile($value);
                    $step_one = curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    $step_two = curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
                    if ($step_one && $step_two) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}