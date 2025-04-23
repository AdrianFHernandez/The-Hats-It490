#!/usr/bin/php
<?php

if ($argc !== 2) {
    echo "Usage: install_bundle.php <path_to_bundle_zip>\n";
    exit(1);
}

$bundleZip = $argv[1];
if (!file_exists($bundleZip)) {
    echo "Bundle not found: $bundleZip\n";
    exit(1);
}

// Define the sudo password
$password = "it490";

$tmpDir = "/tmp/bundle_install_" . uniqid();
mkdir($tmpDir, 0777, true);

$unzipCmd = "unzip -q " . escapeshellarg($bundleZip) . " -d " . escapeshellarg($tmpDir);
exec($unzipCmd, $out, $code);
if ($code !== 0) {
    echo "Failed to unzip bundle\n";
    exec("rm -rf " . escapeshellarg($tmpDir));
    exit(1);
}

$iniPath = $tmpDir . "/bundle.ini";
chdir($tmpDir);
if (!file_exists($iniPath)) {
    echo "bundle.ini not found in bundle\n";
    exec("rm -rf " . escapeshellarg($tmpDir));
    exit(1);
}

$config = parse_ini_file($iniPath, true);

// Run commands
if (isset($config['commands']['execute'])) {
    $cmds = $config['commands']['execute'];
    if (!is_array($cmds)) $cmds = [$cmds];
    foreach ($cmds as $cmd) {
        exec($cmd, $out, $code);
        echo "Executed: $cmd (exit code: $code)\n";
    }
}
if (isset($config['commands']['sudo'])) {
    $sudoCmds = $config['commands']['sudo'];
    if (!is_array($sudoCmds)) $sudoCmds = [$sudoCmds];
    foreach ($sudoCmds as $cmd) {
        $safeCmd = "echo " . escapeshellarg($password) . " | sudo -S bash -c " . escapeshellarg($cmd);
        exec($safeCmd, $out, $code);
        echo "SUDO Executed: $cmd (exit code: $code)\n";
    }
}
// Bounce processes
if (isset($config['processes']['bounce'])) {
    $procs = $config['processes']['bounce'];
    if (!is_array($procs)) $procs = [$procs];
    foreach ($procs as $proc) {
        $bounceCmd = "echo " . escapeshellarg($password) . " | sudo -S systemctl restart " . escapeshellarg($proc);
        exec($bounceCmd, $out, $code);
        echo "Restarted process: $proc (exit code: $code)\n";
    }
}

echo "Bundle installation complete.\n";
exec("rm -rf " . escapeshellarg($tmpDir));
echo "Cleaned up temp folder: $tmpDir\n";
?>
