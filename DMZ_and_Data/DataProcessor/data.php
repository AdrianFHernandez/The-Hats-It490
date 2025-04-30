<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// 3WE0LDXUCWaW7auFquUZB6UlN6BQ41sn
function buildResponse($type, $status, $payload = [])
{
    return [
        "type" => $type,
        "timestamp" => time(),
        "status" => $status,
        "payload" => $payload
    ];
}

$api_key = trim(file_get_contents("apiKey"));
echo "Using API Key: " . substr($api_key, 0, 5) . "...\n"; 

function fetchAllTickers()
{
    $url = "https://www.sec.gov/files/company_tickers.json";
    $options = [
        "http" => [
            "header" => "User-Agent: MyTradingApp/1.0 (myemail@example.com)\r\n"
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        return buildResponse("FETCH_ALL_TICKERS_RESPONSE", "FAILED", ["message" => "Error fetching SEC company tickers"]);
    }

    // Convert JSON to PHP array
    $data = json_decode($response, true);

    if (!$data) {
        return buildResponse("FETCH_ALL_TICKERS_RESPONSE", "FAILED", ["message" => "Failed to decode JSON data"]);
    }

    // Extract only ticker and title
    $filteredData = [];
    foreach ($data as $stock) {
        $filteredData[] = [
            "ticker" => $stock['ticker'],
            "name" => $stock['title']
        ];
    }


    return buildResponse("FETCH_ALL_TICKERS_RESPONSE", "SUCCESS", ["data" => $filteredData, "message" => "Sent tickers to databaseProcessor"]);
}

function fetch_specific_stock_chart_data($ticker, $start_date, $end_date) {
    global $api_key;

    // // Convert epoch timestamps to YYYY-MM-DD
    // $start_date = date("Y-m-d", $start);
    // $end_date = date("Y-m-d", $end);


    // API URL with correct date format
    $base_url = "https://api.polygon.io/v2/aggs/ticker/$ticker/range/1/minute/$start_date/$end_date?sort=asc&limit=50000&";
    $headers = [
        "Authorization: Bearer $api_key"
    ];

    $all_data = [];
    $params = [
        "apiKey" => $api_key
    ];

    while (true) {
        $url = $base_url . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle HTTP errors
        if ($http_code != 200) {
            echo "Error fetching data: HTTP $http_code - Response: $response\n";
            return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "FAILED", ["message" => "Failed to fetch stock data"]);
        }

        $data = json_decode($response, true);

        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $record) {
                if (isset($record['t'], $record['o'], $record['h'], $record['l'], $record['c'], $record['v'])) {
                    $adjusted_timestamp = intval($record['t'] / 1000) - (5 * 3600); // Convert ms to sec & subtract 5 hours

                    $all_data[] = [
                        "ticker" => $ticker,
                        "timestamp" => $adjusted_timestamp, // Epoch format with -5h adjustment
                        "open" => floatval($record['o']),
                        "high" => floatval($record['h']),
                        "low" => floatval($record['l']),
                        "close" => floatval($record['c']),
                        "volume" => intval($record['v'])
                    ];
                }
            }

            // Check for pagination
            if (isset($data['next'])) {
                $params["next"] = $data["next"];
                echo "Fetching next page: " . $data['next'] . "\n";
                sleep(3);
            } else {
                break;
            }
        } else {
            echo "No more data available.\n";
            break;
        }
    }
    // print_r($all_data);
    if (!empty($all_data)) {
        return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "SUCCESS", ["data" => $all_data, "message" => "Stock data found"]);
        
    } else {
        echo "No data retrieved.\n";
        return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "FAILED", ["message" => "No stock data found"]);
    }

}

function delayed_latest_price($ticker) {
    global $api_key;

    $url = "https://api.polygon.io/v2/aggs/ticker/$ticker/prev?apiKey=$api_key";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

  
    if ($http_code != 200) {
        echo "Error fetching latest price: HTTP $http_code - Response: $response\n";
        return ["returnCode" => '1', "message" => "Failed to fetch latest price"];
    }

    $data = json_decode($response, true);

    if (isset($data['results']) && is_array($data['results']) && count($data['results']) > 0) {
        $latest = $data['results'][0];
        print_r($latest);
        return [
            "returnCode" => '0',
            "message" => "Latest price retrieved",
            "ticker" => $ticker,
            "timestamp" => intval($latest['t'] / 1000),
            "open" => floatval($latest['o']),
            "high" => floatval($latest['h']),
            "low" => floatval($latest['l']),
            "close" => floatval($latest['c']),
            "volume" => intval($latest['v'])
        ];
    } else {
        return ["returnCode" => '2', "message" => "No price data found for $ticker"];
    }
}

