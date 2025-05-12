<?php
// CORS Headers
$allowed_origins = [
    'https://localhost:3000',
    "https://www.investzero.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json");
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized origin']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once(__DIR__ . '/vendor/autoload.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function logExternally($type, $text) {
    $escapedText = escapeshellarg($text);
    $type = strtoupper($type);
    exec("php logEvent.php $type $escapedText > /dev/null 2>&1 &");
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['type'])) {
    logExternally("ERROR", "Frontend: Invalid request received");
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

$client = null;
function get_client() {
    global $client;
    if ($client == null) {
        $client = new rabbitMQClient("HatsRabbitMQ.ini", "Server");
    }
    return $client;
}

function buildRequest($type, $payload = []) {
    return ["type" => $type, "timestamp" => time(), "payload" => $payload];
}

function handleRegister($data) {
    $name = $data['name'] ?? '';
    $username = $data['username'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $phone = $data['phone'] ?? '';

    if (!$name || !$username || !$email || !$password) {
        $msg = "All fields are required";
        logExternally("ERROR", "Frontend: Registration failed - $msg");
        echo json_encode(["error" => $msg]);
        exit();
    }

    $client = get_client();
    $request = buildRequest('REGISTER', [
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'phone' => $phone
    ]);

    $response = $client->send_request($request);

    if ($response && $response['status'] === 'SUCCESS' && $response["type"] === "REGISTER_RESPONSE") {
        logExternally("LOG", "Frontend: Registration success - $username");
        echo json_encode(["success" => true, "message" => $response['payload']['message']]);
    } else {
        $msg = $response['payload']['message'] ?? "Registration failed";
        logExternally("ERROR", "Frontend: Registration failed - $msg");
        echo json_encode(["error" => $msg]);
    }
}

function handleLogin($data) {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        logExternally("ERROR", "Frontend: Login failed - Missing credentials");
        echo json_encode(["error" => "Username and password are required"]);
        exit();
    }

    $client = get_client();
    $request = buildRequest('LOGIN', [
        'username' => $username,
        'password' => $password
    ]);

    $response = $client->send_request($request);

    if ($response && $response['status'] === "SUCCESS" && $response['type'] === 'LOGIN_RESPONSE') {
        logExternally("LOG", "Frontend: OTP sent for login - $username");
        echo json_encode([
            "success" => true,
            "message" => $response["payload"]['message']
        ]);
    } else {
        $msg = $response['payload']['error'] ?? "Login failed";
        logExternally("ERROR", "Frontend: OTP sending failed for Login - $msg");
        echo json_encode(["error" => $msg]);
    }
}


function handleValidateSession() {
    if (!isset($_COOKIE['PHPSESSID'])) {
        logExternally("ERROR", "Frontend: Session validation failed - cookie not set");
        echo json_encode(["valid" => false, "error" => "Session cookie not set"]);
        exit();
    }

    $client = get_client();
    $request = buildRequest('VALIDATE_SESSION', ['sessionId' => $_COOKIE['PHPSESSID']]);
    $response = $client->send_request($request);

    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "VALIDATE_SESSION_RESPONSE") {
        logExternally("LOG", "Frontend: Session validated for user");
        echo json_encode(["valid" => true, "user" => $response["payload"]['user']]);
    } else {
        logExternally("ERROR", "Frontend: Invalid session during validation");
        setcookie("PHPSESSID", "", [
            "expires" => -1,
            "path" => "/",
            "domain" => "www.investzero.com",
            "secure" => true,
            "httponly" => false,
            "samesite" => "None"
        ]);
        echo json_encode(["valid" => false, "error" => "Invalid or expired session"]);
    }
}


function handleVerifyOTP($data) {
    $OTP_code = $data['OTP_code'] ?? '';
    if (!$OTP_code) {
        logExternally("ERROR", "Frontend: OTP verification failed - No code provided");
        echo json_encode(["error" => "OTP code is required"]);
        exit();
    }

    $client = get_client();
    $request = buildRequest('VERIFY_OTP', ['OTP_code' => $OTP_code]);
    $response = $client->send_request($request);

    if ($response && $response['status'] === "SUCCESS" && $response['type'] === 'VERIFY_OTP_RESPONSE') {
        $session_id = $response["payload"]['session']['sessionId'];
        $session_expires = $response["payload"]['session']['expiresAt'];
        setcookie("PHPSESSID", $session_id, [
            "expires" => $session_expires,
            "path" => "/",
            "domain" => "www.investzero.com",
            "secure" => true,
            "httponly" => false,
            "samesite" => "None"
        ]);
        logExternally("LOG", "Frontend: OTP verified");
        echo json_encode([
            "success" => true,
            "sessionId" => $session_id,
            "user" => $response["payload"]['user'],
            "message" => $response["payload"]['message']
        ]);
    } else {
        logExternally("ERROR", "Frontend: OTP verification failed");
        echo json_encode(["error" => "Invalid OTP Code"]);
    }
}


