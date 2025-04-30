<?php

require_once("rabbitMQLib.inc");

function buildRequest($type, $payload = [])
{
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}

function sendRequest($type, $payload = [])
{
    $client = new rabbitMQClient("DeploymentRabbitMQ.ini", "DeploymentServer");
    return $client->send_request(buildRequest($type, $payload));
}

function getNextVersion($bundleName)
{
    return sendRequest("GET_VERSION", ["bundle_name" => $bundleName]);
}


function build_ini_string(array $assoc_array): string
{
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

function format_ini_value($val)
{
    return is_numeric($val) ? $val : '"' . addcslashes($val, '"') . '"';
}


function addNewBundle($bundleName, $hostType, $filePath, $version)
{
    return sendRequest("ADD_NEW_BUNDLE", [
        "bundle_name" => $bundleName,
        "version" => $version,
        "host_type" => $hostType,
        "file_path" => $filePath
    ]);
}

function listBundleNames()
{
    return sendRequest("LIST_BUNDLES");
}

function listVersionsForBundle($bundleName)
{
    return sendRequest("LIST_BUNDLE_VERSIONS", ["bundle_name" => $bundleName]);
}

function rollbackToVersion($bundleName, $version, $env)
{
    return sendRequest("ROLLBACK_TO_VERSION", [
        "bundle_name" => $bundleName,
        "version" => $version,
        "env" => $env
    ]);
}

function markStatus($bundleName, $version, $status)
{
    return sendRequest("MARK_STATUS", [
        "bundle_name" => $bundleName,
        "version" => $version,
        "status" => strtoupper($status)
    ]);
}

function zip_bundles($inputs, $zipName, $sudoPassword) {
    $zipPath = $zipName;
    $cmds = [];
    $skipped = [];

    foreach ($inputs as $input) {
        $realPath = realpath($input);
        if (!$realPath) {
            $skipped[] = $input;
            continue;
        }

        if (is_dir($realPath)) {
            $parentDir = dirname($realPath);
            $folderName = basename($realPath);
            $cmds[] = "echo " . escapeshellarg($sudoPassword) . " | sudo -S bash -c " .
                      escapeshellarg("(cd " . $parentDir . " && zip -r -q " . $zipPath . " " . $folderName . ")");
        } elseif (is_file($realPath)) {
            $parent = dirname($realPath);
            $basename = basename($realPath);
            $cmds[] = "echo " . escapeshellarg($sudoPassword) . " | sudo -S bash -c " .
                      escapeshellarg("(cd " . $parent . " && zip -q " . $zipPath . " " . $basename . ")");
        }
    }

    $finalCmd = implode(" && ", $cmds);
    $output = [];
    $exitCode = null;

    exec($finalCmd, $output, $exitCode);

    return $exitCode;
}


function createAndRegisterBundle($bundleName, $hostType, $sourceDir)
{
    $validHostTypes = ['dmz', 'web', 'db'];
    if (!in_array(strtolower($hostType), $validHostTypes)) {
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

    if (!is_dir($bundleDir) && !mkdir($bundleDir, 0777, true)) {
        return ["error" => "Failed to create bundle directory: $bundleDir"];
    }

    $config = parse_ini_file($bundleIniPath, true);
    if (!isset($config['files']['include'])) {
        return ["error" => "bundle.ini must contain [files] with include[] entries"];
    }

    $filesToInclude = $config['files']['include'];
    if (!is_array($filesToInclude)) {
        $filesToInclude = [$filesToInclude];
    }

    $missingFiles = [];
    $resolvedInputs = [];

    foreach ($filesToInclude as $file) {
        $file = rtrim($file, '/');
        $fullPath = ($file[0] === '~') ? str_replace('~', getenv('HOME'), $file) :
                    ((strpos($file, '/') === 0) ? $file : $sourceDir . '/' . $file);

        if (!$fullPath || !file_exists($fullPath)) {
            $missingFiles[] = $file;
        } else {
            $resolvedInputs[] = $fullPath;
        }
    }


    if (!empty($missingFiles)) {
        return ["error" => "Missing files: " . implode(', ', $missingFiles)];
    }

   
    foreach (['execute', 'sudo'] as $cmdType) {
        if (isset($config['commands'][$cmdType])) {
            $cmds = $config['commands'][$cmdType];
            $cmds = is_array($cmds) ? $cmds : [$cmds];
            $cleaned = [];

            foreach ($cmds as $cmd) {
                $result = validateAndSanitizeCommand($cmd, $cmdType);
                if (!$result['valid']) {
                    return ["error" => "Invalid {$cmdType} command: {$result['error']}"];
                }
                $cleaned[] = $result['command'];
            }

          
            $config['commands'][$cmdType] = $cleaned;
        }
    }

    
    $resolvedInputs[] = $bundleIniPath;

    $versionResponse = getNextVersion($bundleName);
    if ($versionResponse['status'] !== "SUCCESS") {
        return ["error" => "Failed to get version"];
    }

    $version = $versionResponse['payload']['version'];
    $zipName = "{$bundleName}_v{$version}.zip";
    $zipPath = $bundleDir . "/" . $zipName;

    
    $config['bundle']['version'] = $version;
    file_put_contents($bundleIniPath, build_ini_string($config));

    $exitCode = zip_bundles($resolvedInputs, $zipPath, "ubuntu");
    if ($exitCode !== 0) {
        return ["error" => "Failed to create zip bundle"];
    }
    // echo "Bundle created: $zipPath\n";
    return addNewBundle($bundleName, $hostType, $zipPath, $version);
}

function createAndRegisterBundleFromIni($sourceDir)
{
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


function validateAndSanitizeCommand(string $command, string $type): array {
     // Whitelist of allowed commands
     $allowedStarts = [
        'cp',
        'chmod',
        'mv',
    ];

    // Block dangerous patterns
    $forbidden = [';', '&&', '`', '|', '$('];

    $result = ['valid' => false, 'command' => null, 'error' => null];

    $normalized = preg_replace('/\s+/', ' ', trim($command));

    
    foreach ($forbidden as $bad) {
        if (strpos($normalized, $bad) !== false) {
            $result['error'] = "Command contains forbidden operator: '$bad'";
            return $result;
        }
    }

    
    if ($type === 'execute' && stripos($normalized, 'sudo') !== false) {
        $result['error'] = "Execute commands must not include 'sudo'";
        return $result;
    }

    // If it's a sudo command, strip leading sudo
    if ($type === 'sudo') {
        if (preg_match('/^sudo\s+(.*)$/i', $normalized, $matches)) {
            $normalized = $matches[1]; 
        }
    }

   

    foreach ($allowedStarts as $allowed) {
        if (stripos($normalized, $allowed) === 0) {
            $result['valid'] = true;
            $result['command'] = $normalized;
            return $result;
        }
    }

    $result['error'] = "Command not allowed: '$normalized'";
    return $result;
}


// print_r(createAndRegisterBundle("testBundle", "web", "./"));
?>
