#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");
require_once("QA/lib/installer.php");
require_once("QA/lib/perform_scp.php");

function requestProcessor($request) {
    print_r($request);
    if (!isset($request['type']) || $request['type'] !== "INSTALL_BUNDLE") {
        return ["status" => "error", "message" => "Invalid request type"];
    }
    $payload = $request['payload'];

    $requiredKeys = ['host_user', 'host_ip', 'host_password', 'remote_path', 'local_path', 'sudo_password'];
    foreach ($requiredKeys as $key) {
        if (empty($payload[$key])) {
            return ["status" => "error", "message" => "Missing required field: $key"];
        }
    }

    $scpResult = scpBundleFromRemote(
        $payload['host_user'],
        $payload['host_ip'],
        $payload['host_password'],
        $payload['remote_path'],
        $payload['local_path']
    );

    if ($scpResult['status'] !== 'success') {
        return $scpResult;
    }

    $installResult = installBundle($payload['local_path'], $payload['sudo_password']);
    return $installResult;

}

$server = new rabbitMQServer("QAInstallRabbitMQ.ini","QADBInstallListener");
echo "Installer Server Running...\n";
$server->process_requests('requestProcessor');
echo "Installer Server Stopped..\n";

?>
