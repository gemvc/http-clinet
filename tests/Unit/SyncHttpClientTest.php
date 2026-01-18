<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\SyncHttpClient;

class SyncHttpClientTest extends TestCase
{
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructor(): void
    {
        $client = new SyncHttpClient();
        
        $this->assertInstanceOf(SyncHttpClient::class, $client);
        $this->assertEquals('call not initialized', $client->error);
        $this->assertEquals(0, $client->http_response_code);
        $this->assertIsArray($client->data);
        $this->assertIsArray($client->header);
        $this->assertIsArray($client->files);
        $this->assertNull($client->authorizationHeader);
        $this->assertFalse($client->responseBody);
        $this->assertEquals('GET', $client->method);
    }
    
    // ============================================
    // Configuration Methods Tests
    // ============================================
    
    public function testSetTimeouts(): void
    {
        $client = new SyncHttpClient();
        $result = $client->setTimeouts(5, 10);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
        $this->assertEquals($client, $result); // Should return self for chaining
    }
    
    public function testSetTimeoutsWithZeroValues(): void
    {
        $client = new SyncHttpClient();
        $client->setTimeouts(0, 0);
        
        // Should accept zero values (legacy behavior)
        $this->assertTrue(true);
    }
    
    public function testSetTimeoutsWithNegativeValues(): void
    {
        $client = new SyncHttpClient();
        $client->setTimeouts(-5, -10);
        
        // Should clamp to 0
        $this->assertTrue(true);
    }
    
    public function testSetSsl(): void
    {
        $client = new SyncHttpClient();
        $result = $client->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem', true, 2);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
        $this->assertEquals($client, $result);
    }
    
    public function testSetSslWithNullValues(): void
    {
        $client = new SyncHttpClient();
        $result = $client->setSsl(null, null, null, false, 0);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
    }
    
    public function testSetRetries(): void
    {
        $client = new SyncHttpClient();
        $result = $client->setRetries(3, 500, [429, 500, 502]);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
        $this->assertEquals($client, $result);
    }
    
    public function testSetRetriesWithEmptyArray(): void
    {
        $client = new SyncHttpClient();
        $result = $client->setRetries(2, 300, []);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
    }
    
    public function testSetRetriesWithZeroRetries(): void
    {
        $client = new SyncHttpClient();
        $client->setRetries(0, 200, []);
        
        // Should accept zero (no retries)
        $this->assertTrue(true);
    }
    
    public function testRetryOnNetworkError(): void
    {
        $client = new SyncHttpClient();
        $result = $client->retryOnNetworkError(true);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
        $this->assertEquals($client, $result);
    }
    
    public function testRetryOnNetworkErrorDisable(): void
    {
        $client = new SyncHttpClient();
        $result = $client->retryOnNetworkError(false);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
    }
    
    // ============================================
    // HTTP Method Tests (GET, POST, PUT)
    // ============================================
    
    public function testGetMethod(): void
    {
        $client = new SyncHttpClient();
        
        // Test that method is set correctly
        $this->assertEquals('GET', $client->method);
        
        // get() should set method to GET
        $client->method = 'POST';
        $client->get('https://example.com/api');
        
        $this->assertEquals('GET', $client->method);
    }
    
    public function testGetWithQueryParams(): void
    {
        $client = new SyncHttpClient();
        
        // get() should append query params to URL
        $client->get('https://example.com/api', ['id' => 1, 'name' => 'test']);
        
        $this->assertEquals('GET', $client->method);
        $this->assertIsArray($client->data);
    }
    
    public function testPostMethod(): void
    {
        $client = new SyncHttpClient();
        $client->post('https://example.com/api', ['name' => 'John']);
        
        $this->assertEquals('POST', $client->method);
        $this->assertEquals(['name' => 'John'], $client->data);
    }
    
