#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");

function buildRequest($type, $message) {
    return [
        "type" => $type,
        "message" => $message
    ];
}



$client = new rabbitMQClient("DEVDistributedLogginRabbitMQ.ini", "DEVDistributedLogginServer");

$req = buildRequest("Error", "Message from DB Client");
print_r($req);
$response = $client->publish($req);
print_r($response);
?>
