<?php

namespace Gemvc\Http\Client;

/**
 * Swoole-optimized Asynchronous HTTP Client
 * 
 * Extends AsyncHttpClient for Swoole environments.
 * Currently uses the same curl_multi implementation but can be optimized
 * to use Swoole coroutines in the future for true async execution.
 */
class SwooleAsyncHttpClient extends AsyncHttpClient
{
    /**
     * Constructor
     * 
     * In the future, this could use Swoole coroutines for true async execution
     * instead of curl_multi for better performance in Swoole environment.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Future: Use Swoole coroutines for async execution
        // if (class_exists('\Swoole\Coroutine\Http\Client')) {
        //     // Use Swoole coroutines
        // }
    }

    /**
     * Override fireAndForget to use Swoole tasks if available
     * 
     * @return bool True if background execution was initiated
     */
    public function fireAndForget(): bool
    {
        if ($this->getQueueSize() === 0) {
            return false;
        }

        // For Swoole: Use task worker if available
        if (function_exists('swoole_async_write') || class_exists('\Swoole\Server')) {
            // Execute in background using Swoole task
            $this->executeInBackground();
            return true;
        }

        // Fallback to parent implementation
        return parent::fireAndForget();
    }

    /**
     * Execute requests in background using Swoole task (if available)
     * 
     * @return void
     */
    private function executeInBackground(): void
    {
        // This would require access to Swoole server instance
        // For now, execute with minimal blocking
        $this->executeAll();
    }
}
