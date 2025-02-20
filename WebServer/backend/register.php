<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request (CORS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Read and decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
echo $data;

// Check if JSON decoding worked
if (!is_array($data)) {
    echo json_encode(["error" => "Invalid JSON format"]);
    exit();
}


// Validate required fields
if (!isset($data["name"]) || !isset($data["username"]) || !isset($data["email"]) || !isset($data["password"])) {
    echo json_encode(["hi" => "All fields are required"]);
    exit();
}

// Prepare request for RabbitMQ
$request = [
    "type" => "jj",
    "name" => $data["name"],
    "username" => $data["username"],
    "email" => $data["email"],
    "password" => $data["password"]
];

// Initialize RabbitMQ Client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

try {
    // Send request to RabbitMQ and get response
    $response = $client->send_request($request);

    // Log the response
    file_put_contents("debug.log", date("Y-m-d H:i:s") . " - Server Response: " . print_r($response, true) . PHP_EOL, FILE_APPEND);

    // Send response back to frontend
    if ($response) {
        echo json_encode($response);
    }
    //  elseif (isset($response['error'])) {
    //     echo json_encode(["error" => $response['error']]);
    // } else {
    //     echo json_encode(["error" => "Unknown response from server"]);
    //}
} catch (Exception $e) {
    echo json_encode(["error" => "Failed to connect to RabbitMQ"]);
}
?>
