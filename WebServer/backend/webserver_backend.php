<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight request (CORS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"), true);

// Check if the data was decoded correctly
if (!$data) {
    echo json_encode(["error" => "No POST message set or invalid JSON"]);
    exit(0);
}

// Handle tasks based on 'type'
switch ($data['type']) {
    case 'login':
        handleLogin($data);
        break;
    case 'register':
        handleRegister($data);
        break;
    case 'validateSession':
        handleValidateSession($data);
        break;
    default:
        echo json_encode(["error" => "Unknown task type"]);
        break;
}

// Function to handle login task
function handleLogin($data) {
    // Validate input for login
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(["error" => "Username and password are required"]);
        exit();
    }

    // Send the login data to RabbitMQ (adjust the request accordingly)
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = [
        'type' => 'login',
        'username' => $username,
        'password' => $password,
        'sessionId' => $data['sessionId'] ?? ''
    ];

    $response = $client->send_request($request);
    if ($response) {
        echo json_encode($response);
    } else {
        echo json_encode(["error" => "Login failed"]);
    }
}

// Function to handle register task
function handleRegister($data) {
    // Validate input for registration
    $name = $data['name'] ?? '';
    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (!$name || !$username || !$email || !$password) {
        echo json_encode(["error" => "All fields are required"]);
        exit();
    }

    // Send the registration data to RabbitMQ
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = [
        'type' => 'register',
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'password' => $password
    ];

    $response = $client->send_request($request);
    if ($response) {
        echo json_encode($response);
    } else {
        echo json_encode(["error" => "Registration failed"]);
    }
}

// Function to validate session task
function handleValidateSession($data) {
    // Validate session ID
    $sessionId = $data['sessionId'] ?? '';

    if (!$sessionId) {
        echo json_encode(["error" => "Session ID is required"]);
        exit();
    }

    if ($sessionId == "mockSessionID123456789") {
        echo json_encode(["valid" => true, "user" => ["username" => "JohnDoe", "email" => "john.doe@example.com"]]);
    } else {
        echo json_encode(["valid" => false]);
    }

    // // Send the session ID to RabbitMQ for validation
    // $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    // $request = [
    //     'type' => 'validateSession',
    //     'sessionId' => $sessionId
    // ];

    // $response = $client->send_request($request);
    // if ($response) {
    //     echo json_encode($response);
    // } else {
    //     echo json_encode(["error" => "Session validation failed"]);
    // }
}
?>
