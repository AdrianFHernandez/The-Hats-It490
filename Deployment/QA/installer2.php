#!/usr/bin/php
<?php
/**
 * Secure Bundle Installer
 * 
 * This script installs software bundles packaged as ZIP files with improved security
 * and error handling compared to the original implementation.
 * 
 * Usage: php install_bundle.php <path_to_bundle_zip> [sudo_password]
 */

// Define constants
define('TEMP_DIR_PREFIX', '/tmp/bundle_install_');
define('TEMP_DIR_PERMISSIONS', 0750); // More restrictive permissions
define('MAX_CMD_LENGTH', 255);        // Limit command length for basic validation
define('LOG_FILE', '/var/log/bundle_install.log'); // Simple logging

/**
 * Main function to handle bundle installation
 */
function installBundle($bundlePath, $password = null) {
    // Create a log entry
    logMessage("Starting installation of bundle: $bundlePath");
    
    // Create temporary directory
    $tmpDir = createTempDirectory();
    if (!$tmpDir) {
        die("Failed to create temporary directory\n");
    }
    
    // Extract bundle
    if (!extractBundle($bundlePath, $tmpDir)) {
        cleanupAndExit($tmpDir, "Failed to extract bundle");
    }
    
    // Parse configuration
    $iniPath = $tmpDir . "/bundle.ini";
    if (!file_exists($iniPath)) {
        cleanupAndExit($tmpDir, "bundle.ini not found in bundle");
    }
    
    // Validate bundle structure
    if (!validateBundleStructure($tmpDir)) {
        cleanupAndExit($tmpDir, "Invalid bundle structure");
    }
    
    // Parse configuration
    $config = parse_ini_file($iniPath, true);
    if ($config === false) {
        cleanupAndExit($tmpDir, "Failed to parse bundle.ini");
    }
    
    // Change to the temporary directory
    chdir($tmpDir);
    
    // Execute regular commands
    executeRegularCommands($config);
    
    // Execute sudo commands if password is provided
    if ($password !== null) {
        executeSudoCommands($config, $password);
        bounceProcesses($config, $password);
    } else {
        echo "WARNING: No sudo password provided. Skipping privileged operations.\n";
        logMessage("WARNING: Skipped privileged operations - no password provided");
    }
    
    // Clean up
    cleanupAndExit($tmpDir, "Bundle installation complete", false);
}

/**
 * Creates a temporary directory with secure permissions
 */
function createTempDirectory() {
    $tmpDir = TEMP_DIR_PREFIX . uniqid();
    if (!mkdir($tmpDir, TEMP_DIR_PERMISSIONS, true)) {
        logMessage("ERROR: Failed to create temporary directory");
        return false;
    }
    echo "Created temporary directory: $tmpDir\n";
    return $tmpDir;
}

/**
 * Extracts the bundle to the temporary directory
 */
function extractBundle($bundlePath, $tmpDir) {
    $unzipCmd = "unzip -q " . escapeshellarg($bundlePath) . " -d " . escapeshellarg($tmpDir);
    exec($unzipCmd, $output, $code);
    
    if ($code !== 0) {
        logMessage("ERROR: Failed to extract bundle: $bundlePath (exit code: $code)");
        return false;
    }
    
    echo "Extracted bundle to: $tmpDir\n";
    return true;
}

/**
 * Validates the bundle structure
 */
function validateBundleStructure($tmpDir) {
    // Check for mandatory files
    if (!file_exists("$tmpDir/bundle.ini")) {
        logMessage("ERROR: Missing required file: bundle.ini");
        return false;
    }
    
    // Check for potentially malicious files (basic example)
    $suspiciousFiles = glob("$tmpDir/*.{php,sh,bash}", GLOB_BRACE);
    foreach ($suspiciousFiles as $file) {
        // Simple validation - check file content for suspicious patterns
        $content = file_get_contents($file);
        if (preg_match('/(eval|base64_decode|system|passthru)/', $content)) {
            logMessage("WARNING: Potentially malicious code found in file: " . basename($file));
            echo "WARNING: Potentially malicious code detected in: " . basename($file) . "\n";
        }
    }
    
    return true;
}

/**
 * Executes regular commands from the configuration
 */
function executeRegularCommands($config) {
    if (!isset($config['commands']['execute'])) {
        echo "No regular commands to execute\n";
        return;
    }
    
    $cmds = $config['commands']['execute'];
    if (!is_array($cmds)) $cmds = [$cmds];
    
    foreach ($cmds as $cmd) {
        if (!validateCommand($cmd)) {
            logMessage("WARNING: Skipped potentially unsafe command: $cmd");
            echo "WARNING: Skipped potentially unsafe command: $cmd\n";
            continue;
        }
        
        echo "Executing: $cmd\n";
        exec($cmd, $output, $code);
        
        if ($code !== 0) {
            echo "WARNING: Command exited with non-zero status: $code\n";
            logMessage("WARNING: Command failed: $cmd (exit code: $code)");
        } else {
            echo "Successfully executed command (exit code: $code)\n";
        }
    }
}

