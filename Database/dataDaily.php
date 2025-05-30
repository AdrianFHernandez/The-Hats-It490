<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('databaseModule.php');




function updateLocalStockData() {
    $conn = dbConnect();

    $query = "SELECT DISTINCT ticker FROM Stocks";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($tickerList);

    $tickers = [];
    while ($stmt->fetch()) {
        $tickers[] = $tickerList;
    }
    $stmt->close();

    foreach ($tickers as $ticker) {
        echo $ticker;
        $query = "SELECT MAX(timestamp) FROM PriceHistory WHERE ticker = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $ticker);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($lastTimestamp);
        $stmt->fetch();
        $stmt->close();

        if (!$lastTimestamp) {
            $lastTimestamp = strtotime('-1 month'); 
        }

        $currentTimestamp = time();

        $request = [
            "type" => "fetch_stock_data",
            "ticker" => $ticker,
            "start" => $lastTimestamp,
            "end" => $currentTimestamp
        ];
        $client = new rabbitMQClient("HatsDMZRabbitMQ.ini","Server");
        $response = $client->send_request($request);

        if ($response && isset($response['data'])) {
            $insertQuery = "INSERT INTO PriceHistory (ticker, timestamp, open, high, low, close, volume) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);

            foreach ($response['data'] as $stock) {
                $stmt->bind_param("siiiiii",
                    $ticker,
                    $stock['timestamp'],
                    $stock['open'],
                    $stock['high'],
                    $stock['low'],
                    $stock['close'],
                    $stock['volume']
                );
                $stmt->execute();
            }

            $stmt->close();
        }
        else{
            print_r($request);
        }

        // sleep(5);
    }

    $conn->close();
}

updateLocalStockData();
?>