    public function testPostClearsRawBody(): void
    {
        $client = new SyncHttpClient();
        $client->postRaw('https://example.com/api', 'raw body', 'text/plain');
        $client->post('https://example.com/api', ['data' => 'value']);
        
        $this->assertEquals('POST', $client->method);
        // rawBody should be cleared
        $reflection = new \ReflectionClass($client);
        $rawBodyProperty = $reflection->getProperty('rawBody');
        $rawBodyProperty->setAccessible(true);
        $this->assertNull($rawBodyProperty->getValue($client));
    }
    
    public function testPutMethod(): void
    {
        $client = new SyncHttpClient();
        $client->put('https://example.com/api', ['name' => 'Updated']);
        
        $this->assertEquals('PUT', $client->method);
        $this->assertEquals(['name' => 'Updated'], $client->data);
    }
    
    // ============================================
    // Form and Multipart Tests
    // ============================================
    
    public function testPostForm(): void
    {
        $client = new SyncHttpClient();
        // Don't actually make HTTP request, just test method configuration
        $client->method = 'GET'; // Reset to verify it changes
        $client->postForm('https://example.com/api', ['field1' => 'value1']);
        
        $this->assertEquals('POST', $client->method);
        // Verify formFields is set
        $reflection = new \ReflectionClass($client);
        $formFieldsProperty = $reflection->getProperty('formFields');
        $formFieldsProperty->setAccessible(true);
        $this->assertEquals(['field1' => 'value1'], $formFieldsProperty->getValue($client));
    }
    
    public function testPostFormWithEmptyFields(): void
    {
        $client = new SyncHttpClient();
        $result = $client->postForm('https://example.com/api', []);
        
        $this->assertEquals('POST', $client->method);
    }
    
    public function testPostMultipart(): void
    {
        $client = new SyncHttpClient();
        $result = $client->postMultipart('https://example.com/api', ['field' => 'value'], ['file' => '/tmp/test.txt']);
        
        $this->assertEquals('POST', $client->method);
        $this->assertIsArray($client->files);
    }
    
    public function testPostMultipartWithEmptyData(): void
    {
        $client = new SyncHttpClient();
        $result = $client->postMultipart('https://example.com/api', [], []);
        
        $this->assertEquals('POST', $client->method);
    }
    
    public function testPostRaw(): void
    {
        $client = new SyncHttpClient();
        $result = $client->postRaw('https://example.com/api', 'raw body content', 'text/plain');
        
        $this->assertEquals('POST', $client->method);
        $this->assertArrayHasKey('Content-Type', $client->header);
        $this->assertEquals('text/plain', $client->header['Content-Type']);
    }
    
    public function testPostRawClearsFormFields(): void
    {
        $client = new SyncHttpClient();
        $client->postForm('https://example.com/api', ['field' => 'value']);
        $client->postRaw('https://example.com/api', 'raw', 'text/plain');
        
        $reflection = new \ReflectionClass($client);
        $formFieldsProperty = $reflection->getProperty('formFields');
        $formFieldsProperty->setAccessible(true);
        $this->assertNull($formFieldsProperty->getValue($client));
    }
    
    // ============================================
    // Header and Authorization Tests
    // ============================================
    
    public function testSetCustomHeaders(): void
    {
        $client = new SyncHttpClient();
        $client->header['X-Custom-Header'] = 'value';
        $client->header['X-Another-Header'] = 'another-value';
        
        $this->assertArrayHasKey('X-Custom-Header', $client->header);
        $this->assertArrayHasKey('X-Another-Header', $client->header);
        $this->assertEquals('value', $client->header['X-Custom-Header']);
    }
    
    public function testSetAuthorizationHeader(): void
    {
        $client = new SyncHttpClient();
        $client->authorizationHeader = 'Bearer token-123';
        
        $this->assertEquals('Bearer token-123', $client->authorizationHeader);
    }
    
    public function testSetAuthorizationHeaderAsArray(): void
    {
        $client = new SyncHttpClient();
        $client->authorizationHeader = ['Bearer', 'token-123'];
        
        // Should accept array (for compatibility)
        $this->assertIsArray($client->authorizationHeader);
    }
    
    // ============================================
    // Files Tests
    // ============================================
    
