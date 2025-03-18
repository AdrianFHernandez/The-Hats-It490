<?php
require_once('databaseModule.php');


// Get database connection
$db = getDatabaseConnection();

// Fetch all stocks that users hold
$query = "SELECT DISTINCT ticker FROM Portfolios";
$result = $db->query($query);

if (!$result) {
    die("Error fetching portfolio stocks: " . $db->error);
}

$stocks = [];
while ($row = $result->fetch_assoc()) {
    $stocks[] = $row['ticker'];
}

// Fetch and insert stock history
foreach ($stocks as $stock) {
    echo "Fetching history for: $stock\n";
    
    $history = fetchStockHistory($stock); // Assume this function exists and returns an array
    
    if (!$history) {
        echo "No history found for $stock\n";
        continue;
    }
    
    // Insert stock history into database
    $stmt = $db->prepare("INSERT INTO PriceHistory (ticker, timestamp, open, close, high, low, volume) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($history as $data) {
        $stmt->bind_param("ssddddd", $stock, $data['date'], $data['open'], $data['close'], $data['high'], $data['low'], $data['volume']);
        $stmt->execute();
    }
    
    echo "Inserted history for $stock\n";
}

$stmt->close();
$db->close();

echo "Stock history update complete.\n";
?>
