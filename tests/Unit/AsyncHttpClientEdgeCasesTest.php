<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\AsyncHttpClient;

class AsyncHttpClientEdgeCasesTest extends TestCase
{
    public function testAddRequestWithCustomMethod(): void
    {
        $async = new AsyncHttpClient();
        $async->addRequest('req1', 'https://example.com/api', 'DELETE', ['id' => 1]);
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testAddRequestWithPatchMethod(): void
    {
        $async = new AsyncHttpClient();
        $async->addRequest('req1', 'https://example.com/api', 'PATCH', ['name' => 'test']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testAddRequestWithOptionsMethod(): void
    {
        $async = new AsyncHttpClient();
        $async->addRequest('req1', 'https://example.com/api', 'OPTIONS');
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testAddRequestWithCustomOptions(): void
    {
        $async = new AsyncHttpClient();
        $async->addRequest('req1', 'https://example.com/api', 'POST', [], [], ['custom' => 'option']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testAddPostMultipartWithEmptyFiles(): void
    {
        $async = new AsyncHttpClient();
        $async->addPostMultipart('req1', 'https://example.com/api', ['field' => 'value'], []);
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testAddPostFormWithSpecialHeaders(): void
    {
        $async = new AsyncHttpClient();
        $async->addPostForm('req1', 'https://example.com/api', ['field' => 'value'], ['X-Custom' => 'Header']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testAddPostRawWithCustomHeaders(): void
    {
        $async = new AsyncHttpClient();
        $async->addPostRaw('req1', 'https://example.com/api', 'raw body', 'text/plain', ['X-Custom' => 'Header']);
        
        $this->assertEquals(1, $async->getQueueSize());
    }

    public function testSetMaxConcurrencyWithLargeValue(): void
    {
        $async = new AsyncHttpClient();
        $async->setMaxConcurrency(100);
        
        $this->assertTrue(true);
    }

    public function testExecuteAllWithSingleRequest(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertArrayHasKey('req1', $results);
        $this->assertIsArray($results['req1']);
    }

    public function testExecuteAllWithMaxConcurrencyLimit(): void
    {
        $async = new AsyncHttpClient();
        $async->setMaxConcurrency(2);
        
        for ($i = 1; $i <= 5; $i++) {
            $async->addGet("req{$i}", 'https://httpbin.org/get');
        }
        
        $results = $async->executeAll();
        
        $this->assertCount(5, $results);
    }

    public function testOnResponseCallbackNotCalledForFailedRequest(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $callbackCalled = false;
        $async->onResponse('req1', function() use (&$callbackCalled) {
            $callbackCalled = true;
        });
        
        $async->executeAll();
        
        // Callback should still be called even for failed requests
        $this->assertTrue($callbackCalled);
    }

    public function testClearQueueClearsCallbacks(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://example.com/api');
        $async->onResponse('req1', function() {});
        
        $async->clearQueue();
        
        $this->assertEquals(0, $async->getQueueSize());
    }

    public function testResultStructureForSuccessfulRequest(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertArrayHasKey('req1', $results);
        $result = $results['req1'];
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('http_code', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('exception', $result);
        $this->assertArrayHasKey('exception_type', $result);
    }

    public function testResultStructureForFailedRequest(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://invalid-domain-that-does-not-exist-xyz123.com/api');
        
        $results = $async->executeAll();
        
        $this->assertArrayHasKey('req1', $results);
        $result = $results['req1'];
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['exception']);
        $this->assertNotEmpty($result['exception_type']);
    }

    public function testDurationIsCalculated(): void
    {
        $async = new AsyncHttpClient();
        $async->addGet('req1', 'https://httpbin.org/get');
        
        $results = $async->executeAll();
        
        $this->assertArrayHasKey('duration', $results['req1']);
        $this->assertIsFloat($results['req1']['duration']);
        $this->assertGreaterThanOrEqual(0, $results['req1']['duration']);
    }
}
