<?php
require_once("lib/modules.php");
require_once("rabbitMQLib.inc");

$db = new mysqli("localhost", "testUser", "12345", "deployment");

if ($db->connect_error) {
    die("DB connection failed: " . $db->connect_error);
}

function deployBundle($env, $bundleName, $version)
{
    global $db;

    $stmt = $db->prepare("SELECT bundle_type, file_path, status FROM Bundles WHERE bundle_name = ? AND version = ?");
    $stmt->bind_param("si", $bundleName, $version);
    $stmt->execute();
    $stmt->bind_result($bundleType, $filePath, $status);

    if (!$stmt->fetch()) {
        return ["status" => "FAIL", "payload" => ["message" => "Bundle/version not found"]];
    }
    $stmt->close();

    $allowed = match (strtolower($env)) {
        "prod" => ["PASSED"],
        "qa"   => ["NEW", "PASSED"],
        default => []
    };

    if (!in_array($status, $allowed)) {
        return ["status" => "FAIL", "payload" => ["message" => "Version $version is $status — not allowed in $env"]];
    }

    $cluster = getClusterInfo($env);
    $bundleType = strtolower($bundleType);
    if (!isset($cluster[$bundleType])) {
        return ["status" => "FAIL", "payload" => ["message" => "No host config for $bundleType in $env"]];
    }

    // Send only what’s needed
    $request = [
        "type" => "INSTALL_BUNDLE",
        "payload" => [
            "remote_bundle_path" => $filePath, 
        ]
    ];

    $configFile = ($env === "prod") ? "ProdInstallRabbitMQ.ini" : "QAInstallRabbitMQ.ini";
    $listener = strtoupper($env) . strtoupper($bundleType) . "InstallListener";

    file_put_contents("passing.txt", $listener); // Debug log
    $client = new rabbitMQClient($configFile, $listener);
    $response = $client->send_request($request);

    return ["status" => "SUCCESS", "payload" => $response];
}



function markStatusInDeployment($bundleName, $version, $status) {
    global $db;

    if (!in_array($status, ['PASSED', 'FAILED'])) {
        return ["status" => "FAIL", "payload" => ["message" => "Invalid status"]];
    }
    $stmt = $db->prepare("UPDATE Bundles SET status = ? WHERE bundle_name = ? AND version = ?");
    $stmt->bind_param("ssi", $status, $bundleName, $version);
    $stmt->execute();
    $rows = $stmt->affected_rows;
    $stmt->close();
    if ($rows === 0) {
        return ["status" => "FAIL", "payload" => ["message" => "No matching bundle found"]];
    }
    return ["status" => "SUCCESS", "payload" => ["message" => "For the {$bundleName}_v{$version}, the status has been updated to {$status}"]];
}
function rollbackToVersion($bundleName, $version, $env)
{
    global $db;
    $env = strtolower($env);
    if (empty($version)) {
        // Get the most recent PASSED version
        $stmt = $db->prepare("SELECT version FROM Bundles WHERE bundle_name = ? AND status = 'PASSED' ORDER BY version DESC LIMIT 1");
        $stmt->bind_param("s", $bundleName);
        $stmt->execute();
        $stmt->bind_result($version);
        if (!$stmt->fetch()) {
            return ["status" => "FAIL", "payload" => ["message" => "No PASSED version available for rollback"]];
        }
        $stmt->close();
    } else {
        // Verify the specified version is PASSED
        $stmt = $db->prepare("SELECT status FROM Bundles WHERE bundle_name = ? AND version = ?");
        $stmt->bind_param("si", $bundleName, $version);
        $stmt->execute();
        $stmt->bind_result($status);
        if (!$stmt->fetch()) {
            return ["status" => "FAIL", "payload" => ["message" => "Specified version not found"]];
        }
        $stmt->close();

        if ($status !== "PASSED") {
            return ["status" => "FAIL", "payload" => ["message" => "Version $version is $status — can only roll back to a PASSED version"]];
        }
    }

    
    return deployBundle($env, $bundleName, $version);
}


function listBundleNames()
{
    global $db;
    $res = $db->query("SELECT DISTINCT bundle_name FROM Bundles WHERE status IN ('NEW', 'PASSED')");
    $bundles = [];

    while ($row = $res->fetch_assoc()) {
        $bundles[] = $row["bundle_name"];
    }

    return ["status" => "SUCCESS", "payload" => ["bundles" => $bundles]];
}

function listVersionsForBundle($bundleName)
{
    global $db;
    $stmt = $db->prepare("SELECT version, status FROM Bundles WHERE bundle_name = ? ORDER BY version DESC");
    $stmt->bind_param("s", $bundleName);
    $stmt->execute();
    $stmt->bind_result($version, $status);

    $versions = [];
    while ($stmt->fetch()) {
        $versions[] = ["version" => $version, "status" => $status];
    }

    return ["status" => "SUCCESS", "payload" => ["versions" => $versions]];
}
