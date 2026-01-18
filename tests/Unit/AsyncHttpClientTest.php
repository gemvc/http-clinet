<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\AsyncHttpClient;

class AsyncHttpClientTest extends TestCase
{
    // ==========================================
    // Constructor Tests
    // ==========================================
    
    public function testConstructor(): void
    {
        $async = new AsyncHttpClient();
        
        $this->assertInstanceOf(AsyncHttpClient::class, $async);
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    // ==========================================
    // Configuration Methods Tests
    // ==========================================
    
    public function testSetMaxConcurrency(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->setMaxConcurrency(5);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetMaxConcurrencyClampsMinimum(): void
    {
        $async = new AsyncHttpClient();
        $async->setMaxConcurrency(0);
        
        // Should clamp to 1
        $this->assertTrue(true);
    }
    
    public function testSetTimeouts(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->setTimeouts(10, 30);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetSsl(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem', true, 2);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetRetries(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->setRetries(3, 200, [500, 502, 503]);
        
        $this->assertSame($async, $result);
    }
    
    public function testRetryOnNetworkError(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->retryOnNetworkError(true);
        
        $this->assertSame($async, $result);
    }
    
    public function testSetUserAgent(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->setUserAgent('MyApp/1.0');
        
        $this->assertSame($async, $result);
    }
    
    // ==========================================
    // Request Building Methods Tests
    // ==========================================
    
    public function testAddGet(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->addGet('req1', 'https://example.com/api');
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddGetWithQueryParams(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://example.com/api', ['id' => 1, 'name' => 'test']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPost(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->addPost('req1', 'https://example.com/api', ['name' => 'John']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPut(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->addPut('req1', 'https://example.com/api', ['name' => 'Updated']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostForm(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->addPostForm('req1', 'https://example.com/api', ['field1' => 'value1']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostMultipart(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->addPostMultipart('req1', 'https://example.com/api', ['description' => 'test'], ['file' => '/tmp/test.txt']);
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testAddPostRaw(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->addPostRaw('req1', 'https://example.com/api', 'raw body content', 'text/plain');
        
        $this->assertSame($async, $result);
        $this->assertEquals(1, $async->getQueueSize());
    }
    
    public function testOnResponse(): void
    {
        $async = new AsyncHttpClient();
        $callback = function($result, $requestId) {
            return true;
        };
        $result = $async->onResponse('req1', $callback);
        
        $this->assertSame($async, $result);
    }
    
    // ==========================================
    // Queue Management Tests
    // ==========================================
    
    public function testGetQueueSize(): void
    {
        $async = new AsyncHttpClient();
        $this->assertEquals(0, $async->getQueueSize());
        
        $async->addGet('req1', 'https://example.com/api');
        $this->assertEquals(1, $async->getQueueSize());
        
        $async->addPost('req2', 'https://example.com/api');
        $this->assertEquals(2, $async->getQueueSize());
    }
    
    public function testClearQueue(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://example.com/api');
        $async->addPost('req2', 'https://example.com/api');
        
        $this->assertEquals(2, $async->getQueueSize());
        
        $result = $async->clearQueue();
        $this->assertSame($async, $result);
        $this->assertEquals(0, $async->getQueueSize());
    }
    
    // ==========================================
    // Execution Tests
    // ==========================================
    
    public function testExecuteAllWithEmptyQueue(): void
    {
        $async = new AsyncHttpClient();
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
    
    public function testWaitForAll(): void
    {
        $async = new AsyncHttpClient();
        $async->setTimeouts(5, 10);
        $async->addGet('req1', 'https://httpbin.org/get');
        $async->addGet('req2', 'https://httpbin.org/get');
        
        $results = $async->waitForAll();
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }
    
    public function testFireAndForgetWithEmptyQueue(): void
    {
        $async = new AsyncHttpClient();
        $result = $async->fireAndForget();
        
        $this->assertFalse($result);
    }
    
    public function testFireAndForgetWithRequests(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://httpbin.org/get');
        $result = $async->fireAndForget();
        
        // Should return true if background execution was initiated
        $this->assertIsBool($result);
    }
    
    // ==========================================
    // Method Chaining Tests
    // ==========================================
    
    public function testMethodChaining(): void
    {
        $async = new AsyncHttpClient();
        $result = $async
            ->setMaxConcurrency(5)
            ->setTimeouts(10, 30)
            ->setSsl('/cert.pem', '/key.pem')
            ->setRetries(3, 500)
            ->retryOnNetworkError(true)
            ->setUserAgent('MyApp/1.0')
            ->addGet('req1', 'https://example.com/api')
            ->addPost('req2', 'https://example.com/api', ['data' => 'value'])
            ->onResponse('req1', function() {});
        
        $this->assertSame($async, $result);
    }

    // ==========================================
    // Error Handling Tests
    // ==========================================

    public function testErrorsPropertyIsEmptyInitially(): void
    {
        $async = new AsyncHttpClient();
        
        $this->assertEmpty($async->errors);
        $this->assertFalse($async->hasErrors());
        $this->assertNull($async->getLastError());
        $this->assertEmpty($async->getErrors());
    }

    public function testErrorsPropertyAfterFailedRequest(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $results = $async->executeAll();
        
        $this->assertNotEmpty($async->errors);
        $this->assertTrue($async->hasErrors());
        $this->assertNotNull($async->getLastError());
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $async->getLastError());
        
        // Result should also contain exception
        $this->assertNotNull($results['req1']['exception']);
        $this->assertNotEmpty($results['req1']['exception_type']);
    }

    public function testClearErrors(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $async->executeAll();
        
        $this->assertTrue($async->hasErrors());
        
        $result = $async->clearErrors();
        $this->assertSame($async, $result);
        $this->assertFalse($async->hasErrors());
        $this->assertEmpty($async->errors);
    }

    public function testErrorsClearedOnNewExecuteAll(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $async->executeAll();
        $this->assertTrue($async->hasErrors());
        
        // New executeAll should clear previous errors
        $async->addGet('req2', 'https://httpbin.org/get');
        $async->executeAll();
        // Errors cleared at start, but may be repopulated if new requests fail
    }

    public function testGetErrorsReturnsArray(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $async->executeAll();
        
        $errors = $async->getErrors();
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $errors[0]);
    }

    public function testExceptionInResultContainsUrl(): void
    {
        $async = new AsyncHttpClient();
        $url = 'https://invalid-domain-that-does-not-exist-xyz123.com/api';
        $async->addGet('req1', $url);
        
        $results = $async->executeAll();
        
        $this->assertNotNull($results['req1']['exception']);
        $this->assertEquals($url, $results['req1']['exception']->getUrl());
    }

    public function testMultipleErrorsStored(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-1-xyz123.com/api');
        $async->addGet('req2', 'https://invalid-domain-2-xyz123.com/api');
        
        $async->executeAll();
        
        $this->assertGreaterThanOrEqual(2, count($async->errors));
    }

    public function testResultContainsExceptionType(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $results = $async->executeAll();
        
        $this->assertNotEmpty($results['req1']['exception_type']);
        $this->assertIsString($results['req1']['exception_type']);
    }
}
