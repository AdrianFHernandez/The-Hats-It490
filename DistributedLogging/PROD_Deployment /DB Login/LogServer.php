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
    // Base log file paths
    $logDir = "/home/Deployment/DistributedLogin/";
    $logFile = $logDir . "proddistributed_db_login.log";
    $errorLogFile = $logDir . "errorproddistributed_db_login.log";

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

echo "Prod Login Server Started\n";
$server = new rabbitMQServer("PRODDistributedLogginRabbitMQ.ini", "PRODistributedLogginServer");
$server->process_requests('requestProcessor');
echo "Login Server Ended\n";
?>
