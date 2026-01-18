<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\Exception\TimeoutException;

class TimeoutExceptionTest extends TestCase
{
    public function testConstructorWithConnectionTimeout(): void
    {
        $exception = new TimeoutException(
            'Timeout error',
            0,
            null,
            'https://example.com',
            0,
            28,
            true
        );

        $this->assertTrue($exception->isConnectionTimeout());
        $this->assertEquals('https://example.com', $exception->getUrl());
    }

    public function testConstructorWithRequestTimeout(): void
    {
        $exception = new TimeoutException(
            'Timeout error',
            0,
            null,
            'https://example.com',
            0,
            28,
            false
        );

        $this->assertFalse($exception->isConnectionTimeout());
    }

    public function testIsConnectionTimeout(): void
    {
        $connectionTimeout = new TimeoutException('Error', 0, null, null, 0, 0, true);
        $this->assertTrue($connectionTimeout->isConnectionTimeout());

        $requestTimeout = new TimeoutException('Error', 0, null, null, 0, 0, false);
        $this->assertFalse($requestTimeout->isConnectionTimeout());
    }

    public function testInheritsFromHttpClientException(): void
    {
        $exception = new TimeoutException('Error', 0, null, 'https://test.com', 500, 28, true);
        
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $exception);
        $this->assertEquals('https://test.com', $exception->getUrl());
        $this->assertEquals(500, $exception->getHttpCode());
        $this->assertEquals(28, $exception->getCurlErrorCode());
    }
}
