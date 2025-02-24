#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');



function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type']) {
    case "login":
        // Logic for login validation
        return ["returnCode" => '0', 'message' => 'Login successful'];
    case "register":
        // Logic for user registration
        return ["returnCode" => '0', 'message' => 'Registration successful'];
    case "validate_session":
        // Logic for session validation
        return ["returnCode" => '0', 'message' => 'Session is valid'];
    default:
        return ["returnCode" => '3', 'message' => 'Unsupported message type'];
}
  return [ "returnCode" => '3', 'message' => 'Unsupported message type' ];
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

