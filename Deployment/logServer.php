#!/usr/bin/php
<?php

require_once(__DIR__ . '/vendor/autoload.php'); // if using Composer for php-amqplib
use PhpAmqpLib\Connection\AMQPStreamConnection;

function logHandler($request) {
    $log = json_decode($request, true);

    if (!isset($log['log_type']) || !isset($log['message'])) {
        echo "[ERROR] Invalid log format received.\n";
        return;
    }

    $timestamp = $log['timestamp'] ?? date('c');
    $source = $log['source'] ?? 'Unknown Source';
    $bundle = $log['bundle_name'] ?? 'Unknown Bundle';
    $version = $log['version'] ?? 'N/A';
    $type = strtoupper($log['log_type']);

    $logLine = "[$timestamp] [$type] [$source] [$bundle] v$version: {$log['message']}\n";

    // Save to log file
    file_put_contents("/var/log/deployment_logs.log", $logLine, FILE_APPEND);
    echo $logLine;
}

echo "Logging Server Started\n";

// Connect to RabbitMQ
$connection = new AMQPStreamConnection('100.96.178.79', 5672, 'hats', 'it490@123', 'hatshost');
$channel = $connection->channel();


$channel->exchange_declare('log_broadcast', 'fanout', false, true, false);


list($queue_name,,) = $channel->queue_declare("", false, false, true, false);


$channel->queue_bind($queue_name, 'log_broadcast');


$channel->basic_consume($queue_name, '', false, true, false, false, function ($msg) {
    logHandler($msg->body);
});


while ($channel->is_consuming()) {
    $channel->wait();
}

// Cleanup (unreachable normally)
$channel->close();
$connection->close();
echo "Logging Server Ended\n";
