<?php


// Set up session parameters for security and persistence
session_set_cookie_params([
    'lifetime' => 0, // Session cookie expires when browser is closed
    'path' => '/',
    'domain' => 'www.sample.com', // Ensure this matches your frontend
    'secure' => true, // Use true for HTTPS, false for local testing
    'httponly' => true, // Prevent JavaScript access to cookies
    'samesite' => 'None' // Allows cross-origin requests
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS Headers
header('Access-Control-Allow-Origin: http://localhost:3000'); // Update if frontend is deployed
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Include RabbitMQ connection
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Decode incoming JSON request
$data = json_decode(file_get_contents("php://input"), true);

// Ensure valid request
if (!$data || !isset($data['type'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

// Function to handle user registration
function handleRegister($data) {
    $name = $data['name'] ?? '';
    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (!$name || !$username || !$email || !$password) {
        echo json_encode(["error" => "All fields are required"]);
        exit();
    }

    // Send registration request to RabbitMQ
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = [
        'type' => 'register',
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'password' => $password
    ];

    $response = $client->send_request($request);

    if ($response && isset($response['returnCode']) && $response['returnCode'] === '0') {
        echo json_encode(["success" => true, "message" => $response['message']]);
    } else {
        echo json_encode(["error" => $response['message'] ?? "Registration failed"]);
    }
}

// Function to handle login
function handleLogin($data) {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(["error" => "Username and password are required"]);
        exit();
    }

    // Send login request to RabbitMQ
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = ['type' => 'login', 'username' => $username, 'password' => $password];

    $response = $client->send_request($request);

    if ($response && isset($response['returnCode']) && $response['returnCode'] === '0') {
        $_SESSION['sessionId'] = session_id();
        $_SESSION['user'] = [
            "username" => $username,
            "email" => $response['user']['email'] ?? ''
        ];

        echo json_encode([
            "success" => true,
            "sessionId" => $_SESSION['sessionId'],
            "user" => $_SESSION['user']
        ]);
    } else {
        echo json_encode(["error" => "Invalid username or password"]);
    }
}

// Function to validate session
function handleValidateSession() {
    if (!isset($_SESSION['sessionId']) || empty($_SESSION['user'])) {
        echo json_encode(["valid" => false, "error" => "Session not found"]);
        exit();
    }

    echo json_encode([
        "valid" => true,
        "user" => $_SESSION['user'],
        "sessionId" => $_SESSION['sessionId']
    ]);
}

// Function to handle logout
function handleLogout() {
    $_SESSION = [];
    session_destroy();
    setcookie("PHPSESSID", "", time() - 3600, "/");

    echo json_encode(["success" => true, "message" => "Logout successful"]);
    exit();
}



// Process API requests
switch ($data['type']) {
    case 'register':
        handleRegister($data);
        break;
    case 'login':
        handleLogin($data);
        break;
    case 'validateSession':
        handleValidateSession();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        echo json_encode(["error" => "Unknown request type"]);
        break;
}
?>
