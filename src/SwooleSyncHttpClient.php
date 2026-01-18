<?php

namespace Gemvc\Http\Client;

/**
 * Swoole-optimized Synchronous HTTP Client
 * 
 * Extends SyncHttpClient for Swoole environments.
 * Currently uses the same cURL implementation but can be optimized
 * to use Swoole's HTTP client in the future.
 */
class SwooleSyncHttpClient extends SyncHttpClient
{
    /**
     * Constructor
     * 
     * In the future, this could check for Swoole HTTP client availability
     * and use it instead of cURL for better performance in Swoole environment.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Future: Check if Swoole HTTP client is available
        // if (class_exists('\Swoole\Coroutine\Http\Client')) {
        //     // Use Swoole HTTP client
        // }
    }
}