function getRecommendedStocks($riskLevel) {
    $baseUrl = "https://financialmodelingprep.com/api/v3/stock-screener";
    $apiKey = "";
    // Configure query parameters based on risk level
    switch ($riskLevel) {
        case 1:
            $queryParams = [
                'betaLowerThan' => 1,
                'marketCapMoreThan' => 10000000000,
                'isActivelyTrading' => 'true',
                'limit' => 10,
                'apikey' => $apiKey
            ];
            break;
        case 2:
            $queryParams = [
                'betaMoreThan' => 1,
                'betaLowerThan' => 2,
                'marketCapMoreThan' => 2000000000,
                'marketCapLowerThan' => 10000000000,
                'isActivelyTrading' => 'true',
                'limit' => 10,
                'apikey' => $apiKey
            ];
            break;
        case 3:
            $queryParams = [
                'betaMoreThan' => 2,
                'marketCapLessThan' => 2000000000,
                'isActivelyTrading' => 'true',
                'limit' => 10,
                'apikey' => $apiKey
            ];
            break;
        default:
            return buildResponse("GET_RECOMMENDED_STOCKS_RESPONSE", "FAILED", ["message" => "Invalid risk level. Choose 1 (Conservative), 2 (Casual), or 3 (Risky)"]);
    }

    // Build the URL with query parameters
    $url = $baseUrl . '?' . http_build_query($queryParams);

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle HTTP errors
    if ($http_code != 200) {
        return buildResponse("GET_RECOMMENDED_STOCKS_RESPONSE", "FAILED", ["message" => "Error fetching stocks: HTTP $http_code - Response: $response"]);
    }

    // Decode JSON response
    $data = json_decode($response, true);

    // Handle JSON parsing errors
    if (!$data) {
        return buildResponse("GET_RECOMMENDED_STOCKS_RESPONSE", "FAILED", ["message" => "Failed to decode JSON data"]);
    }

    // Process data if available
    if (is_array($data) && count($data) > 0) {
        $filteredData = array_map(function ($stock) {
            return [
                "ticker" => $stock['symbol'],
                "name" => $stock['companyName'] ?? "N/A",
                "marketCap" => $stock['marketCap'],
                "beta" => $stock['beta']
            ];
        }, $data);
        
        return buildResponse("GET_RECOMMENDED_STOCKS_RESPONSE", "SUCCESS", ["data" => $filteredData, "message" => "Stocks data retrieved successfully"]);
    } else {
        return buildResponse("GET_RECOMMENDED_STOCKS_RESPONSE", "FAILED", ["message" => "No stocks data found"]);
    }
}

function getChatbotAnswer($question) {
    $api = ''; // Your actual API key
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

    // AIzaSyBLFwBnV4d2fCaY_nGquyAPoHeLnL5tE4o
    // to the question add "less than 100 words" to get a concise answer

    $postData = json_encode([
        "contents" => [
            [
                "parts" => [
                    ["text" => $question]
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        return buildResponse("GET_CHATBOT_ANSWER_RESPONSE", "FAILED", ["message" => "Error fetching response: HTTP $http_code"]);
    }

    $responseData = json_decode($response, true);

    if (!$responseData) {
        return buildResponse("GET_CHATBOT_ANSWER_RESPONSE", "FAILED", ["message" => "Failed to decode JSON data"]);
    }

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $answerText = $responseData['candidates'][0]['content']['parts'][0]['text'];
        $citations = [];
        if (isset($responseData['candidates'][0]['citationMetadata']['citationSources'])) {
            $sources = $responseData['candidates'][0]['citationMetadata']['citationSources'];
            foreach ($sources as $index => $source) {
                if ($index < 2) { 
                    $citations[] = $source['uri'] ?? "Citation from characters " . $source['startIndex'] . " to " . $source['endIndex'];
                }
            }
        }
        return buildResponse("GET_CHATBOT_ANSWER_RESPONSE", "SUCCESS", [
            "answer" => $answerText,
            "citations" => $citations,
            "message" => "Answer retrieved successfully"
        ]);
    } else {
        return buildResponse("GET_CHATBOT_ANSWER_RESPONSE", "FAILED", ["message" => "No answer found in the response"]);
    }
}

function getNews(){
    
}

// fetch_all_stock_data("TSLA", 1738969811, 1741654317)
// Example Call (for testing)
// $data = fetch_specific_stock_chart_data('VOO', strtotime('2025-02-01'), strtotime('2025-02-14'));
// dump into a file json
// file_put_contents('stock_data.json', json_encode($data, JSON_PRETTY_PRINT));
?>
