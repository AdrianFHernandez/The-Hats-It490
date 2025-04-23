<?php

function getStocksByRisk($riskLevel, $apiToken) {
    $baseUrl = "https://financialmodelingprep.com/api/v3/stock-screener";

    // Define risk level parameters
    $riskFilters = [
        1 => ["betaLowerThan" => 1, "marketCapMoreThan" => 10000000000], // Conservative
        2 => ["betaMoreThan" => 1, "betaLowerThan" => 2, "marketCapMoreThan" => 2000000000, "marketCapLowerThan" => 10000000000], // Casual
        3 => ["betaMoreThan" => 2, "marketCapLowerThan" => 2000000000] // Risky
    ];

    if (!array_key_exists($riskLevel, $riskFilters)) {
        throw new Exception("Invalid risk level. Choose 1 (Conservative), 2 (Casual), or 3 (Risky)");
    }

    $params = array_merge($riskFilters[$riskLevel], [
        "isActivelyTrading" => "true",
        "limit" => 10,
        "apikey" => $apiToken
    ]);

    $queryString = http_build_query($params);
    $url = "$baseUrl?$queryString";

    return fetchAPI($url);
}

function fetchAPI($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ["error" => "API request failed with status code $httpCode"];
    }

    return json_decode($response, true);
}

// Secure API Key Usage
$apiKey = getenv("FMP_API_KEY"); // Load from env variable

// Get user input for risk level (CLI)
echo "Select Risk Level (1 = Conservative, 2 = Casual, 3 = Risky): ";
$riskLevel = trim(readline());

if (!in_array($riskLevel, ["1", "2", "3"])) {
    die("Invalid choice! Please enter 1, 2, or 3.\n");
}

$riskLevel = (int)$riskLevel;
$stocks = getStocksByRisk($riskLevel, $apiKey);

// Display results
if (isset($stocks["error"])) {
    echo $stocks["error"] . "\n";
} else {
    foreach ($stocks as $stock) {
        echo "Ticker: {$stock['symbol']} | Company: {$stock['companyName']} | Beta: {$stock['beta']} | Market Cap: {$stock['marketCap']}\n";
    }
}

?>
