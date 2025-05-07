<?php
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start(); // Only if needed for user authentication

// Dummy data (replace with DB query for actual user transactions)
$transactions = [
    ['date' => '2025-05-01', 'stock' => 'AAPL', 'quantity' => 10, 'price' => 175],
    ['date' => '2025-05-02', 'stock' => 'GOOGL', 'quantity' => 5, 'price' => 2700],
];

$html = "<h1>User Transactions</h1><table border='1' cellspacing='0' cellpadding='5'>";
$html .= "<tr><th>Date</th><th>Stock</th><th>Quantity</th><th>Price</th></tr>";
foreach ($transactions as $txn) {
    $html .= "<tr><td>{$txn['date']}</td><td>{$txn['stock']}</td><td>{$txn['quantity']}</td><td>\${$txn['price']}</td></tr>";
}
$html .= "</table>";

$options = new Options();
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("transactions.pdf", ["Attachment" => true]);
