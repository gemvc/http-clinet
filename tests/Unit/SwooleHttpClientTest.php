<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\SwooleHttpClient;

class SwooleHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            // Use stubs for testing on environments without Swoole
            $stubFile = __DIR__ . '/../Stubs/Swoole.php';
            if (file_exists($stubFile)) {
                require_once $stubFile;
            } else {
                $this->markTestSkipped('Swoole extension is not available and Stubs not found.');
            }
        }
    }

    public function testConstructor(): void
    {
        $client = new SwooleHttpClient();
        $this->assertInstanceOf(SwooleHttpClient::class, $client);
    }

    public function testAddRequest(): void
    {
        $client = new SwooleHttpClient();
        $client->addGet('req1', 'http://example.com');
        $this->assertEquals(1, $client->getQueueSize());
    }

    public function testClearQueue(): void
    {
        $client = new SwooleHttpClient();
        $client->addGet('req1', 'http://example.com');
        $client->clearQueue();
        $this->assertEquals(0, $client->getQueueSize());
    }
}
