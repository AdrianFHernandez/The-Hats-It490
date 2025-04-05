#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");
require_once(__DIR__ . "/lib/modules.php");

$mysqli = new mysqli('localhost', 'testUser', '12345', 'deployment');
$deployServerArchiveLocation = "/home/Deployment/deploy_archive/";
function buildRequest($type, $payload = []) {
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}

function buildResponse($type, $status, $payload = []) {
    return [
        "type" => $type . "_RESPONSE",
        "timestamp" => time(),
        "status" => $status,
        "payload" => $payload
    ];
}

function getNextVersion($bundleName) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT COALESCE(MAX(version), 0) + 1 AS next_version FROM Bundles WHERE bundle_name = ?");
    $stmt->bind_param("s", $bundleName);
    $stmt->execute();
    $stmt->bind_result($nextVersion);
    $stmt->fetch();
    $stmt->close();
    return $nextVersion;
}

function markStatus($payload) {
    global $mysqli;
    $bundleName = $payload['bundle_name'];
    $version = $payload['version'];
    $status = strtoupper($payload['status']);
    if (!in_array($status, ['NEW', 'PASSED', 'FAILED'])) {
        return buildResponse("MARK_STATUS", "ERROR", ["message" => "Invalid status"]);
    }
    $stmt = $mysqli->prepare("UPDATE Bundles SET status = ? WHERE bundle_name = ? AND version = ?");
    $stmt->bind_param("ssi", $status, $bundleName, $version);
    $stmt->execute();
    $rows = $stmt->affected_rows;
    $stmt->close();
    if ($rows === 0) {
        return buildResponse("MARK_STATUS", "ERROR", ["message" => "No matching bundle found"]);
    }
    return buildResponse("MARK_STATUS", "SUCCESS", ["message" => "For the {$bundleName}_v{$version}, the status has been updated to {$status}"]);
}

function addNewBundle($bundleInfo) {
    global $mysqli, $deployServerArchiveLocation;

    $cluster = getClusterInfo("dev"); 
    $hostType = $bundleInfo["host_type"];
    $remoteHost = $cluster[$hostType]['hostname'] . "@" . $cluster[$hostType]['ip'];
    $password = isset($cluster[$hostType]['password']) ? $cluster[$hostType]['password'] : '@Dinohunter58';

    $bundle_location = $bundleInfo["file_path"];
    $filename = basename($bundle_location);
    $bundlePath = $deployServerArchiveLocation . $filename;

    $cmd = "sshpass -p {$password} scp  {$remoteHost}:{$bundle_location} $bundlePath";
    exec($cmd, $output, $code);

    if ($code !== 0) {
        // Log the error
        logToQueue([
            'timestamp' => date('c'),
            'log_type' => 'ERROR',
            'message' => "SCP failed: " . implode("\n", $output),
            'source' => 'Deployment Server',
            'bundle_name' => $bundleInfo['bundle_name'],
            'version' => $bundleInfo['version']
        ]);
        return buildResponse("ADD_NEW_BUNDLE", "ERROR", ["message" => "SCP failed: " . implode("\n", $output)]);
    }

    // Log the success
    logToQueue([
        'timestamp' => date('c'),
        'log_type' => 'INFO',
        'message' => "Successfully deployed {$filename} to the server.",
        'source' => 'Deployment Server',
        'bundle_name' => $bundleInfo['bundle_name'],
        'version' => $bundleInfo['version']
    ]);

    // Continue with adding the bundle to the database...
    $stmt = $mysqli->prepare("INSERT INTO Bundles (bundle_name, version, status, file_path) VALUES (?, ?, 'NEW', ?)");
    $stmt->bind_param("sis", $bundleInfo['bundle_name'], $bundleInfo['version'], $bundlePath);
    $stmt->execute();
    $stmt->close();

    return buildResponse("ADD_NEW_BUNDLE", "SUCCESS", ["result" => true, "message"=> "Successfully deployed {$filename} to the server."]);
}



function listBundles() {
    global $mysqli;
    $result = $mysqli->query("SELECT DISTINCT bundle_name FROM Bundles");
    $bundles = [];
    while ($row = $result->fetch_assoc()) {
        $bundles[] = $row['bundle_name'];
    }
    return buildResponse("LIST_BUNDLES", "SUCCESS", ["bundles" => $bundles]);
}

function listBundleVersions($bundleName) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT version, status FROM Bundles WHERE bundle_name = ? ORDER BY version ASC");
    $stmt->bind_param("s", $bundleName);
    $stmt->execute();
    $result = $stmt->get_result();
    $versions = [];
    while ($row = $result->fetch_assoc()) {
        $versions[] = $row;
    }
    $stmt->close();
    return buildResponse("LIST_BUNDLE_VERSIONS", "SUCCESS", ["versions" => $versions]);
}

function logToQueue($logData) {
    // Validate the input before doing anything
    if (!isset($logData['level']) || !isset($logData['message'])) {
        return [
            "status" => "error",
            "message" => "Missing required log fields: 'level' and/or 'message'."
        ];
    }

    try {
        $connection = new AMQPStreamConnection('100.96.178.79', 5672, 'hats', 'it490@123', 'hatshost');
        $channel = $connection->channel();
        $channel->exchange_declare('log_broadcast', 'fanout', false, true, false);
        $logMessage = new AMQPMessage(json_encode($logData), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        $channel->basic_publish($logMessage, 'log_broadcast');

        $channel->queue_declare('log_queue', false, true, false, false);
        $channel->basic_publish($logMessage, '', 'log_queue');

        $channel->close();
        $connection->close();

        return [
            "status" => "success",
            "message" => "Log sent to queue and exchange."
        ];
    } catch (Exception $e) {
        return [
            "status" => "error",
            "message" => "Failed to log: " . $e->getMessage()
        ];
    }
}

function requestProcessor($request) {
    if (!isset($request['type'])) {
        return buildResponse("UNKNOWN", "ERROR", ["message" => "Missing type"]);
    }


    switch ($request['type']) {
        case "GET_VERSION":
            return buildResponse("GET_VERSION", "SUCCESS", [
                "version" => getNextVersion($request['payload']['bundle_name'])
            ]);
        case "ADD_NEW_BUNDLE":
            return addNewBundle($request['payload']);
        case "MARK_STATUS":
            return markStatus($request['payload']);
        case "LIST_BUNDLES":
            return listBundles();
        case "LIST_BUNDLE_VERSIONS":
            return listBundleVersions($request['payload']['bundle_name']);
        case "ROLLBACK_TO_VERSION":
            return buildResponse("ROLLBACK_TO_VERSION", "NOT_IMPLEMENTED", []);
        case "logToQueue":
            return logToQueue($request['payload']);
            
    }

    return buildResponse($request['type'], "UNKNOWN", ["message" => "Unhandled type"]);
}

echo "Deployment Server Started\n";
$server = new rabbitMQServer("DeploymentRabbitMQ.ini", "DeploymentServer");
$server->process_requests('requestProcessor');
echo "Deployment Server Ended\n";
?>
