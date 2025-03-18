<?php

set_time_limit(0);  // Allow script to run indefinitely if necessary

if ($argc < 2) {
    exit("No file path provided to insert.\n");
}

// Get the file path passed as argument
$filePath = $argv[1];

// Check if the file exists
if (!file_exists($filePath)) {
    exit("File does not exist: $filePath\n");
}

// Read the file content
$fileContent = file_get_contents($filePath);

// Decode the JSON data into an associative array
$data = json_decode($fileContent, true);

// Check if decoding was successful
if (!$data) {
    exit("Invalid data format: JSON decoding failed.\n");
}

// Debugging: Show the decoded data
echo "Decoded Data:\n";
print_r($data);

function dbConnect() {
    // Connect to the database
    $conn = new mysqli("localhost", "testUser", "12345", "investzero");

    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }

    return $conn;
}

$db = dbConnect();

// Prepare the SQL statement to insert data into PriceHistory table
$stmt = $db->prepare("
    INSERT INTO PriceHistory (ticker, timestamp, open, high, low, close, volume) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    open = VALUES(open), high = VALUES(high), 
    low = VALUES(low), close = VALUES(close), volume = VALUES(volume)
");

foreach ($data as $row) {
    // Prepare the data from each row
    $ticker = $row["ticker"];
    $timestamp = intval($row["timestamp"]);  // Ensure the timestamp is an integer
    $open = floatval($row["open"]);
    $high = floatval($row["high"]);
    $low = floatval($row["low"]);
    $close = floatval($row["close"]);
    $volume = intval($row["volume"]);

    // Bind the parameters correctly for each column in the prepared statement
    // 's' for string (ticker), 'i' for integer (timestamp, volume), 'd' for double (open, high, low, close)
    $stmt->bind_param("siddddi", $ticker, $timestamp, $open, $high, $low, $close, $volume);

    // Execute the prepared statement
    $stmt->execute();
}

// Close the prepared statement and database connection
$stmt->close();
$db->close();

// Delete the file after processing
unlink($filePath);

echo "Data insertion completed and file deleted.\n";
?>
