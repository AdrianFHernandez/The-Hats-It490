#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");

function buildRequest($type, $payload = []) {
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}



$client = new rabbitMQClient("QADistributedLogginRabbitMQ.ini", "QADistributedLogginServer");

$req = buildRequest("Error", ["message"=>"found DB error"]);
// print_r($req);
$response = $client->publish($req);

print_r($response);
?>
