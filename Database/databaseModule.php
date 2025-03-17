<?php
require_once 'path.inc';
require_once 'get_host_info.inc';
require_once 'rabbitMQLib.inc';

$client = null;

function getClientForDMZ()
{
    global $client;
    if ($client === null) {
        $client = new rabbitMQClient("HatsDMZRabbitMQ.ini", "Server");

    }
    return $client;
}


function buildRequest($type, $payload = []){
    return [
        "type" => $type,
        "timestamp" => time(),
        "payload" => $payload
    ];
}
function buildResponse($type, $status, $payload = [])
{
    return [
        "type" => $type,
        "timestamp" => time(),
        "status" => $status,
        "payload" => $payload
    ];
}


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
        return buildResponse("REGISTER_RESPONSE", "FAILED", ["message" => "Username or email already exists"]);
    }

    // Hash password securely
    $hashedpass = password_hash($password, PASSWORD_DEFAULT);
    $createdAt = time(); // Store epoch timestamp

    // Insert user into database
    $stmt = $conn->prepare("INSERT INTO Users (username, email, password, created_at, name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $username, $email, $hashedpass, $createdAt, $name);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Creating Trading account for the user automatically
        $stmt = $conn->prepare("INSERT INTO Accounts (user_id) VALUES (?)");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $account_id = $stmt->insert_id;
            $stmt->close();
            $conn->close();
            return buildResponse("REGISTER_RESPONSE", "SUCCESS", [
                "message" => "Registration successful! Trading account created.",
                "user_id" => $user_id,
                "account_id" => $account_id
            ]);
        } else {
            $stmt->close();
            $conn->close();
            return buildResponse("REGISTER_RESPONSE", "FAILED", ["message" => "Trading account creation failed, Registraction Success"]);
        }

    } else {
        $stmt->close();
        $conn->close();
        return buildResponse("REGISTER_RESPONSE", "FAILED", ["message" => "Registration failed"]);

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
            
            return buildResponse("LOGIN_RESPONSE", "SUCCESS", [
                "message" => "Login successful",
                "user" => [
                    "id" => $id,
                    "username" => $username,
                    "email" => $email,
                    "created_at" => $createdAt
                ]
            ]);
        }
    }

    $stmt->close();
    $conn->close();
    return buildResponse("LOGIN_RESPONSE", "FAILED", ["message" => "Invalid username or password"]);
}


function validateSession($sessionId)
{
    $conn = dbConnect();

    $stmt = $conn->prepare("SELECT user_id FROM Sessions WHERE session_id = ? AND expires_at > ?");
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

        return buildResponse("VALIDATE_SESSION_RESPONSE", "SUCCESS", [
            "valid" => true,
            "user" => [
                "id" => $userId,
                "username" => $username,
                "email" => $email,
                "created_at" => $createdAt
            ]
        ]);
    }

    return buildResponse("VALIDATE_SESSION_RESPONSE", "FAILED", ["valid" => false, "error" => "Invalid session."]);
}


function createSession($userId)
{
    $conn = dbConnect();
    $sessionId = bin2hex(random_bytes(32)); // Generate a secure session ID
    $expiresAt = time() + (60 * 5); // 5 minutes expiration

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

    return buildResponse("LOGOUT_RESPONSE", "SUCCESS", ["message" => "Logout successful"]);
}

