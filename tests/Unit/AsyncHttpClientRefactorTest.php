<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\AsyncHttpClient;

class AsyncHttpClientRefactorTest extends TestCase
{
    public function testJsonEncodingFailureReturnsFailedResult(): void
    {
        $client = new AsyncHttpClient();

        // Create circular reference to force JSON encoding error
        $data = [];
        $data['self'] = &$data;

        $client->addPost('req1', 'https://example.com/api', $data);

        // This should not throw exception, but result in an error result for this request
        $results = $client->executeAll();

        $this->assertArrayHasKey('req1', $results);
        $this->assertFalse($results['req1']['success']);
        $this->assertStringContainsString('Failed to initialize cURL handle', $results['req1']['error']);
    }

    public function testFireAndForgetRestoresTimeouts(): void
    {
        $client = new AsyncHttpClient();
        $client->setTimeouts(30, 60);

        // We can't easily force executeAll to throw a fatal error without mocking protected methods
        // But we can verify that fireAndForget runs without exception and restores timeouts
        // in normal condition (simulating fallback path by ensuring fastcgi doesn't exist or we mock it, 
        // but since fastcgi_finish_request likely doesn't exist in CLI/PHPUnit, it goes to fallback)

        $client->addGet('req1', 'https://example.com/api');

        // Mock executeAll behavior if possible, or just rely on the fact that running it
        // on a dummy URL won't crash but will just fail the request

        $client->fireAndForget();

        // Reflection to check timeouts
        $reflection = new \ReflectionClass($client);
        $connectTimeoutProp = $reflection->getProperty('connect_timeout');
        $connectTimeoutProp->setAccessible(true);
        $timeoutProp = $reflection->getProperty('timeout');
        $timeoutProp->setAccessible(true);

        $this->assertEquals(30, $connectTimeoutProp->getValue($client));
        $this->assertEquals(60, $timeoutProp->getValue($client));
    }
}
