<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\Client\Exception\NetworkException;

class NetworkExceptionTest extends TestCase
{
    public function testConstructorWithErrorType(): void
    {
        $exception = new NetworkException(
            'Network error',
            0,
            null,
            'https://example.com',
            0,
            6,
            NetworkException::TYPE_DNS_ERROR
        );

        $this->assertEquals(NetworkException::TYPE_DNS_ERROR, $exception->getErrorType());
    }

    public function testIsDnsError(): void
    {
        $exception = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_DNS_ERROR);
        $this->assertTrue($exception->isDnsError());
        $this->assertFalse($exception->isConnectionError());
        $this->assertFalse($exception->isSslError());
    }

    public function testIsConnectionError(): void
    {
        $exception = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_CONNECTION_ERROR);
        $this->assertTrue($exception->isConnectionError());
        $this->assertFalse($exception->isDnsError());
    }

    public function testIsSslError(): void
    {
        $exception = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_SSL_ERROR);
        $this->assertTrue($exception->isSslError());
        $this->assertFalse($exception->isConnectionError());
    }

    public function testIsReceiveError(): void
    {
        $exception = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_RECEIVE_ERROR);
        $this->assertTrue($exception->isReceiveError());
        $this->assertFalse($exception->isSendError());
    }

    public function testIsSendError(): void
    {
        $exception = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_SEND_ERROR);
        $this->assertTrue($exception->isSendError());
        $this->assertFalse($exception->isReceiveError());
    }

    public function testGetErrorTypeDescription(): void
    {
        $dnsException = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_DNS_ERROR);
        $this->assertEquals('DNS resolution failed', $dnsException->getErrorTypeDescription());

        $connException = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_CONNECTION_ERROR);
        $this->assertEquals('Connection failed', $connException->getErrorTypeDescription());

        $sslException = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_SSL_ERROR);
        $this->assertEquals('SSL/TLS handshake failed', $sslException->getErrorTypeDescription());

        $recvException = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_RECEIVE_ERROR);
        $this->assertEquals('Data receive error', $recvException->getErrorTypeDescription());

        $sendException = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_SEND_ERROR);
        $this->assertEquals('Data send error', $sendException->getErrorTypeDescription());

        $unknownException = new NetworkException('Error', 0, null, null, 0, 0, NetworkException::TYPE_UNKNOWN);
        $this->assertEquals('Unknown network error', $unknownException->getErrorTypeDescription());
    }

    public function testInheritsFromHttpClientException(): void
    {
        $exception = new NetworkException('Error', 0, null, 'https://test.com', 500, 6, NetworkException::TYPE_DNS_ERROR);
        
        $this->assertInstanceOf(\Gemvc\Http\Client\Exception\HttpClientException::class, $exception);
        $this->assertEquals('https://test.com', $exception->getUrl());
        $this->assertEquals(500, $exception->getHttpCode());
        $this->assertEquals(6, $exception->getCurlErrorCode());
    }
}
