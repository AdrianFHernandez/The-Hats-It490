#!/usr/bin/php
<?php

require_once(__DIR__ . "/lib/rabbitMQLib.inc");
require_once(__DIR__ . "/lib/modules.php");
require_once(__DIR__ . "/lib/DeploymentLib.php");

$mysqli = new mysqli('localhost', 'testUser', '12345', 'deployment');
$deployServerArchiveLocation = "/home/QA-DB/deploy_archive/";
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
    if (!in_array($status, ['PASSED', 'FAILED'])) {
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
    $hostType = $bundleInfo["host_type"]; // 'web', 'db', or 'dmz'

    
    $remoteHost = $cluster[$hostType]['hostname'] . "@" . $cluster[$hostType]['ip'];

    $bundle_location = $bundleInfo["file_path"];
    $filename = basename($bundle_location);
    $bundlePath = $deployServerArchiveLocation . $filename;

    // Using key-based SCP
    $cmd = "scp -o StrictHostKeyChecking=no {$remoteHost}:{$bundle_location} {$bundlePath}";
    file_put_contents("passing.txt", $cmd);
    exec($cmd, $output, $code);

    if ($code !== 0) {
        return buildResponse("ADD_NEW_BUNDLE", "ERROR", [
            "message" => "SCP failed: " . implode("\n", $output)
        ]);
    }

    try {
        $stmt = $mysqli->prepare("INSERT INTO Bundles (bundle_name, version, bundle_type, status, file_path) VALUES (?, ?, ?, 'NEW', ?)");
        $stmt->bind_param("siss", $bundleInfo['bundle_name'], $bundleInfo['version'], $hostType, $bundlePath);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        return buildResponse('ADD_NEW_BUNDLE', 'FAILED', [
            "message" => "Failed to insert the entry into DB"
        ]);
    }

    return buildResponse("ADD_NEW_BUNDLE", "SUCCESS", [
        "result" => true,
        "message" => "Successfully deployed {$filename} to the server."
    ]);
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

function requestProcessor($request) {

    print_r(json_encode($request) . "\n");

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
            $res = rollbackToVersion(
                $request['payload']['bundle_name'],
                $request['payload']['version'], 
                $request['payload']['env'],
            );
            $status = $res['status'];
            $payload = $res['payload'];
            return buildResponse("ROLLBACK_TO_VERSION", $status, $payload);

    }

    return buildResponse($request['type'], "UNKNOWN", ["message" => "Unhandled type"]);
}

echo "Deployment Server Started\n";
$server = new rabbitMQServer(__DIR__ . "/lib/DeploymentRabbitMQ.ini", "DeploymentServer");
$server->process_requests('requestProcessor');
echo "Deployment Server Ended\n";
?>
