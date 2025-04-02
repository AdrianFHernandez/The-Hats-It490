<?php
// CORS Headers
header('Access-Control-Allow-Origin: http://localhost:3000'); // Update if frontend is deployed
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Handle preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}


// Include RabbitMQ connection
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


require '/home/ubuntu/Desktop/The-Hats-It490/WebServer/backend/vendor/autoload.php';  // Use absolute path


// Decode incoming JSON request
$data = json_decode(file_get_contents("php://input"), true);

// Ensure valid request
if (!$data || !isset($data['type'])) {
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

$client = null;

function get_client(){
    global $client;
    if($client == null){
        $client = new rabbitMQClient("HatsRabbitMQ.ini", "Server");
    }
    return $client;
}

function buildRequest($type, $payload = []){
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
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
    $client = get_client();

    $request = buildRequest('REGISTER', [
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'password' => $password
    ]);


    $response = $client->send_request($request);

    if ($response && $response['status'] === 'SUCCESS' && $response["type"] === "REGISTER_RESPONSE") {
        echo json_encode(["success" => true, "message" => $response['payload']['message']]);
    } else {
        echo json_encode(["error" => $response['payload']['message'] ?? "Registration failed"]);
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
    $client = get_client();
    $request = buildRequest('LOGIN', [
        'username' => $username,
        'password' => $password
    ]);

    $response = $client->send_request($request);

    if ($response && $response['status'] === "SUCCESS" && $response['type'] === 'LOGIN_RESPONSE') {
        $session_id = $response["payload"]['session']['sessionId'];
        $session_expires = $response["payload"]['session']['expiresAt'];
        // set session_id in browser cookie with same site attribute as None
        
        setcookie("PHPSESSID", $session_id, [
            "expires" => $session_expires,
            "path" => "/",
            "domain" => "www.sample.com",
            "secure" => false, // change to false for http testing
            "httponly" => true,
            "samesite" => "lax" // lax
        ]);

        echo json_encode([
            "success" => true,
             "sessionId" => $session_id,
             "user" => $response["payload"]['user'],
             "message" => $response["payload"]['message']
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
    $client = get_client();
    $request = buildRequest('VALIDATE_SESSION', [
        'sessionId' => $_COOKIE['PHPSESSID']
    ]);

    $response = $client->send_request($request);
    
    // echo json_encode($response);
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "VALIDATE_SESSION_RESPONSE") {
        echo json_encode([
            "valid" => true,
            "user" => $response["payload"]['user']
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

    $client = get_client();
    $request = buildRequest('LOGOUT', [
        'sessionId' => $_COOKIE['PHPSESSID']
    ]);
    
    $response = $client->send_request($request);

    if ($response && $response['status'] === "SUCCESS" && $response['type'] === 'LOGOUT_RESPONSE') {
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
    
    $client = get_client();
    $request = buildRequest('GET_ACCOUNT_INFO', [
        'sessionId' => $_COOKIE['PHPSESSID']
    ]);
    
    $response = $client->send_request($request);

    ob_clean();
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "GET_ACCOUNT_INFO_RESPONSE") {
        $payload = $response["payload"];
        echo json_encode($payload["data"]);
           
    

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
        
    } else {
        echo json_encode(["valid" => false, "error" => "Invalid or expired sesasion"]);
    }

}

function handlePerformTransaction($data) {
    if (!isset($_COOKIE['PHPSESSID'])) {
        echo json_encode(["success" => false, "message" => "Session cookie not set"]);
        exit();
    }

    $ticker = $data["ticker"];
    $quantity = $data["quantity"];
    $price = $data["price"];
    $transactionType = $data["transactionType"];
    $client = get_client();
    $request = buildRequest('PERFORM_TRANSACTION', [
        'sessionId' => $_COOKIE['PHPSESSID'],
        'ticker' => $ticker,
        'quantity' => $quantity,
        'price' => $price,
        'type' => $transactionType
    ]);

    $response = $client->send_request($request);

    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "PERFORM_TRANSACTION_RESPONSE") {
        $user = $response["payload"]["user"];
        $email = $user["email"] ?? null; // Ensure email exists
        $username = $user["name"] ?? "User";

        if ($email) {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            try {
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // SMTP Server (Gmail)
                $mail->SMTPAuth = true;
                $mail->Username = 'hatsit490@gmail.com'; // Your Gmail
                $mail->Password = 'dmft jzxc ilqk xgqy'; // Generate an App Password (DO NOT USE YOUR MAIN PASSWORD)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Email Details
                $mail->setFrom('your-email@gmail.com', 'Your Name');
                $mail->addAddress($email, $username);
                $mail->Subject = "Transaction Confirmation - $ticker";
                $mail->Body = "Hello $username,\n\nYour transaction for $quantity shares of $ticker at $$price has been successfully processed.\n\nTransaction Type: $transactionType\n\nThank you for using our service!";

                // Send the email
                $mail->send();

                echo json_encode([
                    "success" => true,
                    "message" => $response["payload"]["message"],
                    "email_status" => "Email sent successfully",
                    "user" => $user
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    "success" => true,
                    "message" => $response["payload"]["message"],
                    "email_status" => "Email failed: " . $mail->ErrorInfo,
                    "user" => $user
                ]);
            }
        } else {
            echo json_encode([
                "success" => true,
                "message" => $response["payload"]["message"],
                "email_status" => "Email not sent (missing email address)",
                "user" => $user
            ]);
        }
    } else {
        echo json_encode(["error" => $response["payload"]["message"] ?? "Transaction failed"]);
    }
}

function handleGetStockInfo($data){
    $ticker = $data["ticker"] ?? '';
    $marketCapMin = $data["marketCapMin"] ?? '';
    $marketCapMax = $data["marketCapMax"] ?? '';
    // Send logout request to RabbitMQ
    if (!isset($_COOKIE['PHPSESSID'])) {
        echo json_encode(["valid" => false, "message" => "Session cookie not set"]);
        exit();
    }
     
   
    $client = get_client();
    

    $request = buildRequest('GET_STOCK_INFO', [
        'sessionId' => $_COOKIE['PHPSESSID'],
        'ticker' => $ticker,
        'marketCapMin' => $marketCapMin,
        'marketCapMax' => $marketCapMax
    ]);
    
    $response = $client->send_request($request);
    
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "GET_STOCK_INFO_RESPONSE") {
        ob_clean();
        echo json_encode(
            $response["payload"]["data"]
        );
    }
    else{
        echo json_encode(["message" => $response["payload"]["message"] ?? "Failed to fetch stock info"]);
    }


}

function handleFetchSpecificStockData( $data ){
    $ticker = $data["ticker"] ?? '';
    $start = $data["startTime"] ?? '';
    $end = $data["endTime"] ?? '';
    if (!$ticker || !$start || !$end) {
        echo json_encode(["error" => "Invalid request"]);
        exit();
    }
    $client = get_client();
    $request = buildRequest('FETCH_SPECIFIC_STOCK_DATA', [
        'sessionId' => $_COOKIE['PHPSESSID'],
        'ticker' => $ticker,
        'start' => $start,
        'end' => $end
    ]);

    $response = $client->send_request($request);
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "FETCH_SPECIFIC_STOCK_DATA_RESPONSE") {
        echo json_encode([
            "message" => $response["payload"]["message"],
            "chartData" => $response["payload"]["data"]["stockData"],
            "stockInfo" => $response["payload"]["data"]["stockInfo"]
        ]);
    } else {
        echo json_encode(["error" => "Failed to fetch stock data"]);
    }

}


function handleGetRecommendedStocks($data){
    $riskLevel = $data["riskLevel"] ?? '';
     
    if ($riskLevel == '') {
        echo json_encode(["error" => "Invalid request"]);
        exit();
    }
    $client = get_client();
    $request = buildRequest('GET_RECOMMENDED_STOCKS', [
        'sessionId' => $_COOKIE['PHPSESSID'],
        'riskLevel' => $riskLevel
    ]);
    //
    $response = $client->send_request($request);
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "GET_RECOMMENDED_STOCKS_RESPONSE") {
        echo json_encode([
            "message" => $response["payload"]["message"],
            "recommendedStocks" => $response["payload"]["data"]
        ]);
    } else {
        echo json_encode(["error" => "Failed to fetch recommended stocks"]);
    }
}

function handleGetChatbotAnswer($data){
    $question = $data["question"] ?? '';
     
    if ($question == '') {
        echo json_encode(["error" => "Invalid request"]);
        exit();
    }
    $client = get_client();
    $request = buildRequest('GET_CHATBOT_ANSWER', [
        'sessionId' => $_COOKIE['PHPSESSID'],
        'question' => $question
    ]);
    //
    $response = $client->send_request($request);
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "GET_CHATBOT_ANSWER_RESPONSE") {
        echo json_encode([
            "message" => $response["payload"]["data"]["message"],
            "answer" => $response["payload"]["data"]["answer"],
            "citations" => $response["payload"]["data"]["citations"]
        ]);
    } else {
        echo json_encode(["error" => "Failed to fetch recommended stocks"]);
    }
}

// Process API requests
switch ($data['type']) {
    case 'REGISTER':
        handleRegister($data);
        break;
    case 'LOGIN':
        handleLogin($data);
        break;
    case 'VALIDATE_SESSION':
        handleValidateSession();
        break;
    case 'LOGOUT':
        handleLogout();
        break;
    case 'GET_ACCOUNT_INFO':
        handleGetAccountInfo();
        break;
    case 'GET_STOCK_INFO':
        handleGetStockInfo($data);
        break;
    case 'GET_STOCKS_BASED_ON_RISK':
        handleGetStocksBasedOnRisk($data);
        break;
    case 'FETCH_SPECIFIC_STOCK_DATA':
        handleFetchSpecificStockData($data);
        break;
    case 'PERFORM_TRANSACTION':
        handlePerformTransaction($data);
        break;
    case "GET_RECOMMENDED_STOCKS":
        handleGetRecommendedStocks($data);
        break;
    case "GET_CHATBOT_ANSWER":
        handleGetChatbotAnswer($data);
        break;
    default:
        echo json_encode(["error" => "Unknown request type --"]);
        break;
}
?>
