<?php

function installBundle($bundleZip, $sudoPassword = '') {
    $bundleZip = realpath($bundleZip);
     if (!file_exists($bundleZip) || pathinfo($bundleZip, PATHINFO_EXTENSION) !== 'zip') {
    return ["error" => "Invalid or non-zip bundle path"];//sanitize path file
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
    if (!$config){//sanitation added if file does not exist
        return ["error"=>"Failed to parse bundle.ini"];
    }
    //$results = [];

    $user = getenv('SUDO_USER') ?: getenv('USER');
    if (!preg_match('/^[\w.-]+$/',$user)){
        return["error"=>"Username contains unsafe characters"];
    }//sanitization for any unsafe username input
    $vars = ['USER'=>$user];

    $replacePlaceholders = function ($cmd) use ($vars) {
        foreach ($vars as $key => $val) {
            $cmd = str_replace('$' . $key, $val, $cmd);
        }
        return $cmd;
    };

    if (isset($config['commands']['execute'])) {
        $cmds = is_array($config['commands']['execute']) ? $config['commands']['execute'] : [$config['commands']['execute']];
        foreach ($cmds as $cmd) {
            $cmd = $replacePlaceholders($cmd);
            exec($cmd, $out, $code);
            if ($code !== 0) {
                exec("rm -rf " . escapeshellarg($tmpDir));
                return ["error" => "Failed to execute command: $cmd"];
            }
            $results[] = "Executed: $cmd (exit code: $code)";
        }
    }

    if (isset($config['commands']['sudo'])) {
        $sudoCmds = is_array($config['commands']['sudo']) ? $config['commands']['sudo'] : [$config['commands']['sudo']];
        foreach ($sudoCmds as $cmd) {
            $cmd = $replacePlaceholders($cmd);
            $safeCmd = "echo " . escapeshellarg($sudoPassword) . " | sudo -S bash -c " . escapeshellarg($cmd);
            exec($safeCmd, $out, $code);
            if ($code !== 0) {
                exec("rm -rf " . escapeshellarg($tmpDir));
                return ["error" => "Failed to execute sudo command: $cmd"];
            }
            $results[] = "SUDO Executed: $cmd (exit code: $code)";
        }
    }




    if (isset($config['processes']['bounce'])) {
        $procs = is_array($config['processes']['bounce']) ? $config['processes']['bounce'] : [$config['processes']['bounce']];
        foreach ($procs as $proc) {
            $proc = preg_replace('/[^a-zA-Z0-9_.@-]/', '', $proc);//sanitize proc input
            $bounceCmd = "echo " . escapeshellarg($sudoPassword) . " | sudo -S systemctl restart " . escapeshellarg($proc);
            exec($bounceCmd, $out, $code);
            if ($code !== 0) {
                exec("rm -rf " . escapeshellarg($tmpDir));
                return ["error" => "Failed to restart process: $proc"];
            }
            $results[] = "Restarted process: $proc (exit code: $code)";
        }
    }

    if (isset($config['bundle']['version'])) {
        $bundle_name = $config['bundle']['name'];
        $version = $config['bundle']['version'];
        $hostname = gethostname();
        $versionDir = "/home/$hostname/installed_bundles_versions";
        $versionFile = "$versionDir/{$bundle_name}.txt";

        if (!is_dir($versionDir)) {
            $mkdirCmd = "mkdir -p " . escapeshellarg($versionDir);
            exec($mkdirCmd, $mkdirOut, $mkdirCode);
            if ($mkdirCode !== 0) {
                exec("rm -rf " . escapeshellarg($tmpDir));
                return ["error" => "Failed to create installed_bundles directory"];
            }
        }

        $writeCmd = "echo 'Current Version: $bundle_name $version' > " . escapeshellarg($versionFile);
        exec($writeCmd, $out, $code);
        if ($code !== 0) {
            exec("rm -rf " . escapeshellarg($tmpDir));
            return ["error" => "Failed to save version"];
        }

        $results[] = "Saved version to $versionFile: $bundle_name $version";
    }

    chdir("/home");
    exec("rm -rf " . escapeshellarg($tmpDir));
    $results[] = "Cleaned up temp folder: $tmpDir";

    return ["status" => "success", "messages" => $results];
}

print_r(installBundle("/home/QA-DB/Desktop/File/zipper.zip", "it490"));
?>
