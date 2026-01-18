<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\SyncHttpClient;

class SyncHttpClientIntegrationTest extends TestCase
{
    public function testGetRequest(): void
    {
        $client = new SyncHttpClient();
        $client->setTimeouts(5, 10);
        
        $response = $client->get('https://httpbin.org/get', ['test' => 'value']);
        
        $this->assertNotFalse($response);
        $this->assertIsString($response);
        $this->assertGreaterThan(0, $client->http_response_code);
    }
    
    public function testPostRequest(): void
    {
        $client = new SyncHttpClient();
        $client->setTimeouts(5, 10);
        
        $response = $client->post('https://httpbin.org/post', ['name' => 'test']);
        
        $this->assertNotFalse($response);
        $this->assertIsString($response);
    }
}
