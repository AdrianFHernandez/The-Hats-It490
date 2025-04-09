#!/usr/bin/php
<?php

require_once("rabbitMQLoggingLib.inc");


function requestProcessor($request) {

    print_r($request);
    if (!isset($request['type'])) {
        return buildResponse("UNKNOWN", "ERROR", ["message" => "Missing type"]);
    }


    switch ($request['type']) {
        case "Error":
            return ["log"=>"ts"];
         default:
            break;
            
    }

    return buildResponse($request['type'], "UNKNOWN", ["message" => "Unhandled type"]);
}

echo "Deployment Server Started\n";
$server = new rabbitMQServer("DistributedLogginRabbitMQ.ini", "DistributedLogginServer");
$server->process_requests('requestProcessor');
echo "Deployment Server Ended\n";
?>
