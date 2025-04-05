#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once 'rabbitMQLib.inc';
require_once 'lib/modules.php';

function logConsumer($msg) {
    $logData = json_decode($msg->body, true);
    
    // Store log to a file
    $logFile = '/var/log/deployment_logs.log';
    file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);

    echo "Received log: " . json_encode($logData) . PHP_EOL;
}

$connection = new AMQPStreamConnection(
    '100.96.178.79',    // or the RabbitMQ server's IP
    5672,           // default port
    'hats',         // username
    'it490@123', 
    'hatsHost'             
);
$channel = $connection->channel();
$channel->queue_declare('log_queue', false, true, false, false);

$callback = function($msg) {
    logConsumer($msg);
};

$channel->basic_consume('log_queue', '', false, true, false, false, $callback);

echo "Waiting for logs...\n";
while($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();

?>