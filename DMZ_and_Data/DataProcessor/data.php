<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$api_key = trim(file_get_contents("apiKey"));
echo "Using API Key: " . substr($api_key, 0, 5) . "...\n"; // Mask API key for security

function fetch_all_stock_data($ticker, $start, $end) {
    global $api_key;

    // Convert epoch timestamps to YYYY-MM-DD
    $start_date = date("Y-m-d", $start);
    $end_date = date("Y-m-d", $end);

    echo $start_date . $end_date;

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
            return ["returnCode" => '1', "message" => "Failed to fetch stock data"];
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
        return ["returnCode" => '0', "message" => "Stock data found", "data" => $all_data];
    } else {
        echo "No data retrieved.\n";
        return ["returnCode" => '2', "message" => "Stock data not found"];
    }

}

// fetch_all_stock_data("TSLA", 1738969811, 1741654317)
// Example Call (for testing)
// fetch_all_stock_data('VOO', '2025-01-10', '2025-03-10');
?>
