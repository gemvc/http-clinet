<?php

namespace Gemvc\Http\Client;

/**
 * Interface for HTTP client implementations
 * 
 * This interface defines common configuration methods shared by
 * both synchronous and asynchronous HTTP clients.
 */
interface IHttpClient
{
    /**
     * Configure connection and total timeouts (seconds)
     * 
     * @param int $connectTimeout Connection timeout in seconds
     * @param int $timeout Total request timeout in seconds
     * @return self
     */
    public function setTimeouts(int $connectTimeout, int $timeout): self;

    /**
     * Configure SSL client options
     * 
     * @param string|null $certPath SSL client certificate path
     * @param string|null $keyPath SSL client private key path
     * @param string|null $caPath CA certificate path
     * @param bool $verifyPeer Verify peer certificate (default: true)
     * @param int $verifyHost Verify host (0, 1, or 2, default: 2)
     * @return self
     */
    public function setSsl(?string $certPath, ?string $keyPath, ?string $caPath = null, bool $verifyPeer = true, int $verifyHost = 2): self;

    /**
     * Configure retry behavior
     * 
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $retryDelayMs Delay between retries in milliseconds
     * @param array<int> $retryOnHttpCodes HTTP status codes that trigger a retry
     * @return self
     */
    public function setRetries(int $maxRetries, int $retryDelayMs = 200, array $retryOnHttpCodes = []): self;

    /**
     * Enable/disable retry on network errors
     * 
     * @param bool $retry Whether to retry on network errors
     * @return self
     */
    public function retryOnNetworkError(bool $retry): self;
}
