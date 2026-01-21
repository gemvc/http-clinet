<?php

namespace Gemvc\Http\Client;

use Gemvc\Http\Client\Exception\HttpClientException;

/**
 * Abstract base class for HTTP clients
 * 
 * Provides common configuration properties and methods
 * shared across all HTTP client implementations.
 */
abstract class AbstractHttpClient
{
    /**
     * Connection timeout in seconds
     */
    protected int $connect_timeout = 30;

    /**
     * Total request timeout in seconds
     */
    protected int $timeout = 60;

    /**
     * SSL client certificate path
     */
    protected ?string $ssl_cert = null;

    /**
     * SSL client private key path
     */
    protected ?string $ssl_key = null;

    /**
     * CA certificate path
     */
    protected ?string $ssl_ca = null;

    /**
     * Verify peer flag
     */
    protected bool $ssl_verify_peer = true;

    /**
     * Verify host setting: 0, 1, or 2
     */
    protected int $ssl_verify_host = 2;

    /**
     * Maximum retry attempts (0 = no retries)
     */
    protected int $max_retries = 0;

    /**
     * Delay between retries in milliseconds
     */
    protected int $retry_delay_ms = 200;

    /**
     * HTTP codes that trigger a retry
     * 
     * @var array<int>
     */
    protected array $retry_on_http_codes = [429, 500, 502, 503, 504];

    /**
     * Retry on network error (cURL error) if true
     */
    protected bool $retry_on_network_error = true;

    /**
     * Default user agent
     */
    protected string $userAgent = 'gemserver';

    /**
     * Array of exceptions/errors that occurred during requests
     * 
     * Can be checked with: if($this->errors) or if(!$this->errors)
     * 
     * @var array<HttpClientException>
     */
    public array $errors = [];

    /**
     * Configure connection and total timeouts (seconds)
     */
    public function setTimeouts(int $connectTimeout, int $timeout): static
    {
        $this->connect_timeout = max(0, $connectTimeout);
        $this->timeout = max(0, $timeout);
        return $this;
    }

    /**
     * Configure SSL client options
     * 
     * @param string|null $certPath
     * @param string|null $keyPath
     * @param string|null $caPath
     * @param bool $verifyPeer
     * @param int $verifyHost
     * @return static
     */
    public function setSsl(
        ?string $certPath,
        ?string $keyPath,
        ?string $caPath = null,
        bool $verifyPeer = true,
        int $verifyHost = 2
    ): static {
        $this->ssl_cert = $certPath;
        $this->ssl_key = $keyPath;
        $this->ssl_ca = $caPath;
        $this->ssl_verify_peer = $verifyPeer;
        $this->ssl_verify_host = $verifyHost;
        return $this;
    }

    /**
     * Configure retry behavior
     * 
     * @param int $maxRetries
     * @param int $retryDelayMs
     * @param array<int> $retryOnHttpCodes
     * @return static
     */
    public function setRetries(int $maxRetries, int $retryDelayMs = 200, array $retryOnHttpCodes = []): static
    {
        $this->max_retries = max(0, $maxRetries);
        $this->retry_delay_ms = max(0, $retryDelayMs);
        if (!empty($retryOnHttpCodes)) {
            $this->retry_on_http_codes = array_values(array_unique(array_map('intval', $retryOnHttpCodes)));
        }
        return $this;
    }

    /**
     * Enable/disable retry on network errors
     * 
     * @return static
     */
    public function retryOnNetworkError(bool $retry): static
    {
        $this->retry_on_network_error = $retry;
        return $this;
    }

    /**
     * Set custom user agent
     * 
     * @return static
     */
    public function setUserAgent(string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Check if a request should be retried based on error and HTTP code
     * 
     * @param string $error cURL error message
     * @param int $httpCode HTTP response code
     * @return bool
     */
    protected function shouldRetry(string $error, int $httpCode): bool
    {
        if ($this->max_retries === 0) {
            return false;
        }

        return ($this->retry_on_network_error && $error !== '') ||
            in_array($httpCode, $this->retry_on_http_codes, true);
    }

    /**
     * Sleep for retry delay
     */
    protected function waitForRetry(): void
    {
        if ($this->retry_delay_ms > 0) {
            usleep($this->retry_delay_ms * 1000);
        }
    }

    /**
     * Add an error/exception to the errors array
     * 
     * @param HttpClientException $exception
     * @return static
     */
    protected function addError(HttpClientException $exception): static
    {
        $this->errors[] = $exception;
        return $this;
    }

    /**
     * Clear all stored errors
     * 
     * @return static
     */
    public function clearErrors(): static
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Check if there are any errors
     * 
     * @return bool True if errors exist
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all stored errors/exceptions
     * 
     * @return array<HttpClientException>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the last error/exception
     * 
     * @return HttpClientException|null
     */
    public function getLastError(): ?HttpClientException
    {
        return !empty($this->errors) ? end($this->errors) : null;
    }
}
