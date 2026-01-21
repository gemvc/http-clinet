<?php
require 'vendor/autoload.php';

use Gemvc\Http\Client\HttpClient;

try {
    $client = new HttpClient();
    echo "Success\n";
    $client->setTimeouts(1, 1);
    echo "SetTimeouts Success\n";
} catch (\Throwable $e) {
    echo "Caught: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
