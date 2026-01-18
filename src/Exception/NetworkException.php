<?php

namespace Gemvc\Http\Client\Exception;

/**
 * Exception thrown when a network error occurs
 * 
 * This includes connection failures, DNS errors, SSL errors, and other network-related issues.
 */
class NetworkException extends HttpClientException
{
    /**
     * Network error type constants
     */
    public const TYPE_DNS_ERROR = 'dns_error';
    public const TYPE_CONNECTION_ERROR = 'connection_error';
    public const TYPE_SSL_ERROR = 'ssl_error';
    public const TYPE_RECEIVE_ERROR = 'receive_error';
    public const TYPE_SEND_ERROR = 'send_error';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Type of network error
     */
    protected string $errorType = self::TYPE_UNKNOWN;

    /**
     * Create network exception with context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $url = null,
        int $httpCode = 0,
        int $curlErrorCode = 0,
        string $errorType = self::TYPE_UNKNOWN
    ) {
        parent::__construct($message, $code, $previous, $url, $httpCode, $curlErrorCode);
        $this->errorType = $errorType;
    }

    /**
     * Get the type of network error
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Check if this is a DNS resolution error
     */
    public function isDnsError(): bool
    {
        return $this->errorType === self::TYPE_DNS_ERROR;
    }

    /**
     * Check if this is a connection error
     */
    public function isConnectionError(): bool
    {
        return $this->errorType === self::TYPE_CONNECTION_ERROR;
    }

    /**
     * Check if this is an SSL/TLS error
     */
    public function isSslError(): bool
    {
        return $this->errorType === self::TYPE_SSL_ERROR;
    }

    /**
     * Check if this is a data receive error
     */
    public function isReceiveError(): bool
    {
        return $this->errorType === self::TYPE_RECEIVE_ERROR;
    }

    /**
     * Check if this is a data send error
     */
    public function isSendError(): bool
    {
        return $this->errorType === self::TYPE_SEND_ERROR;
    }

    /**
     * Get human-readable error type description
     */
    public function getErrorTypeDescription(): string
    {
        return match ($this->errorType) {
            self::TYPE_DNS_ERROR => 'DNS resolution failed',
            self::TYPE_CONNECTION_ERROR => 'Connection failed',
            self::TYPE_SSL_ERROR => 'SSL/TLS handshake failed',
            self::TYPE_RECEIVE_ERROR => 'Data receive error',
            self::TYPE_SEND_ERROR => 'Data send error',
            default => 'Unknown network error',
        };
    }
}
