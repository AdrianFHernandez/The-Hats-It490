#!/usr/bin/php
<?php

require_once("rabbitMQLib.inc");

$client = new rabbitMQClient("QAInstallRabbitMQ.ini","QADBInstallListener");



$request = [
    "type" => "INSTALL_BUNDLE",
    "payload" => [
        "host_user"     => "Adrian",                                  // Remote user
        "host_ip"       => "100.95.180.45",                                // Remote IP (installer cluster)
        "host_password" => "@Dinohunter58",                               // Password to fetch file via SCP
        "remote_path"   => "/home/Deployment/TempBundles/login_package_v4.zip", // Path to bundle on deploy server
        "local_path"    => "/home/Desktop/login_package_v4.zip",                // Where installer will save it
        "sudo_password" => "@Dinohunter58"                                     // For installer to use during execution
    ]
];

$response = $client->send_request($request);
print_r($response);


?>