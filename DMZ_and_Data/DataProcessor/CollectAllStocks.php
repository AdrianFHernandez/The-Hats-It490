<?php

$apiKey = trim(file_get_contents("FMTAPIKEY"));

$baseUrl = "https://financialmodelingprep.com/stable/company-screener";

$batchSize = 10000;
$filteredStocks = [];

$exchanges = ["NASDAQ", "NYSE", "AMEX"];

echo "Fetching all actively trading stocks from NASDAQ & NYSE ARCA...\n";

foreach ($exchanges as $exchange) {
    echo "\nFetching stocks from $exchange...\n";

    // Query parameters
    $params = [
        "exchange" => $exchange,
        "isActivelyTrading" => "true",
        "limit" => $batchSize,
        "apikey" => $apiKey
    ];

    // Fetch data
    $url = $baseUrl . "?" . http_build_query($params);
    $response = file_get_contents($url);
    
    if ($response === false) {
        echo "API error: Failed to fetch data from $exchange.\n";
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        echo "No stocks found on $exchange.\n";
        continue;
    }
    $currentStockCount = 0;
    // Extract relevant fields for actively trading stocks
    foreach ($data as $stock) {
        if (!empty($stock["isActivelyTrading"])) {
            $filteredStocks[] = [
                "ticker" => $stock["symbol"],
                "name" => $stock["companyName"],
                "marketCap" => $stock["marketCap"],
                "sector" => $stock["sector"],
                "industry" => $stock["industry"],
                "price" => $stock["price"],
                "exchange" => $stock["exchangeShortName"]
            ];
        }
        $currentStockCount += 1;
    }
    echo "Retrieved " . $currentStockCount . " from $exchange. (Total: " . count($filteredStocks) . ")\n";
}

file_put_contents("filtered_tickers.json", json_encode($filteredStocks, JSON_PRETTY_PRINT));

echo "\nRetrieved a total of " . count($filteredStocks) . " unique actively trading stock tickers.\n";
echo "Filtered stock data saved to filtered_tickers.json\n";