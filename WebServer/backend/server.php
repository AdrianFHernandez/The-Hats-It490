<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"), true); 

// Check if the data was decoded correctly
if (!$data) {
    $msg = "No POST message set or invalid JSON";
    echo json_encode($msg);
    exit(0);
}

// Retrieve the username and password from the decoded JSON data
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$type = $data['type'] ?? '';




// Check if username and password are set
if ($username && $password) {
    // Send the username and password to the RabbitMQ server
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = array();
    $request['type'] = $type;
    $request['username'] = $username;
    $request['password'] = $password;
    $request['sessionId'] = $data['sessionId'] ?? '';
    $request['message'] = "How are you sr?";


    $response = $client->send_request($request);
    // Check if the response is valid
    if ($response) {
        echo json_encode($response);
        exit(0);
    }
}



?>

