<?php

require_once("rabbitMQLib.inc");

function buildRequest($type, $payload = []) {
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}

function sendRequest($type, $payload = []) {
    $client = new rabbitMQClient("DeploymentRabbitMQ.ini", "DeploymentServer");
    return $client->send_request(buildRequest($type, $payload));
}

function getNextVersion($bundleName) {
    return sendRequest("GET_VERSION", ["bundle_name" => $bundleName]);
}


function build_ini_string(array $assoc_array): string {
    $content = '';
    foreach ($assoc_array as $section => $values) {
        $content .= "[$section]\n";
        foreach ($values as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $v) {
                    $content .= "{$key}[] = " . format_ini_value($v) . "\n";
                }
            } else {
                $content .= "$key = " . format_ini_value($val) . "\n";
            }
        }
        $content .= "\n";
    }
    return $content;
}

function format_ini_value($val) {
    return is_numeric($val) ? $val : '"' . addcslashes($val, '"') . '"';
}


function addNewBundle($bundleName, $hostType, $filePath, $version) {
    return sendRequest("ADD_NEW_BUNDLE", [
        "bundle_name" => $bundleName,
        "version" => $version,
        "host_type" => $hostType,
        "file_path" => $filePath
    ]);
}

function listBundleNames() {
    return sendRequest("LIST_BUNDLES");
}

function listVersionsForBundle($bundleName) {
    return sendRequest("LIST_BUNDLE_VERSIONS", ["bundle_name" => $bundleName]);
}

function rollbackToVersion($bundleName, $version, $env) {
    return sendRequest("ROLLBACK_TO_VERSION", [
        "bundle_name" => $bundleName,
        "version" => $version, 
        "env" => $env
    ]);
}

function markStatus($bundleName, $version, $status) {
    return sendRequest("MARK_STATUS", [
        "bundle_name" => $bundleName,
        "version" => $version,
        "status" => strtoupper($status)
    ]);
}


function createAndRegisterBundle($bundleName, $hostType, $sourceDir) {
    $validHostTypes = ['dmz', 'web', 'db'];
    if (!in_array($hostType, $validHostTypes)) {
        return ["error" => "Invalid host type: $hostType"];
    }

    $sourceDir = rtrim($sourceDir, '/');
    $bundleIniPath = $sourceDir . "/bundle.ini";

    $hostname = gethostname();
    $bundleDir = "/home/$hostname/TempBundles";

    if (!file_exists($sourceDir)) {
        return ["error" => "Source folder does not exist: $sourceDir"];
    }

    if (!file_exists($bundleIniPath)) {
        return ["error" => "Missing bundle.ini in source folder"];
    }

    if (!is_dir($bundleDir)) {
        if (!mkdir($bundleDir, 0777, true)) {
            return ["error" => "Failed to create bundle directory: $bundleDir"];
        }
    }

    $config = parse_ini_file($bundleIniPath, true);
    if (!isset($config['files']['include'])) {
        return ["error" => "bundle.ini must contain [files] with include[] entries"];
    }

    $filesToInclude = $config['files']['include'];
    if (!is_array($filesToInclude)) {
        $filesToInclude = [$filesToInclude]; // single entry case
    }

    // Check all files exist
    $missingFiles = [];
    foreach ($filesToInclude as $file) {
        $fullPath = $sourceDir . '/' . $file;
        if (!file_exists($fullPath)) {
            $missingFiles[] = $file;
        }
    }

    if (!empty($missingFiles)) {
        return ["error" => "Missing files: " . implode(', ', $missingFiles)];
    }

    $filesToInclude = array_map(function ($f) {
        return rtrim($f, '/');
    }, $filesToInclude);

    if (in_array('./', $filesToInclude)) {
        $filesToInclude = ['./']; 
    }

    $versionResponse = getNextVersion($bundleName);
    if ($versionResponse['status'] !== "SUCCESS") {
        return ["error" => "Failed to get version"];
    }

    $version = $versionResponse['payload']['version'];
    $zipName = "{$bundleName}_v{$version}.zip";
    $zipPath = $bundleDir . "/" . $zipName;

    // Save the version in the bundle.ini
    $config['bundle']['version'] = $version;

    // Save the updated INI file
    file_put_contents($bundleIniPath, build_ini_string($config));

    // Create the zip
    $tmpZipCmd = "cd $sourceDir && zip -r $zipPath " . " bundle.ini " . implode(" ", array_map('escapeshellarg', $filesToInclude));
    exec($tmpZipCmd, $output, $code);
    if ($code !== 0) {
        return ["error" => "Failed to create zip: " . implode("\n", $output)];
    }

    $registerResponse = addNewBundle($bundleName, $hostType, $zipPath, $version);
    return $registerResponse;
}

function createAndRegisterBundleFromIni($sourceDir) {
    $sourceDir = rtrim($sourceDir, '/');
    $bundleIniPath = $sourceDir . "/bundle.ini";

   
    if (!file_exists($bundleIniPath)) {
        return ["error" => "Missing bundle.ini in: $sourceDir"];
    }

    $config = parse_ini_file($bundleIniPath, true);

    if (!isset($config['bundle']['name']) || !isset($config['bundle']['host_type'])) {
        return ["error" => "[bundle] section must have 'name' and 'host_type'"];
    }

    $bundleName = $config['bundle']['name'];
    $hostType = strtolower($config['bundle']['host_type']);
    return createAndRegisterBundle($bundleName, $hostType, $sourceDir);
}
