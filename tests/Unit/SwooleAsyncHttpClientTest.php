<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\SwooleAsyncHttpClient;

class SwooleAsyncHttpClientTest extends TestCase
{
    public function testConstructor(): void
    {
        $client = new SwooleAsyncHttpClient();
        
        $this->assertInstanceOf(SwooleAsyncHttpClient::class, $client);
        $this->assertInstanceOf(\Gemvc\Http\Client\AsyncHttpClient::class, $client);
    }
    
    public function testInheritsAsyncHttpClientMethods(): void
    {
        $client = new SwooleAsyncHttpClient();
        
        // Should have all methods from AsyncHttpClient
        $this->assertTrue(method_exists($client, 'addGet'));
        $this->assertTrue(method_exists($client, 'addPost'));
        $this->assertTrue(method_exists($client, 'executeAll'));
        $this->assertTrue(method_exists($client, 'fireAndForget'));
    }
    
    public function testFireAndForget(): void
    {
        $client = new SwooleAsyncHttpClient();
        $client->addGet('req1', 'https://httpbin.org/get');
        
        $result = $client->fireAndForget();
        
        $this->assertIsBool($result);
    }
}
