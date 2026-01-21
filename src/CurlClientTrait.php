<?php

namespace Gemvc\Http\Client;

use CurlHandle;
use Gemvc\Http\Client\Exception\HttpClientException;
use Gemvc\Http\Client\Exception\NetworkException;
use Gemvc\Http\Client\Exception\TimeoutException;

/**
 * Trait for cURL-based HTTP clients
 * 
 * Provides cURL-specific helper methods for configuration,
 * error handling, and exception creation.
 */
trait CurlClientTrait
{
    /**
     * Apply common cURL options to a handle
     * 
     * @param CurlHandle $ch
     */
    protected function applyCommonCurlOptions(CurlHandle $ch): void
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
     * @param CurlHandle $ch
     * @return int cURL error code (0 if no error)
     */
    protected function getCurlErrorCode(CurlHandle $ch): int
    {
        return curl_errno($ch);
    }
}
