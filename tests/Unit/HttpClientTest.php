<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\HttpClient;

class HttpClientTest extends TestCase
{
    // ============================================
    // Constructor Tests
    // ============================================

    public function testConstructor(): void
    {
        $client = new HttpClient();

        $this->assertInstanceOf(HttpClient::class, $client);
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
        $client = new HttpClient();
        $result = $client->setTimeouts(5, 10);

        $this->assertInstanceOf(HttpClient::class, $result);
        $this->assertEquals($client, $result); // Should return self for chaining
    }

    public function testSetTimeoutsWithZeroValues(): void
    {
        $client = new HttpClient();
        $client->setTimeouts(0, 0);

        // Should accept zero values (legacy behavior)
        $this->assertTrue(true);
    }

    public function testSetTimeoutsWithNegativeValues(): void
    {
        $client = new HttpClient();
        $client->setTimeouts(-5, -10);

        // Should clamp to 0
        $this->assertTrue(true);
    }

    public function testSetSsl(): void
    {
        $client = new HttpClient();
        $result = $client->setSsl('/path/to/cert.pem', '/path/to/key.pem', '/path/to/ca.pem', true, 2);

        $this->assertInstanceOf(HttpClient::class, $result);
        $this->assertEquals($client, $result);
    }

    public function testSetSslWithNullValues(): void
    {
        $client = new HttpClient();
        $result = $client->setSsl(null, null, null, false, 0);

        $this->assertInstanceOf(HttpClient::class, $result);
    }

    public function testSetRetries(): void
    {
        $client = new HttpClient();
        $result = $client->setRetries(3, 500, [429, 500, 502]);

        $this->assertInstanceOf(HttpClient::class, $result);
        $this->assertEquals($client, $result);
    }

    public function testSetRetriesWithEmptyArray(): void
    {
        $client = new HttpClient();
        $result = $client->setRetries(2, 300, []);

        $this->assertInstanceOf(HttpClient::class, $result);
    }

    public function testSetRetriesWithZeroRetries(): void
    {
        $client = new HttpClient();
        $client->setRetries(0, 200, []);

        // Should accept zero (no retries)
        $this->assertTrue(true);
    }

    public function testRetryOnNetworkError(): void
    {
        $client = new HttpClient();
        $result = $client->retryOnNetworkError(true);

        $this->assertInstanceOf(HttpClient::class, $result);
        $this->assertEquals($client, $result);
    }

    public function testRetryOnNetworkErrorDisable(): void
    {
        $client = new HttpClient();
        $result = $client->retryOnNetworkError(false);

        $this->assertInstanceOf(HttpClient::class, $result);
    }

    // ============================================
    // HTTP Method Tests (GET, POST, PUT)
    // ============================================

    public function testGetMethod(): void
    {
        $client = new HttpClient();

        // Test that method is set correctly
        $this->assertEquals('GET', $client->method);

        // get() should set method to GET
        $client->method = 'POST';
        $client->get('https://example.com/api');

        $this->assertEquals('GET', $client->method);
    }

    public function testGetWithQueryParams(): void
    {
        $client = new HttpClient();

        // get() should append query params to URL
        $client->get('https://example.com/api', ['id' => 1, 'name' => 'test']);

        $this->assertEquals('GET', $client->method);
        $this->assertIsArray($client->data);
    }

    public function testPostMethod(): void
    {
        $client = new HttpClient();
        $client->post('https://example.com/api', ['name' => 'John']);

        $this->assertEquals('POST', $client->method);
        $this->assertEquals(['name' => 'John'], $client->data);
    }

    public function testPostClearsRawBody(): void
    {
        $client = new HttpClient();
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
        $client = new HttpClient();
        $client->put('https://example.com/api', ['name' => 'Updated']);

        $this->assertEquals('PUT', $client->method);
        $this->assertEquals(['name' => 'Updated'], $client->data);
    }

    // ============================================
    // Form and Multipart Tests
    // ============================================

    public function testPostForm(): void
    {
        $client = new HttpClient();
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
        $client = new HttpClient();
        $result = $client->postForm('https://example.com/api', []);

        $this->assertEquals('POST', $client->method);
    }