function handleLogout() {
    if (!isset($_COOKIE['PHPSESSID'])) {
        logExternally("ERROR", "Frontend: Logout - session cookie not set");
        echo json_encode(["success" => true, "message" => "Session cookie not set"]);
        return;
    }

    $client = get_client();
    $request = buildRequest('LOGOUT', [
        'sessionId' => $_COOKIE['PHPSESSID']
    ]);

    $response = $client->send_request($request);

    if ($response && $response['status'] === "SUCCESS" && $response['type'] === 'LOGOUT_RESPONSE') {
        logExternally("LOG", "Frontend: Logout successful");
        setcookie("PHPSESSID", "", [
            "expires" => -1,
            "path" => "/",
            "domain" => "www.investzero.com",
            "secure" => true,
            "httponly" => false,
            "samesite" => "None"
        ]);
        echo json_encode(["success" => true, "message" => $response['message']]);
    } else {
        logExternally("ERROR", "Frontend: Logout failed");
        echo json_encode(["error" => "Logout failed"]);
    }
}


function handleGetAccountInfo() {
    if (!isset($_COOKIE['PHPSESSID'])) {
        logExternally("ERROR", "Frontend: GetAccountInfo - session cookie not set");
        echo json_encode(["success" => false, "message" => "Session cookie not set"]);
        return;
    }

    $client = get_client();
    $request = buildRequest('GET_ACCOUNT_INFO', [
        'sessionId' => $_COOKIE['PHPSESSID']
    ]);

    $response = $client->send_request($request);

    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "GET_ACCOUNT_INFO_RESPONSE") {
        logExternally("LOG", "Frontend: Retrieved account info");
        echo json_encode($response["payload"]["data"]);
    } else {
        logExternally("ERROR", "Frontend: Failed to retrieve account info");
        echo json_encode(["success" => false, "error" => "Invalid or expired session"]);
    }
}

function handleGetStockInfo($data){
    $ticker = $data["ticker"] ?? '';
    $marketCapMin = $data["marketCapMin"] ?? '';
    $marketCapMax = $data["marketCapMax"] ?? '';

    if (!isset($_COOKIE['PHPSESSID'])) {
        logExternally("ERROR", "Frontend: Get stock info - session cookie not set");
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
        logExternally("LOG", "Frontend: Retrieved stock info for $ticker");
        echo json_encode($response["payload"]["data"]);
    } else {
        logExternally("ERROR", "Frontend: Failed to retrieve stock info");
        echo json_encode(["message" => $response["payload"]["message"] ?? "Failed to fetch stock info"]);
    }
}

function handleFetchSpecificStockData($data){
    $ticker = $data["ticker"] ?? '';
    $start = $data["startTime"] ?? '';
    $end = $data["endTime"] ?? '';

    if (!$ticker || !$start || !$end) {
        logExternally("ERROR", "Frontend: Fetch specific stock data - missing params");
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
        logExternally("LOG", "Frontend: Fetched specific data for $ticker");
        echo json_encode([
            "message" => $response["payload"]["message"],
            "chartData" => $response["payload"]["data"]["stockData"],
            "stockInfo" => $response["payload"]["data"]["stockInfo"]
        ]);
    } else {
        logExternally("ERROR", "Frontend: Failed to fetch specific stock data");
        echo json_encode(["error" => "Failed to fetch stock data"]);
    }
}

