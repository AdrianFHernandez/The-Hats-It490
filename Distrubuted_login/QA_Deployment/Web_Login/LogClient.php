#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");

function buildRequest($type, $message) {
    return [
        "type" => $type,
        "message" => $message
    ];
}



$client = new rabbitMQClient("QADistributedLogginRabbitMQ.ini", "QADistributedLogginServer");

$req = buildRequest("LOG", "Found Web LOG");
// print_r($req);
$response = $client->publish($req);

print_r($response);
?>