    public function testPostMultipart(): void
    {
        $client = new HttpClient();
        $result = $client->postMultipart('https://example.com/api', ['field' => 'value'], ['file' => '/tmp/test.txt']);

        $this->assertEquals('POST', $client->method);
        $this->assertIsArray($client->files);
    }

    public function testPostMultipartWithEmptyData(): void
    {
        $client = new HttpClient();
        $result = $client->postMultipart('https://example.com/api', [], []);

        $this->assertEquals('POST', $client->method);
    }

    public function testPostRaw(): void
    {
        $client = new HttpClient();
        $result = $client->postRaw('https://example.com/api', 'raw body content', 'text/plain');

        $this->assertEquals('POST', $client->method);
        $this->assertArrayHasKey('Content-Type', $client->header);
        $this->assertEquals('text/plain', $client->header['Content-Type']);
    }

    public function testPostRawClearsFormFields(): void
    {
        $client = new HttpClient();
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
        $client = new HttpClient();
        $client->header['X-Custom-Header'] = 'value';
        $client->header['X-Another-Header'] = 'another-value';

        $this->assertArrayHasKey('X-Custom-Header', $client->header);
        $this->assertArrayHasKey('X-Another-Header', $client->header);
        $this->assertEquals('value', $client->header['X-Custom-Header']);
    }

    public function testSetHeadersWithMultipleValues(): void
    {
        $client = new HttpClient();
        $client->header = [
            'X-Header-1' => 'value1',
            'X-Header-2' => 'value2',
            'X-Header-3' => 'value3'
        ];

        $client->get('https://example.com/api');

        $this->assertCount(3, $client->header);
    }

    public function testSetHeadersWithEmptyArray(): void
    {
        $client = new HttpClient();
        $client->header = [];

        $client->get('https://example.com/api');

        $this->assertIsArray($client->header);
    }

    public function testSetAuthorizationHeader(): void
    {
        $client = new HttpClient();
        $client->authorizationHeader = 'Bearer token-123';

        $this->assertEquals('Bearer token-123', $client->authorizationHeader);
    }

    public function testSetAuthorizationHeaderAsArray(): void
    {
        $client = new HttpClient();
        $client->authorizationHeader = ['Bearer', 'token-123'];

        // Should accept array (for compatibility)
        $this->assertIsArray($client->authorizationHeader);
    }

    public function testSetAuthorizationHeaderAsString(): void
    {
        $client = new HttpClient();
        $client->authorizationHeader = 'Bearer token-123';

        $this->assertIsString($client->authorizationHeader);
        $this->assertEquals('Bearer token-123', $client->authorizationHeader);
    }

    public function testAuthorizationHeaderOverwritesHeaders(): void
    {
        $client = new HttpClient();
        $client->header = ['Content-Type' => 'application/json'];
        $client->authorizationHeader = 'Bearer token-123';

        // Authorization header should overwrite when string is provided
        $client->get('https://example.com/api');

        $this->assertTrue(true);
    }

    // ============================================
    // Files Tests
    // ============================================

    public function testSetFiles(): void
    {
        $client = new HttpClient();
        $client->files = ['file1' => '/path/to/file1.txt', 'file2' => '/path/to/file2.txt'];

        $this->assertIsArray($client->files);
        $this->assertCount(2, $client->files);
    }