/**
 * Executes sudo commands from the configuration
 */
function executeSudoCommands($config, $password) {
    if (!isset($config['commands']['sudo'])) {
        echo "No sudo commands to execute\n";
        return;
    }
    
    $cmds = $config['commands']['sudo'];
    if (!is_array($cmds)) $cmds = [$cmds];
    
    foreach ($cmds as $cmd) {
        if (!validateCommand($cmd)) {
            logMessage("WARNING: Skipped potentially unsafe sudo command: $cmd");
            echo "WARNING: Skipped potentially unsafe sudo command: $cmd\n";
            continue;
        }
        
        echo "Executing with sudo: $cmd\n";
        $safeCmd = "echo " . escapeshellarg($password) . " | sudo -S bash -c " . escapeshellarg($cmd) . " 2>/dev/null";
        exec($safeCmd, $output, $code);
        
        if ($code !== 0) {
            echo "WARNING: Sudo command exited with non-zero status: $code\n";
            logMessage("WARNING: Sudo command failed: $cmd (exit code: $code)");
        } else {
            echo "Successfully executed sudo command (exit code: $code)\n";
        }
    }
}

/**
 * Restarts system services
 */
function bounceProcesses($config, $password) {
    if (!isset($config['processes']['bounce'])) {
        echo "No processes to restart\n";
        return;
    }
    
    $procs = $config['processes']['bounce'];
    if (!is_array($procs)) $procs = [$procs];
    
    foreach ($procs as $proc) {
        if (!validateServiceName($proc)) {
            logMessage("WARNING: Invalid service name: $proc");
            echo "WARNING: Invalid service name: $proc\n";
            continue;
        }
        
        echo "Restarting service: $proc\n";
        $bounceCmd = "echo " . escapeshellarg($password) . " | sudo -S systemctl restart " . escapeshellarg($proc) . " 2>/dev/null";
        exec($bounceCmd, $output, $code);
        
        if ($code !== 0) {
            echo "WARNING: Failed to restart service: $proc (exit code: $code)\n";
            logMessage("WARNING: Service restart failed: $proc (exit code: $code)");
        } else {
            // Verify service is actually running
            $statusCmd = "echo " . escapeshellarg($password) . " | sudo -S systemctl is-active " . escapeshellarg($proc) . " 2>/dev/null";
            exec($statusCmd, $statusOutput, $statusCode);
            
            if ($statusCode !== 0) {
                echo "WARNING: Service may not be running: $proc\n";
                logMessage("WARNING: Service may be inactive after restart: $proc");
            } else {
                echo "Successfully restarted service: $proc\n";
            }
        }
    }
}

/**
 * Basic command validation
 */
function validateCommand($cmd) {
    // Check for command length
    if (strlen($cmd) > MAX_CMD_LENGTH) {
        return false;
    }
    
    // Check for potentially dangerous commands
    $dangerousPatterns = [
        '/rm\s+(-[rf]+\s+)?\//', // Removing root files
        '/mkfs/',                // Formatting disks
        '/>?\s*\/dev\/sd[a-z]/', // Writing to disk devices
        '/dd\s+if=.*of=\/dev/'   // Writing to devices with dd
    ];
    
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $cmd)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Validate service name
 */
function validateServiceName($service) {
    // Only allow alphanumeric characters, dots, dashes and underscores
    return preg_match('/^[a-zA-Z0-9\._\-]+$/', $service);
}

/**
 * Log message to file
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    // Try to write to log file, fallback to stdout if not possible
    if (!@file_put_contents(LOG_FILE, $logEntry, FILE_APPEND)) {
        // If we can't write to the log file, output to stderr
        fwrite(STDERR, "NOTE: Couldn't write to log file. Log message: $message\n");
    }
}

/**
 * Clean up and exit
 */
function cleanupAndExit($tmpDir, $message, $isError = true) {
    echo $message . "\n";
    logMessage($isError ? "ERROR: $message" : $message);
    
    // Clean up temporary directory
    exec("rm -rf " . escapeshellarg($tmpDir));
    echo "Cleaned up temporary directory: $tmpDir\n";
    
    exit($isError ? 1 : 0);
}

// Main script execution
try {
    // Check if the correct number of arguments is provided
    if ($argc < 2 || $argc > 3) {
        echo "Usage: php install_bundle.php <path_to_bundle_zip> [sudo_password]\n";
        echo "Note: If sudo_password is not provided, privileged operations will be skipped.\n";
        exit(1);
    }
    
    $bundlePath = $argv[1];
    
    // Check if the bundle exists
    if (!file_exists($bundlePath)) {
        echo "Bundle not found: $bundlePath\n";
        exit(1);
    }
    
    // Get password if provided
    $password = ($argc == 3) ? $argv[2] : null;
    
    // Install the bundle
    installBundle($bundlePath, $password);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    logMessage("EXCEPTION: " . $e->getMessage());
    exit(1);
}
?>