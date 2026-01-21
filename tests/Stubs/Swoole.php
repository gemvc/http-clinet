<?php

namespace Swoole;

class Coroutine
{
    public static function create(callable $callback): void
    {
    }
    public static function sleep(float $seconds): void
    {
    }
}

namespace Swoole\Coroutine;

class Barrier
{
    public static function make(): self
    {
        return new self();
    }
    public static function wait(self $barrier): void
    {
    }
}

class Channel
{
    public function __construct(int $capacity = 1)
    {
    }
    public function push(mixed $data, float $timeout = -1): bool
    {
        return true;
    }
    public function pop(float $timeout = -1): mixed
    {
        return true;
    }
}

namespace Swoole\Coroutine\Http;

class Client
{
    public int $errCode = 0;
    public string $errMsg = '';
    public array $headers = [];

    public function __construct(string $host, int $port = 80, bool $ssl = false)
    {
    }
    public function set(array $settings): bool
    {
        return true;
    }
    public function setHeaders(array $headers): bool
    {
        return true;
    }
    public function setMethod(string $method): bool
    {
        return true;
    }
    public function setData(mixed $data): bool
    {
        return true;
    }
    public function execute(string $path): bool
    {
        return true;
    }
    public function getBody(): string
    {
        return '';
    }
    public function getStatusCode(): int
    {
        return 200;
    }
    public function close(): bool
    {
        return true;
    }
}
