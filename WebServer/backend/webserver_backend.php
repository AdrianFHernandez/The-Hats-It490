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

function handleRegister($data) {
    $username = $data['username'] ?? '';
    logExternally("LOG", "Frontend: Register attempt for username '$username'");

    $request = buildRequest('REGISTER', [
        'name' => $data['name'] ?? '',
        'username' => $username,
        'email' => $data['email'] ?? '',
        'password' => $data['password'] ?? '',
        'phone' => $data['phone'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleLogin($data) {
    $username = $data['username'] ?? '';
    logExternally("LOG", "Frontend: Login request from user '$username'");

    $request = buildRequest('LOGIN', [
        'username' => $username,
        'password' => $data['password'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleValidateSession() {
    logExternally("LOG", "Frontend: Validate session attempt");
    $request = buildRequest('VALIDATE_SESSION', [
        'sessionId' => $_COOKIE['PHPSESSID'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleLogout() {
    logExternally("LOG", "Frontend: Logout attempt");
    $request = buildRequest('LOGOUT', [
        'sessionId' => $_COOKIE['PHPSESSID'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleGetAccountInfo() {
    logExternally("LOG", "Frontend: Get account info request");
    $request = buildRequest('GET_ACCOUNT_INFO', [
        'sessionId' => $_COOKIE['PHPSESSID'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleGetStockInfo($data) {
    logExternally("LOG", "Frontend: Get stock info request");
    $request = buildRequest('GET_STOCK_INFO', [
        'sessionId' => $_COOKIE['PHPSESSID'] ?? '',
        'ticker' => $data['ticker'] ?? '',
        'marketCapMin' => $data['marketCapMin'] ?? '',
        'marketCapMax' => $data['marketCapMax'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleGetStocksBasedOnRisk($data) {
    logExternally("LOG", "Frontend: Get stocks by risk request");
    $response = ["status" => "ERROR", "message" => "Not implemented yet"];
    echo json_encode($response);
}

function handleFetchSpecificStockData($data) {
    logExternally("LOG", "Frontend: Fetch specific stock data request");
    $request = buildRequest('FETCH_SPECIFIC_STOCK_DATA', [
        'sessionId' => $_COOKIE['PHPSESSID'] ?? '',
        'ticker' => $data['ticker'] ?? '',
        'start' => $data['startTime'] ?? '',
        'end' => $data['endTime'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

function handleVerifyOTP($data) {
    logExternally("LOG", "Frontend: Verify OTP attempt");
    $request = buildRequest('VERIFY_OTP', [
        'OTP_code' => $data['OTP_code'] ?? ''
    ]);
    $response = get_client()->send_request($request);
    if ($response["status"] === "SUCCESS") {
        $session = $response["payload"]["session"];
        setcookie("PHPSESSID", $session['sessionId'], [
            "expires" => $session['expiresAt'],
            "path" => "/",
            "domain" => "www.investzero.com",
            "secure" => true,
            "httponly" => false,
            "samesite" => "None"
        ]);
    }
    echo json_encode($response);
}

function handlePerformTransaction($data) {
    $ticker = $data['ticker'] ?? '';
    logExternally("LOG", "Frontend: Perform transaction for ticker '$ticker'");
    $request = buildRequest('PERFORM_TRANSACTION', [
        'sessionId' => $_COOKIE['PHPSESSID'] ?? '',
        'ticker' => $ticker,
        'quantity' => $data["quantity"] ?? 0,
        'price' => $data["price"] ?? 0,
        'type' => $data["transactionType"] ?? ''
    ]);
    $response = get_client()->send_request($request);
    echo json_encode($response);
}

switch ($data['type']) {
    case 'REGISTER': handleRegister($data); break;
    case 'LOGIN': handleLogin($data); break;
    case 'VALIDATE_SESSION': handleValidateSession(); break;
    case 'LOGOUT': handleLogout(); break;
    case 'GET_ACCOUNT_INFO': handleGetAccountInfo(); break;
    case 'GET_STOCK_INFO': handleGetStockInfo($data); break;
    case 'GET_STOCKS_BASED_ON_RISK': handleGetStocksBasedOnRisk($data); break;
    case 'FETCH_SPECIFIC_STOCK_DATA': handleFetchSpecificStockData($data); break;
    case 'PERFORM_TRANSACTION': handlePerformTransaction($data); break;
    case 'VERIFY_OTP': handleVerifyOTP($data); break;
    default:
        logExternally("ERROR", "Frontend: Unknown request type '{$data['type']}'");
        echo json_encode(["error" => "Unknown request type"]);
        break;
}
?>
