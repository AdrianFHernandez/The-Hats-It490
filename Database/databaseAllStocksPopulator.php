<?php 
require_once('databaseModule.php');



function fetchAndStoreAllStocks() {
    $request = buildRequest('FETCH_ALL_TICKERS', []);
    $client = getClientForDMZ();

    $response = $client->send_request($request);

    if ($response && $response["status"] === "SUCCESS") {
        $stocks = $response["payload"]["data"];
        
        if (!$stocks) {
            echo "No stocks found\n";
            return;
        }

        $db = dbConnect();

        $stmt = $db->prepare("INSERT INTO AllStockTickers (ticker, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");

        foreach ($stocks as $stock) {
            $ticker = strtoupper($stock['ticker']);
            $name = trim($stock['name']);

            $stmt->bind_param("ss", $ticker, $name);
            $stmt->execute();
        }

        echo "Successfully fetched and stored all stocks\n";
        
        
    }
    else {
        echo "Failed to fetch all stocks\n";
    }
    
}

fetchAndStoreAllStocks();

?>