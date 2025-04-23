<?php

require_once('databaseModule.php');

function updateAllStocks() {
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
    $conn->close();

    foreach ($tickers as $ticker) {
        echo "Updating stock: $ticker\n";
        $command = "php updateStockSingle.php $ticker";
        exec($command); 
    }
}

updateAllStocks();

?>
