<?php
function scpBundleFromRemote($hostUser, $hostIP, $remotePath, $localPath) {
    $remote = $hostUser . '@' . $hostIP;
    $scpCommand = "scp -o StrictHostKeyChecking=no "
                . escapeshellarg($remote . ':' . $remotePath) . ' '
                . escapeshellarg($localPath);

    exec($scpCommand, $output, $code);

    if ($code !== 0) {
        return [
            "status" => "error",
            "message" => "SCP failed with code $code",
            "details" => implode("\n", $output)
        ];
    }

    return [
        "status" => "success",
        "message" => "File copied successfully to $localPath"
    ];
}
?>
