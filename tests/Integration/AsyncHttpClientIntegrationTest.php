<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\AsyncHttpClient;

class AsyncHttpClientIntegrationTest extends TestCase
{
    public function testExecuteAllWithMultipleRequests(): void
    {
        $async = new AsyncHttpClient();
        $async->setTimeouts(5, 10)
              ->setMaxConcurrency(3);
        
        $async->addGet('req1', 'https://httpbin.org/get', ['id' => 1]);
        $async->addGet('req2', 'https://httpbin.org/get', ['id' => 2]);
        $async->addGet('req3', 'https://httpbin.org/get', ['id' => 3]);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertArrayHasKey('req1', $results);
        $this->assertArrayHasKey('req2', $results);
        $this->assertArrayHasKey('req3', $results);
    }
    
    public function testExecuteAllWithPostRequest(): void
    {
        $async = new AsyncHttpClient();
        $async->setTimeouts(5, 10);
        $async->addPost('test', 'https://httpbin.org/post', ['name' => 'test']);
        
        $results = $async->executeAll();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('test', $results);
    }
}
