#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('data.php');

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
    case "fetch_stock_data":
      return fetch_all_stock_data($request["ticker"],$request["start"], $request["end"]);
  }
  return array("success" => '200', "returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("HatsRabbitMQ.ini","Server");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

