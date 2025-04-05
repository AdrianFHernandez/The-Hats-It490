#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");
require_once("lib/modules.php");

function buildRequest($type, $payload = []) {
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}

// === CLI Args ===
if ($argc !== 4) {
    echo "Usage: create_and_register_bundle.php <bundle_name> <host_type> <source_folder>\n";
    exit(1);
}

$bundleName = $argv[1];
$hostType = strtolower($argv[2]);
$tempSource = rtrim($argv[3], '/');

// Validate host type
$validHostTypes = ['dmz', 'web', 'db'];
if (!in_array($hostType, $validHostTypes)) {
    echo "Invalid host_type: $hostType. Must be one of: " . implode(', ', $validHostTypes) . "\n";
    exit(1);
}

$bundleDir = "/home/Deployment/TempBundles/";

$client = new rabbitMQClient("DeploymentRabbitMQ.ini", "DeploymentServer");

// === Step 1: Get Next Version ===
$req = buildRequest("GET_VERSION", ["bundle_name" => $bundleName]);
$response = $client->send_request($req);
$version = $response['payload']['version'];

$zipName = "{$bundleName}_v{$version}.zip";
$zipPath = $bundleDir . $zipName;

// === Step 2: Create ZIP ===
if (!file_exists($tempSource)) {
    die("Source folder not found: $tempSource\n");
}

if (!is_dir($bundleDir)) {
    mkdir($bundleDir, 0777, true);
}

$cmd = "cd $tempSource && zip -r $zipPath .";
exec($cmd, $output, $code);
if ($code !== 0) {
    die("Failed to create bundle: " . implode("\n", $output) . "\n");
}

echo "Created bundle: $zipPath\n";

// === Step 3: Register with Deployment Server ===
$req = buildRequest("ADD_NEW_BUNDLE", [
    "bundle_name" => $bundleName,
    "version" => $version,
    "host_type" => $hostType,
    "file_path" => $zipPath
]);

$response = $client->send_request($req);

print_r($response);
?>
