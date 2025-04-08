#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

function logConsumer($msg) {
    $logData = json_decode($msg->body, true);
    
    if (!$logData) {
        echo "[ERROR] Invalid log message.\n";
        return;
    }

    $timestamp = $logData['timestamp'] ?? date('c');
    $source = $logData['source'] ?? 'Unknown';
    $bundle = $logData['bundle_name'] ?? 'Unknown Bundle';
    $version = $logData['version'] ?? 'N/A';
    $type = strtoupper($logData['log_type'] ?? 'INFO');
    $message = $logData['message'] ?? 'No message.';

    $logLine = "[$timestamp] [$type] [$source] [$bundle] v$version: $message\n";

    // Store log to a file
    $logFile = '/var/log/deployment_logs.log';
    file_put_contents($logFile, $logLine, FILE_APPEND);

    echo "Received log: $logLine";
}

$connection = new AMQPStreamConnection(
    '100.96.178.79',
    5672,
    'hats',
    'it490@123',
    'hatshost'
);
$channel = $connection->channel();

// Declare the fanout exchange
$channel->exchange_declare('log_broadcast', 'fanout', false, true, false);

// Declare a unique, exclusive, autodelete queue
list($queue_name,,) = $channel->queue_declare("", false, false, true, false);

// Bind it to the fanout exchange
$channel->queue_bind($queue_name, 'log_broadcast');

// Start consuming
echo "Waiting for logs...\n";
$channel->basic_consume($queue_name, '', false, true, false, false, 'logConsumer');

// Event loop
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