function doGetAccountInfo($sessionId)
{
    // Step 1: Get user ID from session
    if (($userId = getUserIDfromSession($sessionId)) === null) {
        return buildResponse("GET_ACCOUNT_INFO_RESPONSE", "FAILED", ["error" => "Invalid session"]);
    }

    $conn = dbConnect();

    // Step 2: Get user account details
    $stmt = $conn->prepare("SELECT account_id, buying_power, total_balance FROM Accounts WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->store_result(); // Ensure we can check if data exists

    // Check if account exists
    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return buildResponse("GET_ACCOUNT_INFO_RESPONSE", "FAILED", ["error" => "User account not found"]);
    }

    $stmt->bind_result($accountId, $cashBalance, $totalBalance);
    $stmt->fetch();
    $stmt->close();

    // Step 3: Fetch user's stock holdings
    $stmt = $conn->prepare("
        SELECT p.ticker, s.name, s.description, p.quantity, p.average_price
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
            "companyDescription" => $row['description'],
            "count" => $row['quantity'],
            "averagePrice" => $row['average_price']
        ];
        // Add to stock balance (total value of stocks owned)
        $stockBalance += $row['quantity'] * $row['average_price'];
    }

    $stmt->close();
    $conn->close();

    // Step 4: Return response
    return buildResponse("GET_ACCOUNT_INFO_RESPONSE", "SUCCESS", [
        "user" => [
            "userStocks" => $userStocks,
            "userBalance" => [
                "cashBalance" => $cashBalance,
                "stockBalance" => $stockBalance,
                "totalBalance" => $totalBalance
            ]
        ]
    ]);
}


function doGetStockInfo($sessionId, $payload)
{
    if (getUserIDfromSession($sessionId) === null) {
        return buildResponse("GET_STOCK_INFO_RESPONSE", "FAILED", ["error" => "Invalid session"]);
    }

    $conn = dbConnect();

    $ticker = $payload['ticker'] ?? '';
    $marketCapMin = $payload['marketCapMin'] ?? '';
    $marketCapMax = $payload['marketCapMax'] ?? '';
    
    if ($ticker) {
        $query = "
        SELECT ticker, name, marketCap, sector, industry, price, exchange
        FROM AllStockTickers 
        WHERE ticker LIKE ?
        LIMIT 7
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return buildResponse("GET_STOCK_INFO_RESPONSE", "FAILED", ["error" => "Database error"]);
        }
        
        $searchPattern = $ticker . "%";
        $stmt->bind_param("s", $searchPattern);
    } else {
        if (!is_numeric($marketCapMin) || !is_numeric($marketCapMax)) {
            return buildResponse("GET_STOCK_INFO_RESPONSE", "FAILED", ["error" => "Invalid market cap range"]);
        }
        
        $query = "
        SELECT ticker, name, marketCap, sector, industry, price, exchange 
        FROM STOCKS 
        WHERE marketCap >= ? AND marketCap <= ?
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return buildResponse("GET_STOCK_INFO_RESPONSE", "FAILED", ["error" => "Database error"]);
        }
        
        $stmt->bind_param("dd", $marketCapMin, $marketCapMax);
    }
    
    $stmt->execute();
    $stmt->bind_result($foundTicker, $name, $marketCap, $sector, $industry, $price, $exchange);

    $stocks = [];

    while ($stmt->fetch()) {
        $stocks[] = [
            "ticker" => $foundTicker,
            "companyName" => $name,
            "marketCap" => $marketCap,
            "sector" => $sector,
            "industry" => $industry,
            "price" => $price,
            "exchange" => $exchange
        ];
    }

    $stmt->close();
    $conn->close();

    return buildResponse("GET_STOCK_INFO_RESPONSE", "SUCCESS", ["data" => $stocks]);
}

function GetStocksBasedOnRisk($sessionId)
{
    if ($userID = getUserIDfromSession($sessionId) === null) {
        return buildResponse("GET_STOCKS_BASED_ON_RISK_RESPONSE", "FAILED", ["error" => "Invalid session"]);
    }
    
    return buildResponse("GET_STOCKS_BASED_ON_RISK_RESPONSE", "SUCCESS", ["stocks" => ["AAPL", "GOOGL", "AMZN", "TSLA", "MSFT"]]);
}


function getUserIDfromSession(string $sessionId){
    # Connect to database and check if the session is valid, if it is return userID, else return null;
    $conn = dbConnect();
    $stmt = $conn->prepare("SELECT user_id FROM Sessions WHERE session_id = ? AND expires_at > ?");
    $currentTime = time();
    $stmt->bind_param("si", $sessionId, $currentTime);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return null;
    }

    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();
    $conn->close();
    return $userId;
}


