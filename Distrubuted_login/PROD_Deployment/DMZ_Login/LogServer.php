#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");

    $logDir = "/var/log/DistributedLogging/";
    $logFile = $logDir . "DistributedInvestZero.log";
    $logError = $logDir . "DistributedInvestZero.err";

function requestProcessor($request) {
    // Base log file paths
   print_r($request["message"]); 
	global $logFile, $logError;
    $timestamp = date("D M d H:i:s Y");
    $logEntry = "[$timestamp] " . " -- DMZ from PROD-- " . $request["message"] . "\n";

    // Determine where to log
    if (isset($request['type']) && $request['type'] === "Error") {
        file_put_contents($logError, $logEntry, FILE_APPEND);
    } else {
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

echo "DEV Login Server Started\n";
$server = new rabbitMQServer("PRODDistributedLogginRabbitMQ.ini", "PRODistributedLogginServer");
$server->process_requests('requestProcessor');
echo "Login Server Ended\n";
?>
