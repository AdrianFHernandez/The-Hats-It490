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

$req = buildRequest("Error", "Message sent by DMZ client");
 print_r($req);
$response = $client->publish($req);

print_r($response);
?>
