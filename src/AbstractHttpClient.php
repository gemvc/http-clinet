<?php

namespace Gemvc\Http\Client;

use Gemvc\Http\Client\Exception\HttpClientException;
use Gemvc\Http\Client\Exception\NetworkException;
use Gemvc\Http\Client\Exception\TimeoutException;

/**
 * Abstract base class for HTTP clients
 * 
 * Provides common configuration properties and methods
 * shared across all HTTP client implementations.
 */
abstract class AbstractHttpClient implements IHttpClient
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
    public function setTimeouts(int $connectTimeout, int $timeout): self
    {
        $this->connect_timeout = max(0, $connectTimeout);
        $this->timeout = max(0, $timeout);
        return $this;
    }

    /**
     * Configure SSL client options
     */
    public function setSsl(
        ?string $certPath, 
        ?string $keyPath, 
        ?string $caPath = null, 
        bool $verifyPeer = true, 
        int $verifyHost = 2
    ): self {
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
     * @param array<int> $retryOnHttpCodes
     */
    public function setRetries(int $maxRetries, int $retryDelayMs = 200, array $retryOnHttpCodes = []): self
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
     */
    public function retryOnNetworkError(bool $retry): self
    {
        $this->retry_on_network_error = $retry;
        return $this;
    }

    /**
     * Set custom user agent
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Apply common cURL options to a handle
     * 
     * @param \CurlHandle $ch
     */
    protected function applyCommonCurlOptions(\CurlHandle $ch): void
    {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($this->userAgent !== '') {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }

        if ($this->connect_timeout > 0) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
        }
        if ($this->timeout > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        // SSL options
        if ($this->ssl_cert) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->ssl_cert);
        }
        if ($this->ssl_key) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->ssl_key);
        }
        if ($this->ssl_ca) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->ssl_ca);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify_peer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->ssl_verify_host ? 2 : 0);
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
     * Check if a cURL error code indicates a timeout
     * 
     * @param int $curlErrorCode cURL error code
     * @return bool True if it's a timeout error
     */
    protected function isTimeoutError(int $curlErrorCode): bool
    {
        return in_array($curlErrorCode, [
            CURLE_OPERATION_TIMEDOUT,
            CURLE_OPERATION_TIMEOUTED,
        ], true);
    }

    /**
     * Check if a cURL error code indicates a connection timeout
     * 
     * @param int $curlErrorCode cURL error code
     * @return bool True if it's a connection timeout
     */
    protected function isConnectionTimeoutError(int $curlErrorCode): bool
    {
        return $curlErrorCode === CURLE_OPERATION_TIMEDOUT;
    }

    /**
     * Check if a cURL error code indicates a network error
     * 
     * @param int $curlErrorCode cURL error code
     * @return bool True if it's a network error
     */
    protected function isNetworkError(int $curlErrorCode): bool
    {
        return in_array($curlErrorCode, [
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_RECV_ERROR,
            CURLE_SEND_ERROR,
            CURLE_PARTIAL_FILE,
            CURLE_HTTP_POST_ERROR,
            CURLE_SSL_CONNECT_ERROR,
            CURLE_GOT_NOTHING,
        ], true);
    }

    /**
     * Get the network error type from cURL error code
     * 
     * @param int $curlErrorCode cURL error code
     * @return string Network error type constant
     */
    protected function getNetworkErrorType(int $curlErrorCode): string
    {
        return match ($curlErrorCode) {
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY => NetworkException::TYPE_DNS_ERROR,
            CURLE_COULDNT_CONNECT => NetworkException::TYPE_CONNECTION_ERROR,
            CURLE_SSL_CONNECT_ERROR => NetworkException::TYPE_SSL_ERROR,
            CURLE_RECV_ERROR,
            CURLE_PARTIAL_FILE,
            CURLE_GOT_NOTHING => NetworkException::TYPE_RECEIVE_ERROR,
            CURLE_SEND_ERROR,
            CURLE_HTTP_POST_ERROR => NetworkException::TYPE_SEND_ERROR,
            default => NetworkException::TYPE_UNKNOWN,
        };
    }

    /**
     * Get human-readable description of network error type
     * 
     * @param string $errorType Network error type constant
     * @return string Description
     */
    protected function getNetworkErrorTypeDescription(string $errorType): string
    {
        return match ($errorType) {
            NetworkException::TYPE_DNS_ERROR => 'DNS resolution failed',
            NetworkException::TYPE_CONNECTION_ERROR => 'Connection failed',
            NetworkException::TYPE_SSL_ERROR => 'SSL/TLS handshake failed',
            NetworkException::TYPE_RECEIVE_ERROR => 'Data receive error',
            NetworkException::TYPE_SEND_ERROR => 'Data send error',
            default => 'Unknown network error',
        };
    }

    /**
     * Create appropriate exception based on error type
     * 
     * @param string $url Request URL
     * @param string $errorMessage Error message
     * @param int $httpCode HTTP response code
     * @param int $curlErrorCode cURL error code
     * @return HttpClientException
     */
    protected function createException(
        string $url,
        string $errorMessage,
        int $httpCode = 0,
        int $curlErrorCode = 0
    ): HttpClientException {
        // Timeout errors
        if ($this->isTimeoutError($curlErrorCode)) {
            $isConnectionTimeout = $this->isConnectionTimeoutError($curlErrorCode);
            $timeoutType = $isConnectionTimeout ? 'connection' : 'request';
            $message = $errorMessage ?: "Request to {$url} timed out ({$timeoutType} timeout)";
            
            return new TimeoutException(
                $message,
                0,
                null,
                $url,
                $httpCode,
                $curlErrorCode,
                $isConnectionTimeout
            );
        }

        // Network errors
        if ($this->isNetworkError($curlErrorCode)) {
            $errorType = $this->getNetworkErrorType($curlErrorCode);
            $typeDescription = $this->getNetworkErrorTypeDescription($errorType);
            $message = $errorMessage ?: "Network error occurred while requesting {$url} ({$typeDescription})";
            
            return new NetworkException(
                $message,
                0,
                null,
                $url,
                $httpCode,
                $curlErrorCode,
                $errorType
            );
        }

        // Note: HTTP error codes (4xx, 5xx) are valid responses, not exceptions
        // They should be handled by checking the HTTP code in the response

        // Generic error
        $message = $errorMessage ?: "Error occurred while requesting {$url}";
        
        return new HttpClientException(
            $message,
            0,
            null,
            $url,
            $httpCode,
            $curlErrorCode
        );
    }

    /**
     * Get cURL error code from a cURL handle
     * 
     * @param \CurlHandle $ch
     * @return int cURL error code (0 if no error)
     */
    protected function getCurlErrorCode(\CurlHandle $ch): int
    {
        return curl_errno($ch);
    }

    /**
     * Add an error/exception to the errors array
     * 
     * @param HttpClientException $exception
     * @return self
     */
    protected function addError(HttpClientException $exception): self
    {
        $this->errors[] = $exception;
        return $this;
    }

    /**
     * Clear all stored errors
     * 
     * @return self
     */
    public function clearErrors(): self
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
