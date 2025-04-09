#!/usr/bin/php
<?php

require_once("rabbitMQLoggingLib.inc");


/*function requestProcessor($request) {

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
}*/

function requestProcessor($request) {
    // log path
    $logFile = "/home/Deployment/DistributedLogin/devdistributed_db_login.log";
    $timestamp = date("D M d H:i:s Y");
    $logEntry = "[$timestamp] " . json_encode($request) . "\n";

    
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // Now proceed with request handling
    print_r($request);
    if (!isset($request['type'])) {
        return buildResponse("UNKNOWN", "ERROR", ["message" => "Missing type"]);
    }

    switch ($request['type']) {
        case "Error":
            return ["log" => "ts"];
        default:
            break;
    }

    return buildResponse($request['type'], "UNKNOWN", ["message" => "Unhandled type"]);
}

echo "DEV Login Server Started\n";
$server = new rabbitMQServer("DEVDistributedLogginRabbitMQ.ini", "DEVDistributedLogginServer");
$server->process_requests('requestProcessor');
echo "Login Server Ended\n";
?>
