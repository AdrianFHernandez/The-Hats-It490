<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function dbConnect()
{
    $conn = new mysqli("localhost", "testUser", "12345", "investzero");

    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }

    return $conn;
}


function doRegister($name, $username, $email, $password)
{
    $conn = dbConnect();

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT userID FROM Users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ["returnCode" => '1', "message" => "Username or Email already taken"];
    }

    // Hash password securely
    $hashedpass = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = time(); // Store epoch timestamp

    // Insert user into database
    $stmt = $conn->prepare("INSERT INTO Users (username, email, password, created_at, name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $username, $email, $hashedpass, $createdAt, $name);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["returnCode" => '0', "message" => "Registration successful"];
    } else {
        $stmt->close();
        $conn->close();
        return ["returnCode" => '2', "message" => "Registration failed"];
    }
}


function doLogin($username, $password)
{
    $conn = dbConnect();
    
    // Get user from database
    $stmt = $conn->prepare("SELECT userID, password, email, created_at FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashedpass, $email, $createdAt);
        $stmt->fetch();

        if (password_verify($password, $hashedpass)) {
            $stmt->close();
            $conn->close();
            return [
                "returnCode" => '0',
                "message" => "Login successful",
                "user" => [
                    "id" => $id,
                    "username" => $username,
                    "email" => $email,
                    "created_at" => $createdAt
                ]
            ];
        }
    }

    $stmt->close();
    $conn->close();
    return ["returnCode" => '1', "message" => "Invalid username or password"];
}


function validateSession($sessionId)
{
    $conn = dbConnect();
    
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE session_id = ? AND expires_at > ?");
    $currentTime = time();
    $stmt->bind_param("si", $sessionId, $currentTime);
    $stmt->execute();
    $stmt->store_result();

    // If session is valid
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userId);
        $stmt->fetch();

        $stmt = $conn->prepare("SELECT username, email, created_at FROM Users WHERE userID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($username, $email, $createdAt);
        $stmt->fetch();

        $stmt->close();
        $conn->close();

        return ["valid" => true, "user" => ["username" => $username, "email" => $email, "created_at" => $createdAt], "sessionId" => $sessionId];
    }
    
    return ["valid" => false, "error" => "Invalid or expired session"];
}


function createSession($userId)
{
    $conn = dbConnect();
    $sessionId = bin2hex(random_bytes(32)); // Generate a secure session ID
    $expiresAt = time() + (120); // 120 seconds expiration

    $stmt = $conn->prepare("INSERT INTO Sessions (session_id, user_id, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $sessionId, $userId, $expiresAt);
    $stmt->execute();

    $stmt->close();
    $conn->close();



    return ["sessionId" => $sessionId, "expiresAt" => $expiresAt];
}


function clearExpiredSessions()
{
    $conn = dbConnect();
    $currentTime = time();

    $stmt = $conn->prepare("DELETE FROM Sessions WHERE expires_at < ?");
    $stmt->bind_param("i", $currentTime);
    $stmt->execute();

    $stmt->close();
    $conn->close();
}

function doLogout($sessionId)
{
    $conn = dbConnect();
    $stmt = $conn->prepare("DELETE FROM Sessions WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    
    $stmt->close();
    $conn->close();

    return ["success" => true, "message" => "Logout successful"];
}

function doGetAccountInfo($sessionId) {
    $conn = dbConnect();

    // Step 1: Validate session and get userID
    $stmt = $conn->prepare("SELECT user_id FROM Sessions WHERE session_id = ? AND expires_at > ?");
    $currentTime = time();
    $stmt->bind_param("si", $sessionId, $currentTime);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return ["valid" => false, "error" => "Invalid session."];
    }

    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();

    // Step 2: Get user account details
    $stmt = $conn->prepare("SELECT account_id, buying_power, total_balance FROM Accounts WHERE userID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($accountId, $cashBalance, $totalBalance);
    $stmt->fetch();
    $stmt->close();

    // Step 3: Fetch user's stock holdings
    $stmt = $conn->prepare("
        SELECT p.ticker, s.name, s.stock_description, p.quantity, p.average_price
        FROM Portfolios p
        JOIN Stocks s ON p.ticker = s.ticker
        WHERE p.account_id = ?
    ");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();

    $userStocks = [];
    $stockBalance = 0;

    while ($row = $result->fetch_assoc()) {
        $ticker = $row['ticker'];
        $userStocks[$ticker] = [
            "companyName" => $row['name'],
            "companyDescription" => $row['stock_description'],
            "count" => $row['quantity'],
            "averagePrice" => $row['average_price']
        ];
        // Add to stock balance (total value of stocks owned)
        $stockBalance += $row['quantity'] * $row['average_price'];
    }

    $stmt->close();
    $conn->close();

    // Step 4: Return response
    return [
        "valid" => true,
        "user" => [
            "userStocks" => $userStocks,
            "userBalance" => [
                "cashBalance" => $cashBalance,
                "stockBalance" => $stockBalance,
                "totalBalance" => $totalBalance
            ]
        ]
    ];
}

function doGetStockInfo() {
    $conn = dbConnect();

    // Query to get stock details with the latest price
    $query = "
        SELECT s.ticker, s.name, s.stock_description, s.sector, ph.close 
        FROM Stocks s
        JOIN PriceHistory ph ON s.ticker = ph.ticker
        WHERE ph.timestamp = (
            SELECT MAX(timestamp) FROM PriceHistory WHERE ticker = s.ticker
        )
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($ticker, $name, $description, $sector, $price);

    $stocks = [];

    // Fetching data and structuring response
    while ($stmt->fetch()) {
        $stocks[$ticker] = [
            "companyName" => $name,
            "description" => $description,
            "sector" => $sector,
            "price" => $price
        ];
    }

    $stmt->close();
    $conn->close();

    // Returning structured response
    return ["data" => $stocks];
}






?>
