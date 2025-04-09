<?php

function installBundle($bundleZip, $sudoPassword = '') {
    if (!file_exists($bundleZip)) {
        return ["error" => "Bundle not found: $bundleZip"];
    }

    $tmpDir = "/tmp/bundle_install_" . uniqid();
    mkdir($tmpDir, 0777, true);

    $unzipCmd = "unzip -q " . escapeshellarg($bundleZip) . " -d " . escapeshellarg($tmpDir);
    exec($unzipCmd, $out, $code);
    if ($code !== 0) {
        exec("rm -rf " . escapeshellarg($tmpDir));
        return ["error" => "Failed to unzip bundle"];
    }

    $iniPath = $tmpDir . "/bundle.ini";
    chdir($tmpDir);
    if (!file_exists($iniPath)) {
        exec("rm -rf " . escapeshellarg($tmpDir));
        return ["error" => "bundle.ini not found in bundle"];
    }

    $config = parse_ini_file($iniPath, true);
    $results = [];

    if (isset($config['bundle']['version'])) {
        $bundle_name = $config['bundle']['name'];
        $version = $config['bundle']['version'];
        $versionDir = "/home/installed_bundles";
        $versionFile = "$versionDir/{$bundle_name}.txt";
    
        // A directory to store the version info
        if (!is_dir($versionDir)) {
            $mkdirCmd = "sudo mkdir -p " . escapeshellarg($versionDir);
            exec($mkdirCmd, $mkdirOut, $mkdirCode);
            if ($mkdirCode !== 0) {
                exec("rm -rf " . escapeshellarg($tmpDir));
                return ["error" => "Failed to create installed_bundles directory"];
            }
        }
    
        // Write the version info to the file 
        $writeCmd = "echo 'Current Version: $bundle_name $version' | sudo tee " . escapeshellarg($versionFile) . " > /dev/null";
        exec($writeCmd, $out, $code);
        if ($code !== 0) {
            exec("rm -rf " . escapeshellarg($tmpDir));
            return ["error" => "Failed to save version"];
        }
    
        $results[] = "Saved version to $versionFile: $bundle_name $version";
    }
    

    if (isset($config['commands']['execute'])) {
        $cmds = is_array($config['commands']['execute']) ? $config['commands']['execute'] : [$config['commands']['execute']];
        foreach ($cmds as $cmd) {
            exec($cmd, $out, $code);
            $results[] = "Executed: $cmd (exit code: $code)";
        }
    }

    if (isset($config['commands']['sudo'])) {
        $sudoCmds = is_array($config['commands']['sudo']) ? $config['commands']['sudo'] : [$config['commands']['sudo']];
        foreach ($sudoCmds as $cmd) {
            $safeCmd = "echo " . escapeshellarg($sudoPassword) . " | sudo -S bash -c " . escapeshellarg($cmd);
            exec($safeCmd, $out, $code);
            $results[] = "SUDO Executed: $cmd (exit code: $code)";
        }
    }

    if (isset($config['processes']['bounce'])) {
        $procs = is_array($config['processes']['bounce']) ? $config['processes']['bounce'] : [$config['processes']['bounce']];
        foreach ($procs as $proc) {
            $bounceCmd = "echo " . escapeshellarg($sudoPassword) . " | sudo -S systemctl restart " . escapeshellarg($proc);
            exec($bounceCmd, $out, $code);
            $results[] = "Restarted process: $proc (exit code: $code)";
        }
    }
    chdir("/home");
    exec("rm -rf " . escapeshellarg($tmpDir));
    $results[] = "Cleaned up temp folder: $tmpDir";

    return ["status" => "success", "messages" => $results];
}