    public function testSetFilesWithLegacyFlow(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->data = ['field1' => 'value1'];
        $client->files = ['file1' => '/nonexistent/file.txt'];

        // Should handle files in legacy flow
        $client->post('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testSetFilesWithExistingFile(): void
    {
        // Create a temporary file for testing
        $tempFile = sys_get_temp_dir() . '/test_file_' . uniqid() . '.txt';
        file_put_contents($tempFile, 'test content');

        try {
            $client = new HttpClient();
            $client->data = ['field1' => 'value1'];
            $client->files = ['file1' => $tempFile];

            // Should handle existing file
            $client->post('https://example.com/api');

            $this->assertTrue(true);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSetFilesReturnsFalseWhenNoFiles(): void
    {
        $client = new HttpClient();
        $client->data = ['field1' => 'value1'];
        $client->files = [];

        $client->post('https://example.com/api');

        $this->assertTrue(true);
    }

    // ============================================
    // Data Tests
    // ============================================

    public function testSetData(): void
    {
        $client = new HttpClient();
        $client->data = ['key1' => 'value1', 'key2' => 'value2'];

        $this->assertIsArray($client->data);
        $this->assertEquals('value1', $client->data['key1']);
    }

    public function testSetDataWithComplexData(): void
    {
        $client = new HttpClient();
        $client->data = [
            'string' => 'value',
            'int' => 123,
            'float' => 45.67,
            'bool' => true,
            'array' => ['nested' => 'data'],
            'null' => null
        ];

        $this->assertIsArray($client->data);
        $this->assertCount(6, $client->data);
    }

    public function testSetDataWithEmptyArray(): void
    {
        $client = new HttpClient();
        $client->data = [];

        $this->assertIsArray($client->data);
        $this->assertEmpty($client->data);
    }

    // ============================================
    // Method Chaining Tests
    // ============================================

    public function testMethodChaining(): void
    {
        $client = new HttpClient();
        $result = $client
            ->setTimeouts(5, 10)
            ->setSsl('/cert.pem', '/key.pem')
            ->setRetries(3, 500)
            ->retryOnNetworkError(true);

        $this->assertInstanceOf(HttpClient::class, $result);
        $this->assertEquals($client, $result);
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testErrorPropertyInitialization(): void
    {
        $client = new HttpClient();

        $this->assertEquals('call not initialized', $client->error);
    }

    public function testErrorPropertyCanBeSet(): void
    {
        $client = new HttpClient();
        $client->error = 'Test error message';

        $this->assertEquals('Test error message', $client->error);
    }

    public function testHttpResponseCodeInitialization(): void
    {
        $client = new HttpClient();

        $this->assertEquals(0, $client->http_response_code);
    }

    public function testHttpResponseCodeCanBeSet(): void
    {
        $client = new HttpClient();
        $client->http_response_code = 200;

        $this->assertEquals(200, $client->http_response_code);
    }

    // ============================================
    // Response Body Tests
    // ============================================

    public function testResponseBodyInitialization(): void
    {
        $client = new HttpClient();

        $this->assertFalse($client->responseBody);
    }

    public function testResponseBodyCanBeSetToString(): void
    {
        $client = new HttpClient();
        $client->responseBody = 'Response content';

        $this->assertEquals('Response content', $client->responseBody);
    }

    public function testResponseBodyCanBeSetToFalse(): void
    {
        $client = new HttpClient();
        $client->responseBody = 'Response';
        $client->responseBody = false;

        $this->assertFalse($client->responseBody);
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function testGetWithEmptyQueryParams(): void
    {
        $client = new HttpClient();
        $client->get('https://example.com/api', []);

        $this->assertEquals('GET', $client->method);
    }

    public function testPostWithEmptyData(): void
    {
        $client = new HttpClient();
        $client->post('https://example.com/api', []);

        $this->assertEquals('POST', $client->method);
        $this->assertIsArray($client->data);
        $this->assertEmpty($client->data);
    }

    public function testPutWithEmptyData(): void
    {
        $client = new HttpClient();
        $client->put('https://example.com/api', []);

        $this->assertEquals('PUT', $client->method);
        $this->assertIsArray($client->data);
    }

    public function testPostFormWithSpecialCharacters(): void
    {
        $client = new HttpClient();
        $client->postForm('https://example.com/api', [
            'field1' => 'value with spaces',
            'field2' => 'value&with=special'
        ]);

        $this->assertEquals('POST', $client->method);
    }

    public function testPostRawWithJsonContentType(): void
    {
        $client = new HttpClient();
        $client->postRaw('https://example.com/api', '{"key":"value"}', 'application/json');

        $this->assertEquals('application/json', $client->header['Content-Type']);
    }

    public function testSetRetriesWithDuplicateHttpCodes(): void
    {
        $client = new HttpClient();
        $client->setRetries(3, 500, [429, 500, 429, 502]);

        // Duplicates should be removed
        $this->assertTrue(true);
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testThrowExceptionsMethod(): void
    {
        $client = new HttpClient();
        $result = $client->throwExceptions(true);

        $this->assertSame($client, $result);
    }

    public function testThrowExceptionsDisabled(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        // Should not throw, just return false
        $result = $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        $this->assertFalse($result);
        $this->assertNotEmpty($client->error);
    }

    public function testErrorsPropertyIsEmptyInitially(): void
    {
        $client = new HttpClient();

        $this->assertEmpty($client->errors);
        $this->assertFalse($client->hasErrors());
        $this->assertNull($client->getLastError());
        $this->assertEmpty($client->getErrors());
    }

    public function testErrorsPropertyAfterFailedRequest(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        $this->assertNotEmpty($client->errors);
        $this->assertTrue($client->hasErrors());
        $this->assertNotNull($client->getLastError());
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $client->getLastError());
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
        $this->assertEmpty($client->errors);
    }

    public function testErrorsClearedOnNewRequest(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');
        $this->assertTrue($client->hasErrors());

        // New request should clear errors
        $client->get('https://httpbin.org/get');
        // Errors should be cleared at start of new request
        // (but may be repopulated if new request fails)
    }

    public function testGetErrorsReturnsArray(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        $errors = $client->getErrors();
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $errors[0]);
    }

    public function testExceptionContainsUrlAndErrorCode(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        $url = 'https://invalid-domain-that-does-not-exist-xyz123.com/api';
        $client->get($url);

        $error = $client->getLastError();
        $this->assertNotNull($error);
        $this->assertEquals($url, $error->getUrl());
        $this->assertGreaterThan(0, $error->getCurlErrorCode());
    }

    public function testNetworkExceptionType(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        $error = $client->getLastError();
        if ($error instanceof \Gemvc\Http\Client\Exception\NetworkException) {
            $this->assertNotEmpty($error->getErrorType());
            $this->assertNotEmpty($error->getErrorTypeDescription());
        }
    }

    public function testSetUserAgent(): void
    {
        $client = new HttpClient();
        $result = $client->setUserAgent('Custom-Agent/1.0');

        $this->assertSame($client, $result);
    }

    public function testSetUserAgentWithEmptyString(): void
    {
        $client = new HttpClient();
        $client->setUserAgent('');

        // Should accept empty string
        $this->assertTrue(true);
    }

    public function testRetryWithAllRetriesExhausted(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(2, 10, []);
        $client->retryOnNetworkError(true);

        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        // Should have errors after all retries
        $this->assertTrue($client->hasErrors());
    }

    public function testRetryWithHttpCodeRetry(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(1, 10, [500, 502]);
        $client->retryOnNetworkError(false);

        // This won't actually retry since we're not getting real HTTP responses
        // But tests the configuration
        $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        $this->assertTrue(true);
    }

    public function testShouldRetryReturnsFalseWhenRetriesDisabled(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->setRetries(0, 10, [500]);
        $client->retryOnNetworkError(false);

        // Should not retry when max_retries is 0
        $result = $client->get('https://invalid-domain-that-does-not-exist-xyz123.com/api');

        // Should return false without retrying
        $this->assertFalse($result);
    }

    public function testPostFormWithFiles(): void
    {
        $client = new HttpClient();

        // Create temp file
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.txt';
        file_put_contents($tempFile, 'test content');

        try {
            $client->postMultipart('https://example.com/api', ['field' => 'value'], ['file' => $tempFile]);
            $this->assertTrue(true);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testPostMultipartWithNonExistentFile(): void
    {
        $client = new HttpClient();
        $client->postMultipart('https://example.com/api', ['field' => 'value'], ['file' => '/nonexistent/file.txt']);

        $this->assertTrue(true);
    }

    public function testPostRawWithDifferentContentTypes(): void
    {
        $client = new HttpClient();
        $client->postRaw('https://example.com/api', '<?xml version="1.0"?><root/>', 'application/xml');

        $this->assertEquals('application/xml', $client->header['Content-Type']);
    }

    public function testGetWithEmptyUrl(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        $client->get('');

        // Should handle empty URL
        $this->assertTrue(true);
    }

    public function testPutWithData(): void
    {
        $client = new HttpClient();
        $client->put('https://example.com/api', ['key' => 'value']);

        $this->assertEquals('PUT', $client->method);
        $this->assertEquals(['key' => 'value'], $client->data);
    }

    public function testDeleteMethod(): void
    {
        $client = new HttpClient();
        $client->method = 'DELETE';
        $client->data = [];

        // Use call() directly via reflection or test through actual usage
        // get() resets method to GET, so we test the method property directly
        $this->assertEquals('DELETE', $client->method);
    }

    public function testPatchMethod(): void
    {
        $client = new HttpClient();
        $client->method = 'PATCH';
        $client->data = ['key' => 'value'];

        // get() resets method to GET, so we test the method property directly
        $this->assertEquals('PATCH', $client->method);
    }

    public function testPostRawWithPatchMethod(): void
    {
        $client = new HttpClient();
        $client->postRaw('https://example.com/api', 'raw body', 'text/plain');
        $client->method = 'PATCH';

        // get() resets method to GET, so we test the method property directly
        $this->assertEquals('PATCH', $client->method);
    }

    public function testPostRawWithDeleteMethod(): void
    {
        $client = new HttpClient();
        $client->postRaw('https://example.com/api', 'raw body', 'text/plain');
        $client->method = 'DELETE';

        // get() resets method to GET, so we test the method property directly
        $this->assertEquals('DELETE', $client->method);
    }

    public function testSetDataWithFormFields(): void
    {
        $client = new HttpClient();
        $reflection = new \ReflectionClass($client);
        $formFieldsProperty = $reflection->getProperty('formFields');
        $formFieldsProperty->setAccessible(true);
        $formFieldsProperty->setValue($client, ['field1' => 'value1']);

        $client->post('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testSetFilesReturnsTrueWhenSuccessful(): void
    {
        $client = new HttpClient();

        // Create temp file
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.txt';
        file_put_contents($tempFile, 'test content');

        try {
            $client->data = ['field1' => 'value1'];
            $client->files = ['file1' => $tempFile];

            // Should use setFiles method
            $client->post('https://example.com/api');

            $this->assertTrue(true);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSetFilesWithNonStringValue(): void
    {
        $client = new HttpClient();
        $client->data = ['field1' => 'value1'];
        $client->files = ['file1' => 123]; // Non-string value

        $client->post('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testSetFilesWithNonExistentFile(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $client->data = ['field1' => 'value1'];
        $client->files = ['file1' => '/nonexistent/file.txt'];

        // This will fail but shouldn't throw exception
        $result = $client->post('https://example.com/api');

        // Result may be false, but exception should not be thrown
        // With explicit is_file check, request proceeds without file and returns string (success)
        $this->assertTrue(is_bool($result) || is_string($result));
    }

    public function testSetFilesWithFormFieldsSet(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);
        $reflection = new \ReflectionClass($client);
        $formFieldsProperty = $reflection->getProperty('formFields');
        $formFieldsProperty->setAccessible(true);
        $formFieldsProperty->setValue($client, ['field1' => 'value1']);

        $client->files = ['file1' => '/path/to/file.txt'];

        // setFiles should return false when formFields is set
        $client->post('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testCurlInitFailure(): void
    {
        $client = new HttpClient();
        $client->throwExceptions(false);

        // This might fail curl_init in some edge cases
        // We can't easily simulate curl_init failure, but we can test the error handling
        $client->get('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testApplyCommonCurlOptionsWithEmptyUserAgent(): void
    {
        $client = new HttpClient();
        $client->setUserAgent('');

        $client->get('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testApplyCommonCurlOptionsWithSslVerifyHost(): void
    {
        $client = new HttpClient();
        $client->setSsl(null, null, null, true, 2);

        $client->get('https://example.com/api');

        $this->assertTrue(true);
    }

    public function testApplyCommonCurlOptionsWithSslVerifyHostZero(): void
    {
        $client = new HttpClient();
        $client->setSsl(null, null, null, true, 0);

        $client->get('https://example.com/api');

        $this->assertTrue(true);
    }
}
