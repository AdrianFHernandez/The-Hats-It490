#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function doLogin($username,$password)
{
    // lookup username in databas
    // Publish a message to the RabbitMQ server
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");
    $request = array();
    $request['type'] = "login";
    $request['username'] = $username;
    $request['password'] = $password;

    $response = $client->send_request($request);
    
    if ($response['returnCode'] == '0') {
        return ["returnCode" => '0', 'message' => 'Login successful'];
    } else {
        return ["returnCode" => '1', 'message' => 'Login failed'];
    }
}


function doRegister($username,$password, $email, $name)
{
    // add username and password to database
    // Publish a message to the RabbitMQ server
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");
    $request = array();
    $request['type'] = "register";
    $request['username'] = $username;
    $request['password'] = $password;
    $request['email'] = $email;
    $request['name'] = $name;



    $response = $client->send_request($request);

    if ($response['returnCode'] == '0') {
        return ["returnCode" => '0', 'message' => 'Registered successfully'];
    } else {
        return ["returnCode" => '1', 'message' => 'Failed to register'];
    }
    
}


function doValidate($sessionId)
{
    // lookup session ID in database
    // Publish a message to the RabbitMQ server
    $client = new rabbitMQClient("testRabbitMQ.ini","testServer");
    $request = array();
    $request['type'] = "validate_session";
    $request['sessionId'] = $sessionId;
    
    $response = $client->send_request($request);

    if ($response['returnCode'] == '0') {
        return ["returnCode" => '0', 'message' => 'Session is valid'];
    } else {
        return ["returnCode" => '1', 'message' => 'Session is invalid'];
    }
}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "login":
      return doLogin($request['username'],$request['password']);
    case "register":
      return doRegister($request['username'],$request['password'], $request['email'], $request['name']);  
    case "validate_session":
      return doValidate($request['sessionId']);
  }
  return [ "returnCode" => '3', 'message' => 'Unsupported message type' ];
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

