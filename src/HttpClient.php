<?php

namespace Gemvc\Http\Client;

use Gemvc\Http\Client\Exception\HttpClientException;

/**
 * Synchronous HTTP Client (Blocking API calls) for Apache/Nginx environments
 */
class HttpClient extends AbstractHttpClient implements IHttpClient
{
    use CurlClientTrait;

    /**
     * Last cURL error message.
     */
    public ?string $error;

    /**
     * HTTP response code from last request.
     */
    public int $http_response_code;

    /**
     * User headers: ['Header-Name' => 'value']
     * @var array<string>
     */
    public array $header;

    public string $method;

    /**
     * User payload for legacy JSON flow.
     * @var array<mixed>
     */
    public array $data;

    /**
     * @var null|string|array<string>
     */
    public null|string|array $authorizationHeader;

    /**
     * Response body as string on success, or false on failure.
     */
    public bool|string $responseBody;

    /**
     * Files for multipart: ['field' => '/path/to/file']
     * @var array<mixed>
     */
    public array $files;

    private ?string $rawBody = null;
    /**
     * Form fields (application/x-www-form-urlencoded or multipart/form-data).
     * @var array<string, mixed>|null
     */
    private ?array $formFields = null;
    private bool $throwExceptions = true;

    public function throwExceptions(bool $throw): self
    {
        $this->throwExceptions = $throw;
        return $this;
    }

    public function __construct()
    {
        // Legacy defaults
        $this->connect_timeout = 0;
        $this->timeout = 0;
        $this->userAgent = 'gemserver';

        $this->error = 'call not initialized';
        $this->http_response_code = 0;
        $this->data = [];
        $this->authorizationHeader = null;
        $this->header = [];
        $this->files = [];
        $this->responseBody = false;
        $this->method = 'GET';
    }

    // --- Public Fluent API ---

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
     * @param array<string, mixed> $files
     */
    public function postMultipart(string $remoteApiUrl, array $fields = [], array $files = []): string|false
    {
        $this->method = 'POST';
        $this->formFields = $fields;
        $this->files = $files;
        $this->rawBody = null;
        return $this->call($remoteApiUrl);
    }

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
     * @param array<string, mixed> $queryParams
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

    // --- Core Logic ---

    /**
     * Perform the API call with Retry Logic.
     */
    private function call(string $remoteApiUrl): string|false
    {
        // Reset state
        $this->responseBody = false;
        $this->http_response_code = 0;
        $this->error = '';
        $this->clearErrors();

        $attempts = $this->max_retries + 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $ch = curl_init($remoteApiUrl);

            if ($ch === false) {
                // Handle initialization failure (rare)
                $this->http_response_code = 500;
                $this->error = "remote api $remoteApiUrl initialization failed";
                $exception = $this->createException($remoteApiUrl, $this->error, 500, 0);
                $this->addError($exception);

                if ($this->throwExceptions)
                    throw $exception;
                return false;
            }

            // 1. Apply Trait Options (Timeout, SSL, etc.)
            $this->applyCommonCurlOptions($ch);

            // 2. Set Method
            $this->setMethod($ch);

            // 3. Prepare Payload (Data & Files)
            // This MUST be called before headers, as strict multipart might affect Content-Type
            $this->preparePayload($ch);

            // 4. Finalize Headers (CRITICAL FIX: Set all headers at once)
            $this->finalizeHeaders($ch);

            // 5. Execute
            $this->responseBody = curl_exec($ch);
            $this->http_response_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->error = curl_error($ch);

            $curlErrorCode = $this->getCurlErrorCode($ch);

            curl_close($ch);

            // 6. Error Handling
            // Network error (empty body OR curl error string present)
            $hasNetworkError = !is_string($this->responseBody) || $this->error !== '';

            if ($hasNetworkError) {
                $exception = $this->createException(
                    $remoteApiUrl,
                    (string) $this->error,
                    $this->http_response_code,
                    $curlErrorCode
                );
                $lastException = $exception;
                $this->addError($exception);

                // Retry? (Uses parent logic + blocking wait)
                if ($this->shouldRetry((string) $this->error, $this->http_response_code) && $attempt < $attempts) {
                    $this->waitForRetry(); // Parent uses usleep() which is correct here
                    continue;
                }

                if ($this->throwExceptions)
                    throw $exception;
                return false;
            }

            // Success (Return body even if HTTP 4xx/5xx)
            return is_string($this->responseBody) ? $this->responseBody : false;
        }

        // Final failure after retries
        if ($lastException !== null) {
            if (!in_array($lastException, $this->errors, true)) {
                $this->addError($lastException);
            }
            if ($this->throwExceptions)
                throw $lastException;
        }
        return false;
    }

    /**
     * Set the HTTP method for the request (legacy behavior preserved).
     *
     * @param \CurlHandle $ch
     */
    private function setMethod(\CurlHandle $ch): void
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
     * Handles Raw, Form, Multipart, and JSON payloads
     */
    private function preparePayload(\CurlHandle $ch): void
    {
        // A. Raw Body (explicit postRaw)
        if ($this->rawBody !== null && in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->rawBody);
            return;
        }

        // B. Form Fields / Multipart
        if (in_array($this->method, ['POST', 'PUT']) && ($this->formFields !== null || !empty($this->files))) {
            $postFields = $this->formFields ?? [];

            // Add files using CURLFile (Modern PHP)
            if (!empty($this->files)) {
                foreach ($this->files as $key => $value) {
                    if (is_string($value) && is_file($value)) {
                        $postFields[$key] = new \CURLFile($value);
                    }
                }
            }
            // Note: cURL handles Content-Type for multipart automatically when array is passed
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            return;
        }

        // C. Legacy JSON (Default behavior for arrays)
        if ($this->method === 'POST' || $this->method === 'PUT') {
            try {
                $data_to_send = json_encode($this->data, JSON_THROW_ON_ERROR);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_to_send);

                // Ensure Content-Type is set for JSON
                if (!isset($this->header['Content-Type'])) {
                    $this->header['Content-Type'] = 'application/json';
                }
            } catch (\JsonException $e) {
                throw new HttpClientException("JSON Encoding Failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Aggregates all headers and sets them ONCE to prevent overwriting.
     */
    private function finalizeHeaders(\CurlHandle $ch): void
    {
        $finalHeaders = [];

        // 1. User Headers (including Content-Type if set in preparePayload)
        foreach ($this->header as $key => $value) {
            $finalHeaders[] = "$key: $value";
        }

        // 2. Authorization (Legacy property support)
        if (is_string($this->authorizationHeader) && !empty($this->authorizationHeader)) {
            $finalHeaders[] = 'Authorization: ' . $this->authorizationHeader;
        }

        // 3. Set All Headers
        if (!empty($finalHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        }
    }
}