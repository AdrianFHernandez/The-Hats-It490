<?php


function fetchActiveStocks()
{
    $apiKey = trim(file_get_contents("FMTAPIKEY"));

    $baseUrl = "https://financialmodelingprep.com/stable/company-screener";
    $batchSize = 10000;
    $filteredStocks = [];
    $exchanges = ["NASDAQ", "NYSE", "AMEX"];
    // $exchanges = ["NASDAQ"];

    echo "Fetching all actively trading stocks from NASDAQ, NYSE & AMEX...\n";

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
            return buildResponse("FETCH_ALL_STOCKS_RESPONSE", "FAILED", ["message" => "API error: Failed to fetch data from $exchange"]);
        }
        
        $data = json_decode($response, true);
        
        if (empty($data)) {
            return buildResponse("FETCH_ALL_STOCKS_RESPONSE", "FAILED", ["message" => "No stocks found on $exchange"]);
        }

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
        }

        echo "Retrieved " . count($filteredStocks) . " stocks from $exchange.\n";
    }

    // Return the result as a structured response
    return buildResponse("FETCH_ALL_STOCKS_RESPONSE", "SUCCESS", [
        "data" => $filteredStocks,
        "message" => "Retrieved a total of " . count($filteredStocks) . " actively trading stock tickers."
    ]);
}

?>
