#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('data.php');
require_once('CollectAllStocks.php');
require_once('otp_utils.php');

function logExternally($type, $text)
{
    $escapedText = escapeshellarg($text);
    $type = strtoupper($type);
    exec("php logEvent.php $type $escapedText > /dev/null 2>&1 &");
    // file_put_contents("logEvent.log", "Log event executed: $type $escapedText\n", FILE_APPEND);
}

$i = 0;
function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "FETCH_SPECIFIC_STOCK_DATA":
      global $i;
      if ($i < -1){
        return buildResponse("ERROR", "FAILED", ["message" => "Request limit reached"]);
        
      }
      $i += 1;
      return fetch_specific_stock_chart_data($request['payload']["ticker"],$request["payload"]["startTime"], $request["payload"]["endTime"]);
    case "get_latest_price":
      return delayed_latest_price($request["ticker"]);
    case "FETCH_ALL_TICKERS":
      return fetchAllTickers();
    case "getStocksBasedOnRisk":
      return getStocksBasedOnRisk($request['risk'], $request['riskFactor']);
    case "FETCH_ALL_STOCKS":
        return fetchActiveStocks();
    case "SEND_OTP_CODE":
        return buildResponse("SEND_OTP_CODE_RESPONSE", "SUCCESS", [
            "message" => "OTP sent successfully" . $request['payload']["otpCode"],
        ]);
        // return sendOTP($request['payload']["phoneNumber"], $request['payload']["otpCode"]);
    
  }

  $response = buildResponse("ERROR", "FAILED", ["message" => "Request type not supported"]);

  $logType = ($response['status'] === "SUCCESS") ? "LOG" : "ERROR";
    $logMessage = $response["payload"]["message"] ?? $response["payload"]["error"] ?? "error in databaseprocessor";


    logExternally($logType, $logMessage);

    return $response;

}


$server = new rabbitMQServer("HatsDMZRabbitMQ.ini","Server");

echo "DataSource Processor BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "DataSource Processor END".PHP_EOL;
exit();
?>

