<?php

function getAllStock() {
    $client = new rabbitMQClient("HatsDMZRabbitMQ.ini", "Server");

    $request = [
        "type" => "fetchAllTickers"
    ];
    $response = $client->send_request($request);

    // Decode JSON response
    $data = json_decode($response, true);

    // Check if decoding was successful
    if ($data === null) {
        die("Error decoding JSON data from RabbitMQ.");
    }

    // Connect to the database
    $conn = dbConnect();

    // Delete the allStockTickers table if it exists
    $dropTableQuery = "DROP TABLE IF EXISTS allStockTickers";
    if (!$conn->query($dropTableQuery)) {
        die("Error dropping table: " . $conn->error);
    }

    // Recreate the allStockTickers table
    $createTableQuery = "CREATE TABLE allStockTickers (
        cik INT PRIMARY KEY,
        ticker VARCHAR(10) NOT NULL,
        name VARCHAR(255) NOT NULL
    )";

    if (!$conn->query($createTableQuery)) {
        die("Error creating table: " . $conn->error);
    }

    // Prepare SQL statement to insert data
    $stmt = $conn->prepare("INSERT INTO allStockTickers (cik, ticker, name) VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE ticker = VALUES(ticker), name = VALUES(name)");

    if (!$stmt) {
        die("Error preparing SQL statement: " . $conn->error);
    }

    $high_risk_sectors = ['Technology', 'Biotech', 'Crypto'];
    $low_risk_sectors = ['Utilities', 'Consumer Staples', 'Healthcare'];

    if (in_array($sector, $high_risk_sectors)) {
        return 'high';
    } elseif (in_array($sector, $low_risk_sectors)) {
        return 'low';
    } else {
        return 'medium';
    }


    // Insert each stock ticker into the database
    foreach ($data as $stock) {
        $cik = $stock['cik_str'] ?? null;
        $ticker = $stock['ticker'] ?? null;
        $name = $stock['title'] ?? null;

        if ($cik && $ticker && $name) {
            $stmt->bind_param("iss", $cik, $ticker, $name);
            $stmt->execute();
        }
    }

    // Close resources
    $stmt->close();
    $conn->close();

    echo "Stock data inserted successfully into allStockTickers!";
}

?>
