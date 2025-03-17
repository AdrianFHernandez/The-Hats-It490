#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('databaseModule.php');



function requestProcessor($request)
{
    echo "received request".PHP_EOL;
    var_dump($request);

    
    if (!isset($request['type'])) {
        return ["returnCode" => '3', "message" => "ERROR: unsupported message type"];
    }

    switch ($request['type']) {
        case "REGISTER":
            return doRegister($request['payload']['name'], $request['payload']['username'], $request['payload']['email'], $request['payload']['password']);
        case "LOGIN":
            $response = doLogin($request['payload']['username'],$request['payload']['password']);
            if ($response["status"] === "SUCCESS") {
                $sessionData = createSession($response["payload"]["user"]["id"]);
                $response["payload"]["session"] = $sessionData;
                clearExpiredSessions();
            }
            return $response;
        case "VALIDATE_SESSION":
            return validateSession($request['payload']['sessionId']);
        case "LOGOUT":
            return doLogout($request['payload']['sessionId']);
        case "GET_ACCOUNT_INFO":
            return doGetAccountInfo($request["payload"]['sessionId']);
        case "GET_STOCK_INFO":
            return doGetStockInfo($request["payload"]['sessionId'], $request["payload"]);
        case "GET_STOCKS_BASED_ON_RISK":
            return GetStocksBasedOnRisk($request["payload"]['sessionId'] );
        default:
            return buildResponse("ERROR", "FAILED", ["message" => "Invalid request type"]);
    }
}

$server = new rabbitMQServer("HatsRabbitMQ.ini","Server");


echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>
