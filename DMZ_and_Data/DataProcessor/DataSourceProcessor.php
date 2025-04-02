#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('data.php');
require_once('CollectAllStocks.php');

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
      return fetch_specific_stock_chart_data($request['payload']["ticker"],$request["payload"]["startTime"], $request["payload"]["endTime"]);
    case "get_latest_price":
      return delayed_latest_price($request["ticker"]);
    case "FETCH_ALL_TICKERS":
      return fetchAllTickers();
    case "getStocksBasedOnRisk":
      return getStocksBasedOnRisk($request['risk'], $request['riskFactor']);
    case "FETCH_ALL_STOCKS":
        return fetchActiveStocks();
    case "GET_RECOMMENDED_STOCKS":
        return getRecommendedStocks($request['riskLevel']);
    case "GET_CHATBOT_ANSWER":
        return getChatbotAnswer($request['question']);
  }

  return buildResponse("ERROR", "FAILED", ["message" => "Request type not supported"]);
}

$server = new rabbitMQServer("HatsDMZRabbitMQ.ini","Server");

echo "DataSource Processor BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "DataSource Processor END".PHP_EOL;
exit();
?>

