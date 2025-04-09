#!/usr/bin/php
<?php

require_once("rabbitMQLoggingLib.inc");

function buildRequest($type, $payload = []) {
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}



$client = new rabbitMQClient("DEVDistributedLogginRabbitMQ.ini", "DEVDistributedLogginServer");

$req = buildRequest("Error", ["message"=>"found DB error"]);
// print_r($req);
$response = $client->publish($req);

print_r($response);
?>
