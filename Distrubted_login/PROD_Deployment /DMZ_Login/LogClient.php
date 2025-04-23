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



$client = new rabbitMQClient("PRODDistributedLogginRabbitMQ.ini", "PRODistributedLogginServer");

$req = buildRequest("Error", ["message"=>"found DMZ error"]);
// print_r($req);
$response = $client->publish($req);

print_r($response);
?>
