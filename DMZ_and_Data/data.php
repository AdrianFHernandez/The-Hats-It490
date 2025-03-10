<?php

$api_key = trim(file_get_contents("apiKey"));
echo $api_key . "\n";

function fetch_all_stock_data($ticker, $start, $end, $filename = "stock_data.json") {
    global $api_key;
    
    $base_url = "https://api.polygon.io/v2/aggs/ticker/$ticker/range/1/minute/$start/$end?sort=asc&limit=50000&";
    $headers = [
        "Authorization: Bearer $api_key"
    ];
    
    $all_data = [];
    $params = [
        "apiKey" => $api_key
    ];

    while (true) {
        // Build complete URL with parameters
        $url = $base_url . http_build_query($params);
        
        // Initialize and execute cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200) {
            echo "Error: $http_code, $response\n";
            break;
        }

        $data = json_decode($response, true);
        if (isset($data['results'])) {
            foreach ($data['results'] as $record) {
                // Convert timestamp from ms to seconds, then subtract 5 hours (in seconds) to go from UTC +5 to UTC
                if (isset($record['t'])) {
                    $timestamp = $record['t'] / 1000;
                    $adjusted = $timestamp - (5 * 3600);
                    $record['t'] = date("Y-m-d H:i:s", $adjusted);
                }
                $all_data[] = $record;
            }
            

            if (isset($data['next_url'])) {
                $params["next_url"] = $data["next_url"];
                echo "Fetching next page: " . $data['next_url'] . "\n";
                sleep(3);
            } else {
                break;
            }
        } else {
            echo "No more data available.\n";
            break;
        }
    }

    // Save to JSON file
    if (!empty($all_data)) {
        file_put_contents($filename, json_encode($all_data, JSON_PRETTY_PRINT));
        echo "Stock data saved to $filename\n";
    } else {
        echo "No data retrieved.\n";
    }
}


fetch_all_stock_data('VOO', '2025-01-10', '2025-03-10', "tmp/stock_data.json");
?>
