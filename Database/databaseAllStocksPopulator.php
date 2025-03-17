<?php 
require_once('databaseModule.php');

function fetchAndStoreAllStocks() {
    $request = buildRequest('FETCH_ALL_STOCKS', []);
    $client = getClientForDMZ();
    
    $response = $client->send_request($request);

    if ($response && $response["status"] === "SUCCESS") {
        $stocks = $response["payload"]["data"];

        if (!$stocks) {
            echo "No stocks found\n";
            return;
        }

        $db = dbConnect();

        // Prepare SQL statement for inserting/updating Stocks table
        $stmt = $db->prepare("
            INSERT INTO Stocks (ticker, name, marketCap, sector, industry, price, exchange, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                marketCap = VALUES(marketCap), 
                sector = VALUES(sector), 
                industry = VALUES(industry), 
                price = VALUES(price), 
                exchange = VALUES(exchange), 
                description = VALUES(description)
        ");

        if (!$stmt) {
            die("Error preparing SQL statement: " . $db->error);
        }

        // Insert each stock into the database
        foreach ($stocks as $stock) {
            $ticker = strtoupper($stock['ticker'] ?? '');
            $name = trim($stock['name'] ?? '');
            $marketCap = $stock['marketCap'] ?? null;
            $sector = trim($stock['sector'] ?? '');
            $industry = trim($stock['industry'] ?? '');
            $price = $stock['price'] ?? null;
            $exchange = trim($stock['exchange'] ?? '');
            $description = trim($stock['description'] ?? '');

            if (!empty($ticker) && !empty($name)) {
                $stmt->bind_param("ssdsdsss", 
                    $ticker, $name, $marketCap, $sector, $industry, $price, $exchange, $description
                );
                $stmt->execute();
            }
        }

        $stmt->close();
        $db->close();

        echo "Successfully fetched and stored all stocks in the Stocks table\n";

    } else {
        echo "Failed to fetch all stocks\n";
    }
}

fetchAndStoreAllStocks();
?>
