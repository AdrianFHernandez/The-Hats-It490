<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true'); // Required for session persistence
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

//Set up session params
session_set_cookie_params([
    'lifetime' => 1800, // Session ends when the browser closes
    'path' => '/',
    'domain' => 'www.sample.com', // Ensure this matches your frontend
    'secure' => true, // IMPORTANT: Set to false because HTTPS is not used
    'httponly' => true, // Prevent JavaScript from accessing the cookie
    'samesite' => 'None' // Allows cross-origin requests
]);


// Handle preflight request (CORS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}


session_start();

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$data = json_decode(file_get_contents("php://input"), true);


if (!$data) {
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

// Handle API request types
switch ($data['type']) {
    case 'login':
        handleLogin($data);
        break;
    case 'register':
        handleRegister($data);
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
     
    if ($response) {
        session_start();
        // Ensure session ID persists
        if (!isset($_SESSION['sessionId'])) {
            $_SESSION['sessionId'] = session_id();
        }

        $_SESSION['user'] = [
            "username" => $username,
            "email" => $response['message']
        ];

        echo json_encode(["success" => true, "sessionId" => $_SESSION['sessionId'], "user" => $_SESSION['user']]);
    } else {
        echo json_encode(["error" => "Login failed"]);
    }
}

// Function to validate session
function handleValidateSession() {
    

    if (!isset($_SESSION['sessionId'])) {
        echo json_encode(["valid" => false, "error" => "Session not found"]);
        exit();
    }

    echo json_encode([
        "valid" => true,
        "user" => $_SESSION['user'],
        "sessionId" => $_SESSION['sessionId']
    ]);
}

// Function to log out and destroy session
function handleLogout() {
    
    $_SESSION = []; // Clear session data
    session_destroy(); // Destroy session
    setcookie("PHPSESSID", "", time() - 3600, "/"); // Remove session cookie

    echo json_encode(["success" => true]);
    exit();
}
?>

