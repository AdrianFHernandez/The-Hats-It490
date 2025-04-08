#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

echo "Starting log test sender...\n";

// Change this if you're not using the default vhost
$vhost = '/';

try {
    $connection = new AMQPStreamConnection(
        '100.96.178.79', // RabbitMQ host
        5672,            // RabbitMQ port
        'hats',          // Username
        'it490@123',     // Password
        'hatshost'       // Vhost (use '/' if you're not sure)
    );

    $channel = $connection->channel();

    // Declare the exchange
    $channel->exchange_declare('log_broadcast', 'fanout', false, true, false);

    // Declare the queue (for direct test verification)
    $channel->queue_declare('log_queue', false, true, false, false);

    // Create test log
    $log = [
        "log_type"     => "INFO",
        "message"      => "This is a test log message from log_test_sender.php",
        "timestamp"    => date('c'),
        "source"       => "log_test_sender.php",
        "bundle_name"  => "TestBundle",
        "version"      => "1.0"
    ];

    $msg = new AMQPMessage(json_encode($log), [
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
    ]);

    // Send to exchange (fanout)
    $channel->basic_publish($msg, 'log_broadcast');

    // Also publish directly to log_queue
    $channel->basic_publish($msg, '', 'log_queue');

    echo "[✓] Test log sent to exchange 'log_broadcast' and queue 'log_queue'.\n";

    $channel->close();
    $connection->close();

} catch (Exception $e) {
    echo "[✗] Failed to send log: " . $e->getMessage() . "\n";
}
?>
