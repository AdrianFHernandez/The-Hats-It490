<?php

require_once('rabbitMQLib.inc');
require_once('databaseModule.php');

if ($argc < 2) {
    die("Usage: php updateStockSingle.php <ticker>\n");
}

$ticker = $argv[1];

$conn = dbConnect();

// Get last update timestamp for the given ticker
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

// Request data from the RabbitMQ server
$request = [
    "type" => "fetch_stock_data",
    "ticker" => $ticker,
    "start" => 1740787200,
    "end" => 1741651200
];

$client = new rabbitMQClient("HatsRabbitMQ.ini", "Server");
$response = $client->send_request($request);

// print_r($response);

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
    echo "Stock $ticker updated successfully!\n";
} else {
    echo "No data received for $ticker.\n";
}

$conn->close();

?>
