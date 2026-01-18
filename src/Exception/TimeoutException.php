<?php

namespace Gemvc\Http\Client\Exception;

/**
 * Exception thrown when a request times out
 * 
 * This includes both connection timeouts and total request timeouts.
 */
class TimeoutException extends HttpClientException
{
    /**
     * Whether this is a connection timeout (true) or total timeout (false)
     */
    protected bool $isConnectionTimeout = false;

    /**
     * Create timeout exception with context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $url = null,
        int $httpCode = 0,
        int $curlErrorCode = 0,
        bool $isConnectionTimeout = false
    ) {
        parent::__construct($message, $code, $previous, $url, $httpCode, $curlErrorCode);
        $this->isConnectionTimeout = $isConnectionTimeout;
    }

    /**
     * Check if this is a connection timeout
     */
    public function isConnectionTimeout(): bool
    {
        return $this->isConnectionTimeout;
    }
}
