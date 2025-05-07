#!/usr/bin/php
<?php

require_once("lib/rabbitMQLib.inc");
require_once("lib/installer.php");
require_once("lib/perform_scp.php");

$remote_hostname = "Deployment";
$remote_host_ip = "100.95.180.45";
$hostname = gethostname();
$local_path = "/home/$hostname/bundles_from_deployment_server/";

function copy_config_files($config_location, $destination_location) {
    if (!is_dir($config_location)) {
        echo "Config location does not exist: $config_location\n";
        return false;
    }

    $files = scandir($config_location);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $source = rtrim($config_location, '/') . '/' . $file;
        $destination = rtrim($destination_location, '/') . '/' . $file;
        if (!copy($source, $destination)) {
            echo "Failed to copy $file\n";
            return false;
        }
    }

    echo "Configuration files copied from $config_location to $destination_location\n";
    return true;
}

function requestProcessor($request) {
    global $remote_hostname, $remote_host_ip, $local_path;
 	print_r($request);    

    if (!is_dir($local_path)) {
        if (!mkdir($local_path, 0777, true)) {
            return ["error" => "Failed to create bundle directory: $local_path"];
        }
    }
    if (!isset($remote_hostname) || !isset($remote_host_ip) || !isset($local_path)) {
        return ["status" => "error", "message" => "Server configuration error in InstallerServer.php"];
    }

    // print_r($request);
    if (!isset($request['type']) || $request['type'] !== "INSTALL_BUNDLE") {
        return ["status" => "error", "message" => "Invalid request type"];
    }
    $payload = $request['payload'];

    $requiredKeys = ['remote_bundle_path'];
    foreach ($requiredKeys as $key) {
        if (empty($payload[$key])) {
            return ["status" => "error", "message" => "Missing required field: $key"];
        }
    }

    $scpResult = scpBundleFromRemote(
        $remote_hostname,
        $remote_host_ip,
        $payload['remote_bundle_path'],
        $local_path,
    );

    if ($scpResult['status'] !== 'success') {
        return $scpResult;
    }

    $full_path_for_bundle = $local_path . basename($payload['remote_bundle_path']);
    $installResult = installBundle($full_path_for_bundle, "it490");
    
    //Remove the folder after installation regardless of success or failure
    if (is_dir($local_path)) {
        array_map('unlink', glob("$local_path/*.*"));
        rmdir($local_path);
    }
    
   if (isset($installResult['status']) && $installResult['status'] === 'success') {
    $configCopied = copy_config_files("configFiles", "/home/QA-DB/The-Hats-IT490/Database");

    if ($configCopied) {
        $installResult['messages'][] = "Configuration files successfully copied.";
    } else {
        $installResult['messages'][] = "Failed to copy configuration files.";
    }
}

return $installResult;

}


$server = new rabbitMQServer("QAInstallRabbitMQ.ini","QADBInstallListener");
echo "Installer Server Running...\n";
$server->process_requests('requestProcessor');
echo "Installer Server Stopped..\n";
?>