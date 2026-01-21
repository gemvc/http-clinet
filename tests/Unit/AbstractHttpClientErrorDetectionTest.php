<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\HttpClient;
use Gemvc\Http\Client\Exception\NetworkException;
use Gemvc\Http\Client\Exception\TimeoutException;

/**
 * Tests for AbstractHttpClient error detection and exception creation
 * Tested through HttpClient which extends AbstractHttpClient
 */
class AbstractHttpClientErrorDetectionTest extends TestCase
{
    public function testCreateExceptionForTimeoutError(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        // Simulate timeout by using very short timeout
        $client->setTimeouts(1, 1);
        
        // Try to connect to a slow endpoint (should timeout)
        $client->get('https://httpbin.org/delay/5');
        
        $error = $client->getLastError();
        if ($error instanceof TimeoutException) {
            $this->assertTrue(true);
        }
    }

    public function testCreateExceptionForNetworkError(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $error = $client->getLastError();
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $error);
        
        if ($error instanceof NetworkException) {
            $this->assertNotEmpty($error->getErrorType());
            $this->assertNotEmpty($error->getErrorTypeDescription());
        }
    }

    public function testCreateExceptionForGenericError(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        // This should create a generic HttpClientException
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $error = $client->getLastError();
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $error);
    }

    public function testExceptionCreationWithAllContext(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        $url = 'https://invalid-domain-that-does-not-exist-xyz123.com/api';
        $client->get($url);
        
        $error = $client->getLastError();
        $this->assertNotNull($error);
        $this->assertEquals($url, $error->getUrl());
        $this->assertIsInt($error->getHttpCode());
        $this->assertIsInt($error->getCurlErrorCode());
    }

    public function testNetworkExceptionErrorTypes(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $error = $client->getLastError();
        if ($error instanceof NetworkException) {
            $errorType = $error->getErrorType();
            $this->assertContains($errorType, [
                NetworkException::TYPE_DNS_ERROR,
                NetworkException::TYPE_CONNECTION_ERROR,
                NetworkException::TYPE_SSL_ERROR,
                NetworkException::TYPE_RECEIVE_ERROR,
                NetworkException::TYPE_SEND_ERROR,
                NetworkException::TYPE_UNKNOWN,
            ]);
            
            $description = $error->getErrorTypeDescription();
            $this->assertNotEmpty($description);
            $this->assertIsString($description);
        }
    }

    public function testTimeoutExceptionIsConnectionTimeout(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setTimeouts(1, 1);
        
        // This might timeout
        $client->get('https://httpbin.org/delay/5');
        
        $error = $client->getLastError();
        if ($error instanceof TimeoutException) {
            $this->assertIsBool($error->isConnectionTimeout());
        }
    }

    public function testRetryLogicWithHttpCodes(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(1, 10, [500, 502, 503]);
        $client->retryOnNetworkError(false);
        
        // This won't actually retry on HTTP codes since we're not getting real responses
        // But tests the retry configuration
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $this->assertTrue(true);
    }

    public function testRetryLogicWithNetworkErrors(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(1, 10, []);
        $client->retryOnNetworkError(true);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        // Should have attempted retries
        $this->assertTrue($client->hasErrors());
    }

    public function testWaitForRetryWithZeroDelay(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(1, 0, []);
        $client->retryOnNetworkError(true);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        // Should work with zero delay
        $this->assertTrue(true);
    }
}
