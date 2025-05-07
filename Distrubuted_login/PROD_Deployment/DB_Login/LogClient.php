#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");

function buildRequest($type, $message) {
    return [
        "type" => $type,
        "message" => $message
    ];
}



$client = new rabbitMQClient("PRODDistributedLogginRabbitMQ.ini", "PRODistributedLogginServer");

$req = buildRequest("Error", "Message sent by DB Client");

 print_r($req);
$response = $client->publish($req);

print_r($response);
?>
