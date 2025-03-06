<?php




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
        // $session_id = $response['session']['sessionId'];
        // set session_id in browser cookie with same site attribute as None
        
        setcookie("PHPSESSID", $session_id, [
            "expires" => 0,
            "path" => "/",
            "domain" => "www.sample.com",
            "secure" => false,
            "httponly" => true,
            "samesite" => "lax"
        ]);
        echo json_encode([
            "success" => true,
             "sessionId" => $session_id,
             "user" => $response['user'],
             "message" => $response['message']
        ]);
    } else {
        echo json_encode(["error" => "Invalid username or password"]);
    }
}

// Function to validate session
function handleValidateSession() {
    if (!isset($_COOKIE['PHPSESSID'])) {
        echo json_encode(["valid" => false, "error" => "Session cookie not set"]);
        exit();
    }
    
    // Send session validation request to RabbitMQ
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = ['type' => 'validateSession', 'sessionId' => $_COOKIE['PHPSESSID']];
    $response = $client->send_request($request);
    
    // echo json_encode($response);
    if ($response && isset($response['valid']) && $response['valid']) {
        echo json_encode([
            "valid" => true,
            "user" => $response['user'],
            "sessionId" => $response['sessionId']
        ]);
    } else {
        // Clear session cookie
        setcookie("PHPSESSID", "", [
            "expires" => -1,
            "path" => "/",
            "domain" => "www.sample.com",
            "secure" => false,
            "httponly" => true,
            "samesite" => "lax"
        ]);
        echo json_encode(["valid" => false, "error" => "Invalid or expired sesasion"]);
    }

    
}

// Function to handle logout
function handleLogout() {
    // Send logout request to RabbitMQ
    if (!isset($_COOKIE['PHPSESSID'])) {
        echo json_encode(["success" => true, "message" => "Session cookie not set"]);
        exit();
    }

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = ['type' => 'logout', 'sessionId' => $_COOKIE['PHPSESSID']];
    $response = $client->send_request($request);

    if ($response && isset($response['success']) && $response['success']) {
        // Clear session cookie
        //Test cookie setting over http
        setcookie("PHPSESSID", "", [
            "expires" => -1,
            "path" => "/",
            "domain" => "www.sample.com",
            "secure" => false,
            "httponly" => true,
            "samesite" => "lax"
        ]);
        
        echo json_encode(["success" => true, "message" => $response['message']]);
    } else {
        echo json_encode(["error" => "Logout failed"]);
    }
    
}

function handleGetAccountInfo(){
    // Send logout request to RabbitMQ
    if (!isset($_COOKIE['PHPSESSID'])) {
        echo json_encode(["success" => true, "message" => "Session cookie not set"]);
        exit();
    }

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = ['type' => 'getAccountInfo', 'sessionId' => $_COOKIE['PHPSESSID']];
    $response = $client->send_request($request);

    // echo json_encode($response);
    if ($response && isset($response['valid']) && $response['valid']) {
        echo json_encode([
            "userStocks" => $response['user']['userStocks'],
            "userCashBalance" => $response['user']['userBalance']['cashBalance'],
            "userStockBalance" => $response['user']['userBalance']['stockBalance'],
            "userTotalBalance" => $response['user']['userBalance']['totalBalance'],
            "sessionId" => $response['sessionId']

    // EXPECTING SOMETHING LIKE THIS:
    // return response = {
    //     "user" : {
    //         "userStocks" : {
    //             "TSLA": {
    //                "companyName" : "Tesla",
    //                "companyDescription": "This company does this ...",
    //                "count" : 2,
    //                "averagePrice" : 300
    //             },
    //             "VOO" : {
    //                 "count" : 1,
    //                 "avergaePrice" : 390
    //             }
    //         },
    //         "userBalance": {
    //             "cashBalance": 10, 
    //             "stockBalance": 990,
    //             "totalBalance" : 1000
    //         }
    //     }
    // }
        ]);
    } else {
        echo json_encode(["valid" => false, "error" => "Invalid or expired sesasion"]);
    }

}

function handleGetStockInfo(){
    // Send logout request to RabbitMQ
    if (!isset($_COOKIE['PHPSESSID'])) {
        echo json_encode(["success" => true, "message" => "Session cookie not set"]);
        exit();
    }

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = ['type' => 'getStockInfo', 'sessionId' => $_COOKIE['PHPSESSID']];
    $response = $client->send_request($request);


    // echo json_encode($response);
    if ($response && isset($response['valid']) && $response['valid']) {
        echo json_encode([
            "tickerPrice" => $response["tickerPrice"]
        ]);
    }
    else{
        echo json_encode(["valid" => false, "error" => "Invalid or expired sesasion"]);
    }


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
    case 'getAccountInfo':
        handleGetAccountInfo();
        break;
    case 'getStockInfo' :
        handleGetStockInfo();
        break;
    default:
        echo json_encode(["error" => "Unknown request type"]);
        break;
}
?>