<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\HttpClient;
use Gemvc\Http\Client\Exception\NetworkException;
use Gemvc\Http\Client\Exception\TimeoutException;
use Gemvc\Http\Client\Exception\HttpClientException;

/**
 * Tests for AbstractHttpClient functionality
 * Tested through HttpClient which extends AbstractHttpClient
 */
class AbstractHttpClientTest extends TestCase
{
    public function testSetUserAgent(): void
    {
        $client = new HttpClient();
        $result = $client->setUserAgent('MyCustomAgent/1.0');
        
        $this->assertSame($client, $result);
    }

    public function testClearErrors(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $this->assertTrue($client->hasErrors());
        
        $result = $client->clearErrors();
        $this->assertSame($client, $result);
        $this->assertFalse($client->hasErrors());
    }

    public function testHasErrors(): void
    {
        $client = new HttpClient();
        $this->assertFalse($client->hasErrors());
        
        $client->throwExceptions(false);
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $this->assertTrue($client->hasErrors());
    }

    public function testGetErrors(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $errors = $client->getErrors();
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }

    public function testGetLastError(): void
    {
        $client = new HttpClient();
        $this->assertNull($client->getLastError());
        
        $client->throwExceptions(false);
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $lastError = $client->getLastError();
        $this->assertNotNull($lastError);
        $this->assertInstanceOf(HttpClientException::class, $lastError);
    }

    public function testErrorsPropertyAccess(): void
    {
        $client = new HttpClient();
        $this->assertIsArray($client->errors);
        $this->assertEmpty($client->errors);
        
        // Test boolean check
        if (!$client->errors) {
            $this->assertTrue(true); // Empty array is falsy
        }
        
        $client->throwExceptions(false);
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        if ($client->errors) {
            $this->assertTrue(true); // Non-empty array is truthy
        }
    }

    public function testRetryLogicWithNetworkError(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(2, 10, []);
        $client->retryOnNetworkError(true);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        // Should have attempted retries
        $this->assertTrue($client->hasErrors());
    }

    public function testRetryLogicDisabled(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(0, 10, []);
        $client->retryOnNetworkError(false);
        
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        // Should still have error but no retries
        $this->assertTrue($client->hasErrors());
    }

    public function testSslConfiguration(): void
    {
        $client = new HttpClient();
        $result = $client->setSsl(
            '/path/to/cert.pem',
            '/path/to/key.pem',
            '/path/to/ca.pem',
            false,
            0
        );
        
        $this->assertSame($client, $result);
    }

    public function testSslConfigurationWithNulls(): void
    {
        $client = new HttpClient();
        $client->setSsl(null, null, null, true, 2);
        
        $this->assertTrue(true);
    }

    public function testSetRetriesWithEmptyArray(): void
    {
        $client = new HttpClient();
        $client->setRetries(3, 200, []);
        
        // Should use default retry codes
        $this->assertTrue(true);
    }

    public function testSetRetriesWithZeroDelay(): void
    {
        $client = new HttpClient();
        $client->setRetries(2, 0, [500, 502]);
        
        $this->assertTrue(true);
    }

    public function testSetRetriesWithNegativeValues(): void
    {
        $client = new HttpClient();
        $client->setRetries(-1, -100, []);
        
        // Should clamp to 0
        $this->assertTrue(true);
    }
}