function performTransaction($sessionId, $ticker, $quantity, $price, $transactionType) {
    
    if (($userId = getUserIDfromSession($sessionId)) === null) {
        return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => "Invalid or expired session."]);
    }
    $db = dbConnect();

    // Step 2: Get accountID from userID
    $stmt = $db->prepare("SELECT account_id FROM Accounts WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($accountId);
    $stmt->fetch();
    $stmt->close();

    if (!$accountId) {
        return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => "User has no trading account."]);
    }

    // Step 3: Process Transaction 
    $db->begin_transaction();
    try {
        if ($transactionType === 'BUY') {
            // Check Buying Power
            $stmt = $db->prepare("SELECT buying_power FROM Accounts WHERE account_id = ?");
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $stmt->bind_result($buyingPower);
            $stmt->fetch();
            $stmt->close();

            $cost = $quantity * $price;
            if ($buyingPower < $cost) {
                throw new Exception("Insufficient buying power.");
            }

            // Deduct buying power
            $stmt = $db->prepare("UPDATE Accounts SET buying_power = buying_power - ?, total_balance = total_balance - ? WHERE account_id = ?");
            $stmt->bind_param("ddi", $cost, $cost, $accountId);
            $stmt->execute();
            $stmt->close();

            // Update Portfolio
            $stmt = $db->prepare("INSERT INTO Portfolios (account_id, ticker, quantity, average_price) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                quantity = quantity + VALUES(quantity), 
                average_price = ((quantity * average_price) + (VALUES(quantity) * VALUES(average_price))) / (quantity + VALUES(quantity))");
            $stmt->bind_param("isid", $accountId, $ticker, $quantity, $price);
            $stmt->execute();
            $stmt->close();
        } 
        elseif ($transactionType === 'SELL') {
            // Check Portfolio Holdings
            $stmt = $db->prepare("SELECT quantity FROM Portfolios WHERE account_id = ? AND ticker = ?");
            $stmt->bind_param("is", $accountId, $ticker);
            $stmt->execute();
            $stmt->bind_result($currentShares);
            $stmt->fetch();
            $stmt->close();

            if ($currentShares < $quantity) {
                throw new Exception("Not enough shares to sell.");
            }

            // Update Portfolio
            $remainingShares = $currentShares - $quantity;
            if ($remainingShares == 0) {
                $stmt = $db->prepare("DELETE FROM Portfolios WHERE account_id = ? AND ticker = ?");
                $stmt->bind_param("is", $accountId, $ticker);
            } else {
                $stmt = $db->prepare("UPDATE Portfolios SET quantity = ? WHERE account_id = ? AND ticker = ?");
                $stmt->bind_param("iis", $remainingShares, $accountId, $ticker);
            }
            $stmt->execute();
            $stmt->close();

            // Add money to buying power
            $profit = $quantity * $price;
            $stmt = $db->prepare("UPDATE Accounts SET buying_power = buying_power + ?, total_balance = total_balance + ? WHERE account_id = ?");
            $stmt->bind_param("ddi", $profit, $profit, $accountId);
            $stmt->execute();
            $stmt->close();
        }

        $transactionType = strtoupper(trim($transactionType)); // Ensure valid ENUM

        if (!in_array($transactionType, ['BUY', 'SELL'])) {
            return buildResponse("TRANSACTION_RESPONSE", "FAILED", ["message" => "Invalid transaction type"]);
        }
        
        $timestamp = time();
       
        $stmt = $db->prepare("INSERT INTO Transactions (account_id, ticker, quantity, price, transaction_type, timestamp) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isidss", $accountId, $ticker, $quantity, $price, $transactionType, $timestamp);
        $stmt->execute();
        $stmt->close();
        

        $db->commit();
        return buildResponse("PERFORM_TRANSACTION_RESPONSE", "SUCCESS", ["message" => "Transaction completed successfully"]);
    } catch (Exception $e) {
        $db->rollback();
        return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => $e->getMessage() . $transactionType]);
    } finally {
        $db->close();
    }
}


?>
