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
        case "register":
            return doRegister($request['name'], $request['username'], $request['email'], $request['password']);
        case "login":
            $response = doLogin($request['username'], $request['password']);
            if ($response["returnCode"] === '0') {
                $sessionData = createSession($response["user"]["id"]);
                $response["session"] = $sessionData;
                clearExpiredSessions();
            }
            return $response;
        case "validateSession":
            return validateSession($request['sessionId']);
        case "logout":
            return doLogout($request['sessionId']);
        case "getUserInfo":
            return doGetUserInfo($request['sessionID']);
        default:
            return ["returnCode" => '3', "message" => "Unsupported message type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

