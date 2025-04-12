#!/usr/bin/php
<?php

require_once("lib/rabbitMQLib.inc");
require_once("lib/installer.php");
require_once("lib/perform_scp.php");

$remote_hostname = "QA-DB";
$remote_host_ip = "100.76.155.76";
$hostname = gethostname();
$local_path = "/home/$hostname/bundles_from_deployment_server/";

function requestProcessor($request) {
    global $remote_hostname, $remote_host_ip, $local_path;
    

    if (!is_dir($local_path)) {
        if (!mkdir($local_path, 0777, true)) {
            return ["error" => "Failed to create bundle directory: $local_path"];
        }
    }
    
    if (!isset($remote_hostname) || !isset($remote_host_ip) || !isset($local_path)) {
        return ["status" => "error", "message" => "Server configuration error in InstallerServer.php"];
    }

    print_r($request);
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


    return $installResult;

}

$server = new rabbitMQServer("QAInstallRabbitMQ.ini","QAWEBInstallListener");
echo "Installer Server Running...\n";
$server->process_requests('requestProcessor');
echo "Installer Server Stopped..\n";

?>
