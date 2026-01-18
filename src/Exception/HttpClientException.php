<?php

namespace Gemvc\Http\Client\Exception;

/**
 * Base exception for HTTP client errors
 */
class HttpClientException extends \Exception
{
    /**
     * The URL that caused the error
     */
    protected ?string $url = null;

    /**
     * HTTP response code (0 if not available)
     */
    protected int $httpCode = 0;

    /**
     * cURL error code (0 if not a cURL error)
     */
    protected int $curlErrorCode = 0;

    /**
     * Create exception with context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $url = null,
        int $httpCode = 0,
        int $curlErrorCode = 0
    ) {
        parent::__construct($message, $code, $previous);
        $this->url = $url;
        $this->httpCode = $httpCode;
        $this->curlErrorCode = $curlErrorCode;
    }

    /**
     * Get the URL that caused the error
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the HTTP response code
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Get the cURL error code
     */
    public function getCurlErrorCode(): int
    {
        return $this->curlErrorCode;
    }
}
