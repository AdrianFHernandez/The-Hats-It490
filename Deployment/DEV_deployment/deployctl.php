#!/usr/bin/php
<?php

require_once(__DIR__ . "/lib/deploylib.php");
function prompt($label)
{
    echo "$label: ";
    return trim(fgets(STDIN));
}

function showResponse($res)
{
    if (isset($res['status']) && $res['status'] === "SUCCESS") {
        if (!empty($res['payload'])) {
            foreach ($res['payload'] as $key => $value) {
                if (is_array($value)) {
                    echo strtoupper($key) . ":\n";
                    foreach ($value as $entry) {
                        if (is_array($entry)) {
                            echo "  - " . json_encode($entry) . "\n";
                        } else {
                            echo "  - $entry\n";
                        }
                    }
                } else {
                    echo ucfirst($key) . ": $value\n";
                }
            }
        } else {
            echo "Success\n";
        }
    } else {
        $errorMsg = $res['payload']['message'] ?? $res["error"] ?? "Unknown error";
        echo "Error: $errorMsg\n";
    }
}

function menu()
{
    echo "\n--- Deployment Menu ---\n";
    echo "1. Deploy new bundle\n";
    echo "2. List all bundles\n";
    echo "3. List versions for a bundle\n";
    echo "4. Rollback a bundle\n";
    echo "5. Mark status of the bundle\n";
    echo "6. Exit\n";
    echo "Select option [1-6]: ";
    return trim(fgets(STDIN));
}

while (true) {
    $choice = menu();

    switch ($choice) {
        case "1":
            system('clear');
            sleep(0.5);
            $sourceDir = prompt("Enter path to bundle folder (must contain bundle.ini)");
            $res = createAndRegisterBundleFromIni($sourceDir);
            showResponse($res);
            break;

        case "2":
            system('clear');
            sleep(0.5);
            $res = listBundleNames();
            showResponse($res);
            break;

        case "3":
            system('clear');
            sleep(0.5);
            $bundleName = prompt("Enter bundle name");
            $res = listVersionsForBundle($bundleName);
            showResponse($res);
            break;

        case "4":

            system('clear');
            sleep(0.5);
            $bundleName = prompt("Enter bundle name");
            $version = prompt("Enter version to roll back to (Leave Empty for latest)");
            $env = prompt("Enter environment (qa/prod)");
        
            if (empty($version)) {
                $version = null;
            }
            if (empty($bundleName)) {
                echo "Bundle name cannot be empty.\n";
                break;
            }
            if (empty($env)) {
                echo "Environment cannot be empty.\n";
                break;
            }
            if (!in_array($env, ["qa", "prod"])) {
                echo "Environment must be either 'qa' or 'prod'.\n";
                break;
            }
            $env = strtolower($env);
            $res = rollbackToVersion($bundleName, $version, $env );
            showResponse($res);
            break;

        case "5":
            system("clear");
            sleep(0.5);
        
            $bundleName = prompt("Enter bundle name");
            $version = prompt("Enter version to be updated");
        
            $choice = prompt("Choose status: [1] FAILED, [2] PASSED");
            switch ($choice) {
                case "1":
                    $status = "FAILED";
                    break;
                case "2":
                    $status = "PASSED";
                    break;
                default:
                    $status = null;
                    echo "Invalid choice. Try Another Option\n";
                    break;
            }
            
            if ($status === null) {
            break;
            }
            $res = markStatus($bundleName, $version, $status);
            showResponse($res);
            break;
        case "6":
            echo "Exiting...\n";
            exit(0);

        default:
            echo "Invalid option. Try again.\n";
            sleep(1.5);
            system('clear');

    }
}