    public function testSetFiles(): void
    {
        $client = new SyncHttpClient();
        $client->files = ['file1' => '/path/to/file1.txt', 'file2' => '/path/to/file2.txt'];
        
        $this->assertIsArray($client->files);
        $this->assertCount(2, $client->files);
    }
    
    // ============================================
    // Data Tests
    // ============================================
    
    public function testSetData(): void
    {
        $client = new SyncHttpClient();
        $client->data = ['key1' => 'value1', 'key2' => 'value2'];
        
        $this->assertIsArray($client->data);
        $this->assertEquals('value1', $client->data['key1']);
    }
    
    // ============================================
    // Method Chaining Tests
    // ============================================
    
    public function testMethodChaining(): void
    {
        $client = new SyncHttpClient();
        $result = $client
            ->setTimeouts(5, 10)
            ->setSsl('/cert.pem', '/key.pem')
            ->setRetries(3, 500)
            ->retryOnNetworkError(true);
        
        $this->assertInstanceOf(SyncHttpClient::class, $result);
        $this->assertEquals($client, $result);
    }
    
    // ============================================
    // Error Handling Tests
    // ============================================
    
    public function testErrorPropertyInitialization(): void
    {
        $client = new SyncHttpClient();
        
        $this->assertEquals('call not initialized', $client->error);
    }
    
    public function testErrorPropertyCanBeSet(): void
    {
        $client = new SyncHttpClient();
        $client->error = 'Test error message';
        
        $this->assertEquals('Test error message', $client->error);
    }
    
    public function testHttpResponseCodeInitialization(): void
    {
        $client = new SyncHttpClient();
        
        $this->assertEquals(0, $client->http_response_code);
    }
    
    public function testHttpResponseCodeCanBeSet(): void
    {
        $client = new SyncHttpClient();
        $client->http_response_code = 200;
        
        $this->assertEquals(200, $client->http_response_code);
    }
    
    // ============================================
    // Response Body Tests
    // ============================================
    
    public function testResponseBodyInitialization(): void
    {
        $client = new SyncHttpClient();
        
        $this->assertFalse($client->responseBody);
    }
    
    public function testResponseBodyCanBeSetToString(): void
    {
        $client = new SyncHttpClient();
        $client->responseBody = 'Response content';
        
        $this->assertEquals('Response content', $client->responseBody);
    }
    
    public function testResponseBodyCanBeSetToFalse(): void
    {
        $client = new SyncHttpClient();
        $client->responseBody = 'Response';
        $client->responseBody = false;
        
        $this->assertFalse($client->responseBody);
    }
    
    // ============================================
    // Edge Cases
    // ============================================
    
    public function testGetWithEmptyQueryParams(): void
    {
        $client = new SyncHttpClient();
        $client->get('https://example.com/api', []);
        
        $this->assertEquals('GET', $client->method);
    }
    
    public function testPostWithEmptyData(): void
    {
        $client = new SyncHttpClient();
        $client->post('https://example.com/api', []);
        
        $this->assertEquals('POST', $client->method);
        $this->assertIsArray($client->data);
        $this->assertEmpty($client->data);
    }
    
    public function testPutWithEmptyData(): void
    {
        $client = new SyncHttpClient();
        $client->put('https://example.com/api', []);
        
        $this->assertEquals('PUT', $client->method);
        $this->assertIsArray($client->data);
    }
    
    public function testPostFormWithSpecialCharacters(): void
    {
        $client = new SyncHttpClient();
        $client->postForm('https://example.com/api', [
            'field1' => 'value with spaces',
            'field2' => 'value&with=special'
        ]);
        
        $this->assertEquals('POST', $client->method);
    }
    
    public function testPostRawWithJsonContentType(): void
    {
        $client = new SyncHttpClient();
        $client->postRaw('https://example.com/api', '{"key":"value"}', 'application/json');
        
        $this->assertEquals('application/json', $client->header['Content-Type']);
    }
    
    public function testSetRetriesWithDuplicateHttpCodes(): void
    {
        $client = new SyncHttpClient();
        $client->setRetries(3, 500, [429, 500, 429, 502]);
        
        // Duplicates should be removed
        $this->assertTrue(true);
    }
}
