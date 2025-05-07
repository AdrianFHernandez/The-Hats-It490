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
function requestProcessor($request) {
  echo "received request - deploy test" . PHP_EOL;
  var_dump($request);

  if (!isset($request['type'])) {
      $response = buildResponse("ERROR", "FAILED", ["message" => "Missing request type"]);
  } else {
      switch ($request['type']) {
          case "FETCH_SPECIFIC_STOCK_DATA":
              global $i;
              if ($i < -1){
                  $response = buildResponse("ERROR", "FAILED", ["message" => "Request limit reached"]);
              } else {
                  $i += 1;
                  $response = fetch_specific_stock_chart_data(
                      $request['payload']["ticker"],
                      $request["payload"]["startTime"],
                      $request["payload"]["endTime"]
                  );
              }
              break;

          case "get_latest_price":
              $response = delayed_latest_price($request["ticker"]);
              break;

          case "FETCH_ALL_TICKERS":
              $response = fetchAllTickers();
              break;

          case "getStocksBasedOnRisk":
              $response = getStocksBasedOnRisk($request['risk'], $request['riskFactor']);
              break;

          case "FETCH_ALL_STOCKS":
              $response = fetchActiveStocks();
              break;
         case "GET_RECOMMENDED_STOCKS":
                $response = getRecommendedStocks($request['payload']['riskLevel']);
                break;
            case "GET_CHATBOT_ANSWER":
                $response = getChatbotAnswer($request['payload']['question']);
                break;
            case "GET_NEWS":
                $response = getNews();
                break;
          case "SEND_OTP_CODE":
              $response = sendOTP($request['payload']["phoneNumber"], $request['payload']["otpCode"]);
              break;

          default:
              $response = buildResponse("ERROR", "FAILED", ["message" => "Unknown request type: " . $request['type']]);
              break;
      }
  }

  $logType = ($response['status'] === "SUCCESS") ? "LOG" : "ERROR";
  $logMessage = $response["payload"]["message"] ?? $response["payload"]["error"] ?? $response["message"] ?? "Unknown response error";

  logExternally($logType, "DMZ: " . $logMessage);

  return $response;
}



$server = new rabbitMQServer("HatsDMZRabbitMQ.ini","Server");

echo "DataSource Processor BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "DataSource Processor END".PHP_EOL;
exit();
?>


