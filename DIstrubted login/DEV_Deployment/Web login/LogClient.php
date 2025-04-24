#!/usr/bin/php
<?php

require_once("rabbitMQLoggingLib.inc");

function buildRequest($type, $message) {
    return [
        "type" => $type,
        "message" => $message
    ];
}



$client = new rabbitMQClient("DEVDistributedLogginRabbitMQ.ini", "DEVDistributedLogginServer");

$req = buildRequest("Error", "Message sent by Web Client");

 print_r($req);
$response = $client->publish($req);

print_r($response);
?>
