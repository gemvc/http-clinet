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

    public function testFireAndForgetWithEmptyQueue(): void
    {
        $client = new SwooleAsyncHttpClient();
        
        $result = $client->fireAndForget();
        
        $this->assertFalse($result);
    }

    public function testFireAndForgetExecutesRequests(): void
    {
        $client = new SwooleAsyncHttpClient();
        $client->addGet('req1', 'https://httpbin.org/get');
        
        // Should execute without throwing
        $result = $client->fireAndForget();
        
        $this->assertIsBool($result);
    }

    public function testInheritsErrorHandling(): void
    {
        $client = new SwooleAsyncHttpClient();
        
        $this->assertTrue(method_exists($client, 'clearErrors'));
        $this->assertTrue(method_exists($client, 'hasErrors'));
        $this->assertTrue(method_exists($client, 'getErrors'));
        $this->assertTrue(method_exists($client, 'getLastError'));
    }

    public function testErrorsPropertyAccess(): void
    {
        $client = new SwooleAsyncHttpClient();
        
        $this->assertIsArray($client->errors);
        $this->assertEmpty($client->errors);
    }
}
