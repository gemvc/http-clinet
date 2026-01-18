<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\Exception\HttpClientException;

class HttpClientExceptionTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new HttpClientException(
            'Test error message',
            500,
            $previous,
            'https://example.com/api',
            404,
            28
        );

        $this->assertEquals('Test error message', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals('https://example.com/api', $exception->getUrl());
        $this->assertEquals(404, $exception->getHttpCode());
        $this->assertEquals(28, $exception->getCurlErrorCode());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new HttpClientException('Error occurred');

        $this->assertEquals('Error occurred', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getUrl());
        $this->assertEquals(0, $exception->getHttpCode());
        $this->assertEquals(0, $exception->getCurlErrorCode());
    }

    public function testGetUrl(): void
    {
        $exception = new HttpClientException('Error', 0, null, 'https://test.com');
        $this->assertEquals('https://test.com', $exception->getUrl());
    }

    public function testGetHttpCode(): void
    {
        $exception = new HttpClientException('Error', 0, null, null, 500);
        $this->assertEquals(500, $exception->getHttpCode());
    }

    public function testGetCurlErrorCode(): void
    {
        $exception = new HttpClientException('Error', 0, null, null, 0, 6);
        $this->assertEquals(6, $exception->getCurlErrorCode());
    }
}
