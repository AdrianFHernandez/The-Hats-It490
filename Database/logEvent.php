#!/usr/bin/php
<?php
if ($argc < 3) {
    exit("Usage: logEvent.php <type> <message_or_error>\n");
}

require_once('rabbitMQLib.inc');

$logType = strtoupper($argv[1]); 
$logText = $argv[2];

if (!in_array($logType, ['LOG', 'ERROR'])) {
    exit("Invalid type: must be LOG or ERROR\n");
}

$payload = [
	'type' => $logType,
	'timestamp' => date("D M d H:i:s Y"),
	'message' => $logText
];



$client = new rabbitMQClient("DEVDistributedLogginRabbitMQ.ini", "DEVDistributedLogginServer");
$client->publish($payload);

