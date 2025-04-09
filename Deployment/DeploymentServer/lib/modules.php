<?php
// lib/modules.php

function getClusterInfo($env) {
    $configPath = __DIR__ . "/Clusters.ini";
    if (!file_exists($configPath)) {
        throw new Exception("Clusters.ini not found at $configPath");
    }

    $config = loadNestedConfig($configPath);

    if (!isset($config[$env])) {
        throw new Exception("Unknown cluster named \"{$env}\"\nCluster name must be one defined in Clusters.ini\n");
    }

    return $config[$env]; 
}

function loadNestedConfig($filePath) {
    $raw = parse_ini_file($filePath, true); 
    $config = [];

    foreach ($raw as $env => $values) {
        foreach ($values as $key => $value) {
            $parts = explode('.', $key);
            if (count($parts) === 2) {
                [$category, $subkey] = $parts;
                $config[$env][$category][$subkey] = $value;
            } else {
                $config[$env][$key] = $value;
            }
        }
    }

    return $config;
}
?>
