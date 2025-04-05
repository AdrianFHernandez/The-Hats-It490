#!/usr/bin/php
<?php

require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("DeploymentRabbitMQ.ini","DeploymentServer");
if (isset($argv[1]))
{
  $msg = $argv[1];
}
else
{
  $msg = "test message";
}

$request = array();
$request['type'] = "Login";
$request['username'] = "steve";
$request['password'] = "password";
$request['message'] = $msg;
echo "Sending";
$response = $client->send_request($request);
//$response = $client->publish($request);
print_r($response);
echo "Recieved";
echo "Sleeping";
sleep(5);
echo "WokeUP";
echo "client received response: ".PHP_EOL;
echo "\n\n";

echo $argv[0]." END".PHP_EOL;

