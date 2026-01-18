<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\SyncHttpClient;
use Gemvc\Http\Client\AsyncHttpClient;
use Gemvc\Http\Client\Exception\HttpClientException;
use Gemvc\Http\Client\Exception\NetworkException;
use Gemvc\Http\Client\Exception\TimeoutException;

class ErrorHandlingIntegrationTest extends TestCase
{
    public function testSyncClientStoresNetworkException(): void
    {
        $client = new SyncHttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $this->assertTrue($client->hasErrors());
        $error = $client->getLastError();
        $this->assertInstanceOf(HttpClientException::class, $error);
        
        if ($error instanceof NetworkException) {
            $this->assertNotEmpty($error->getErrorType());
        }
    }

    public function testSyncClientExceptionContainsFullContext(): void
    {
        $client = new SyncHttpClient();
        $client->throwExceptions(false);
        
        $url = 'https://invalid-domain-that-does-not-exist-xyz123.com/api';
        $client->get($url);
        
        $error = $client->getLastError();
        $this->assertNotNull($error);
        $this->assertEquals($url, $error->getUrl());
        $this->assertIsInt($error->getHttpCode());
        $this->assertIsInt($error->getCurlErrorCode());
        $this->assertNotEmpty($error->getMessage());
    }

    public function testAsyncClientStoresExceptionsInResultsAndErrors(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $results = $async->executeAll();
        
        // Should be in results
        $this->assertNotNull($results['req1']['exception']);
        
        // Should also be in errors array
        $this->assertTrue($async->hasErrors());
        $this->assertSame($results['req1']['exception'], $async->getLastError());
    }

    public function testMultipleErrorsInAsyncClient(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-1-xyz123.com/api');
        $async->addGet('req2', 'https://invalid-domain-2-xyz123.com/api');
        $async->addGet('req3', 'https://httpbin.org/get'); // This should succeed
        
        $results = $async->executeAll();
        
        // At least 2 errors from failed requests
        $this->assertGreaterThanOrEqual(2, count($async->errors));
        // All requests should be in results
        $this->assertArrayHasKey('req1', $results);
        $this->assertArrayHasKey('req2', $results);
        $this->assertArrayHasKey('req3', $results);
        // req1 and req2 should fail
        $this->assertFalse($results['req1']['success']);
        $this->assertFalse($results['req2']['success']);
        // req3 should succeed if network is available (but may fail due to network issues)
        // Just verify it's in the results
        $this->assertIsBool($results['req3']['success']);
    }

    public function testErrorClearingBetweenRequests(): void
    {
        $client = new SyncHttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $this->assertTrue($client->hasErrors());
        
        $client->clearErrors();
        $this->assertFalse($client->hasErrors());
        
        // New request should work
        $client->get('https://httpbin.org/get');
        // May or may not have errors depending on network
    }

    public function testRetryLogicStoresMultipleErrors(): void
    {
        $client = new SyncHttpClient();
        $client->throwExceptions(false);
        $client->setRetries(2, 10, []);
        $client->retryOnNetworkError(true);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        // Should have errors from retry attempts
        $this->assertTrue($client->hasErrors());
    }

    public function testExceptionTypesAreCorrectlyIdentified(): void
    {
        $client = new SyncHttpClient();
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
        }
    }

    public function testNetworkExceptionHelperMethods(): void
    {
        $client = new SyncHttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $error = $client->getLastError();
        if ($error instanceof NetworkException) {
            // Test helper methods
            $error->isDnsError();
            $error->isConnectionError();
            $error->isSslError();
            $error->isReceiveError();
            $error->isSendError();
            $error->getErrorTypeDescription();
            
            $this->assertTrue(true);
        }
    }

    public function testTimeoutExceptionHelperMethod(): void
    {
        $client = new SyncHttpClient();
        $client->throwExceptions(false);
        $client->setTimeouts(1, 1);
        
        // This might timeout
        $client->get('https://httpbin.org/delay/5');
        
        $error = $client->getLastError();
        if ($error instanceof TimeoutException) {
            $this->assertIsBool($error->isConnectionTimeout());
        }
    }
}
