#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Replace with your RabbitMQ credentials
$connection = new AMQPStreamConnection('100.96.178.79', 5672, 'hats', 'it490@123', '/');
$hannel = $connection->channel();

// Declare fanout exchange and optional queue for backup
$channel->exchange_declare('log_broadcast', 'fanout', false, true, false);
$channel->queue_declare('log_queue', false, true, false, false);

// Dummy logs to test broadcast
$dummyLogs = [
    ['level' => 'INFO', 'message' => 'Deployment started', 'machine' => gethostname()],
    ['level' => 'WARNING', 'message' => 'High memory usage on server03', 'machine' => gethostname()],
    ['level' => 'ERROR', 'message' => 'Service failed to start', 'machine' => gethostname()],
];

foreach ($dummyLogs as $logData) {
    $logData['timestamp'] = date('c');
    $msg = new AMQPMessage(json_encode($logData), [
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
    ]);

    // Send to broadcast
    $channel->basic_publish($msg, 'log_broadcast');
    
    // Optional: Also store in persistent queue
    $channel->basic_publish($msg, '', 'log_queue');

    echo "Sent log: " . $logData['message'] . "\n";
}

$channel->close();
$connection->close();
