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


    $logDir = "/var/log/LogResponse";
    $logFile = $logDir . "proddistributed_web_login.log";
    $errorLogFile = $logDir . "errordevdistributed_db_login.err";

function requestProcessor($request) {
    // Base log file paths
    
    global $logFile, $logError;
    $timestamp = date("D M d H:i:s Y");
    $logEntry = "[$timestamp] " . json_encode($request) . "\n";

    // Determine where to log
    if (isset($request['type']) && $request['type'] === "Error") {
        file_put_contents($errorLogFile, $logEntry, FILE_APPEND);
    } else {
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

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