function handlePerformTransaction($data){
    if (!isset($_COOKIE['PHPSESSID'])) {
        logExternally("ERROR", "Frontend: Perform transaction - session cookie not set");
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
        $email = $user["email"] ?? null;
        $username = $user["name"] ?? "User";

        if ($email) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'hatsit490@gmail.com';
                $mail->Password = 'dmft jzxc ilqk xgqy';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('hatsit490@gmail.com', 'InvestZero');
                $mail->addAddress($email, $username);
                $mail->Subject = "Transaction Confirmation - $ticker";
                $mail->Body = "Hello $username,\n\nYour transaction for $quantity shares of $ticker at $$price has been successfully processed.\n\nTransaction Type: $transactionType\n\nThank you for using our service!";

                $mail->send();
                logExternally("LOG", "Frontend: Transaction completed and email sent to $email");
                echo json_encode([
                    "success" => true,
                    "message" => $response["payload"]["message"],
                    "email_status" => "Email sent successfully",
                    "user" => $user
                ]);
            } catch (Exception $e) {
                logExternally("ERROR", "Frontend: Email failed for $email - {$mail->ErrorInfo}");
                echo json_encode([
                    "success" => true,
                    "message" => $response["payload"]["message"],
                    "email_status" => "Email failed: " . $mail->ErrorInfo,
                    "user" => $user
                ]);
            }
        } else {
            logExternally("ERROR", "Frontend: No email address to send transaction receipt");
            echo json_encode([
                "success" => true,
                "message" => $response["payload"]["message"],
                "email_status" => "Email not sent (missing email address)",
                "user" => $user
            ]);
        }
    } else {
        logExternally("ERROR", "Frontend: Transaction failed for $ticker");
        echo json_encode(["error" => $response["payload"]["message"] ?? "Transaction failed"]);
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
        echo json_encode(["error" => "Invalid requestS", "question" => $question]);
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

function handleGetNews($data){
    $query = $data['query'] ?? 'stock_market';
    $client = get_client();
    $request = buildRequest('GET_NEWS', [
        'sessionId' => $_COOKIE['PHPSESSID'],
        'query' => $query
    ]);
    //
    $response = $client->send_request($request);
    
    if ($response && $response["status"] === "SUCCESS" && $response["type"] === "GET_NEWS_RESPONSE") {
        echo json_encode([
            "message" => $response["payload"]["message"],
            "news" => $response["payload"]["data"]
        ]);
    } else {
        echo json_encode(["error" => "Failed to fetch news"]);
    }
}

function handleGet1099K() {
    if (!isset($_COOKIE['PHPSESSID'])) {
        logExternally("ERROR", "Frontend: 1099-K fetch failed - cookie not set");
        echo json_encode(["error" => "Session cookie not set"]);
        exit();
    }

    $sessionId = $_COOKIE['PHPSESSID'];

    
    $client = get_client();
    $request = buildRequest('GET_TAX_1099K', ['sessionId' => $sessionId]);

    $response = $client->send_request($request);

    // Expecting: user: { name, email, phone }, transactions: [{ date, ticker, type, quantity, price }]
    if (
        $response &&
        $response["status"] === "SUCCESS" &&
        $response["type"] === "GET_TAX_1099K_RESPONSE"
    ) {
        logExternally("LOG", "Frontend: Successfully retrieved 1099-K data");
        echo json_encode([
            "user" => $response["payload"]["user"],
            "transactions" => $response["payload"]["transactions"]
        ]);
    } else {
        logExternally("ERROR", "Frontend: Failed to retrieve 1099-K data via broker");
        echo json_encode(["error" => "Failed to fetch tax data"]);
    }
}



switch ($data['type']) {
    case 'REGISTER': handleRegister($data); break;
    case 'LOGIN': handleLogin($data); break;
    case 'VALIDATE_SESSION': handleValidateSession(); break;
    case 'LOGOUT': handleLogout(); break;
    case 'GET_ACCOUNT_INFO': handleGetAccountInfo(); break;
    case 'GET_STOCK_INFO': handleGetStockInfo($data); break;
    case 'FETCH_SPECIFIC_STOCK_DATA': handleFetchSpecificStockData($data); break;
    case "PERFORM_TRANSACTION": handlePerformTransaction($data); break;
    case "VERIFY_OTP": handleVerifyOTP($data); break;
    case "GET_RECOMMENDED_STOCKS":
        handleGetRecommendedStocks($data);
        break;
    case "GET_CHATBOT_ANSWER":
        handleGetChatbotAnswer($data);
        break;
    case "GET_NEWS":
        handleGetNews($data);
        break;
    case 'GET_TAX_1099K':
        handleGet1099K();
        break;
    default:
        logExternally("ERROR", "Frontend: Unknown request type: " . $data['type']);
        echo json_encode(["error" => "Unknown request type"]);
        break;
}
?>
