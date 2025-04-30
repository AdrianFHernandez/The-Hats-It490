#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('databaseModule.php');

function logExternally($type, $text)
{
    $escapedText = escapeshellarg($text);
    $type = strtoupper($type);
    exec("php logEvent.php $type $escapedText > /dev/null 2>&1 &");
    // file_put_contents("logEvent.log", "Log event executed: $type $escapedText\n", FILE_APPEND);
}

function requestProcessor($request)
{
    echo "received request" . PHP_EOL;
    var_dump($request);

    if (!isset($request['type'])) {
        logExternally("ERROR", "Missing request type");
        return buildResponse("ERROR", "FAILED", ["message" => "Missing request type"]);
    }

    $response = null;

    switch ($request['type']) {
        case "REGISTER":
            $response = doRegister(
                $request['payload']['name'],
                $request['payload']['username'],
                $request['payload']['email'],
                $request['payload']['password'],
                $request['payload']['phone']
            );
            break;
        case "LOGIN":
            $response = doLogin($request['payload']['username'], $request['payload']['password']);
            break;
        case "VALIDATE_SESSION":
            $response = validateSession($request['payload']['sessionId']);
            break;
        case "LOGOUT":
            $response = doLogout($request['payload']['sessionId']);
            break;
        case "GET_ACCOUNT_INFO":
            $response = doGetAccountInfo($request["payload"]['sessionId']);
            break;
        case "GET_STOCK_INFO":
            $response = doGetStockInfo($request["payload"]['sessionId'], $request["payload"]);
            break;
        case "GET_STOCKS_BASED_ON_RISK":
            $response = GetStocksBasedOnRisk($request["payload"]['sessionId']);
            break;
        case "PERFORM_TRANSACTION":
            $response = performTransaction(
                $request["payload"]['sessionId'],
                $request["payload"]['ticker'],
                $request["payload"]['quantity'],
                $request["payload"]['price'],
                $request["payload"]['type']
            );
            break;
        case "FETCH_SPECIFIC_STOCK_DATA":
            $response = fetchSpecificStockData(
                $request["payload"]['sessionId'],
                $request["payload"]['ticker'],
                $request["payload"]['start'],
                $request["payload"]['end']
            );
            break;
        case "VERIFY_OTP":
            $response = verifyOTP($request["payload"]['OTP_code']);
            if ($response["status"] === "SUCCESS") {
                $sessionData = createSession($response["payload"]["user"]["id"]);
                $response["payload"]["session"] = $sessionData;
                clearExpiredSessions();
            }
            break;
        default:
            $response = buildResponse("ERROR", "FAILED", ["message" => "Unknown request type: " . $request['type']]);
            break;
    }

    // Logging every response
    $logType = ($response['status'] === "SUCCESS") ? "LOG" : "ERROR";
    $logMessage = $response["payload"]["message"] ?? $response["payload"]["error"] ?? "error in databaseprocessor";


    logExternally($logType, $logMessage);

    return $response;
}

$server = new rabbitMQServer("HatsRabbitMQ.ini", "Server");

echo "testRabbitMQServer BEGIN" . PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END" . PHP_EOL;
exit();
?>
