<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\SwooleSyncHttpClient;

class SwooleSyncHttpClientTest extends TestCase
{
    public function testConstructor(): void
    {
        $client = new SwooleSyncHttpClient();
        
        $this->assertInstanceOf(SwooleSyncHttpClient::class, $client);
        $this->assertInstanceOf(\Gemvc\Http\Client\SyncHttpClient::class, $client);
    }
    
    public function testInheritsSyncHttpClientMethods(): void
    {
        $client = new SwooleSyncHttpClient();
        
        // Should have all methods from SyncHttpClient
        $this->assertTrue(method_exists($client, 'get'));
        $this->assertTrue(method_exists($client, 'post'));
        $this->assertTrue(method_exists($client, 'put'));
        $this->assertTrue(method_exists($client, 'setTimeouts'));
        $this->assertTrue(method_exists($client, 'setSsl'));
    }
}
