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


function doGetStockInfo($sessionId, $ticker)
{
    if (getUserIDfromSession($sessionId) === null) {
        return buildResponse("GET_STOCK_INFO_RESPONSE", "FAILED", ["error" => "Invalid session"]);
    }

    $conn = dbConnect();

    $query = "
        SELECT ticker, name
        FROM AllStockTickers 
        WHERE ticker LIKE ?
        LIMIT 7
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return buildResponse("GET_STOCK_INFO_RESPONSE", "FAILED", ["error" => "Database error"]);
    }

    // Use '%' wildcard only at the end to match prefixes
    $searchPattern = $ticker . "%";
    $stmt->bind_param("s", $searchPattern);
    $stmt->execute();
    $stmt->bind_result($foundTicker, $name);

    $stocks = [];

    while ($stmt->fetch()) {
        $stocks[$foundTicker] = [
            "ticker" => $foundTicker,
            "companyName" => $name,
        ];
    }

    $stmt->close();
    $conn->close();

    /*
    if (!empty($stocks)) {
        $client = new rabbitMQClient("HatsDMZRabbitMQ.ini", "Server");
        foreach ($stocks as $key => $stock) {
            $request = [
                "type" => "get_latest_price",
                "ticker" => $stock['ticker'],
            ];
            $response = $client->send_request($request);

            if ($response) {
                $stocks[$key]["price"] = $response['close'];    
            }
        }
    }
    */
    return buildResponse("GET_STOCK_INFO_RESPONSE", "SUCCESS", ["data" => $stocks]);
}

function GetStocksBasedOnRisk($sessionId)
{
    // Get user ID from session
    if (($userID = getUserIDfromSession($sessionId)) === null) {
        return buildResponse("GET_STOCKS_BASED_ON_RISK_RESPONSE", "FAILED", ["error" => "Invalid session"]);
    }

    // Get the user's risk level from the database
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT risk_level FROM Users WHERE id = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $riskLevel = $row['risk_level'];
    } else {
        return buildResponse("GET_STOCKS_BASED_ON_RISK_RESPONSE", "FAILED", ["error" => "User not found"]);
    }

    // Get stocks based on risk level
    $stmt = $db->prepare("SELECT stock_symbol FROM Stocks WHERE risk_category = ?");
    $stmt->bind_param("s", $riskLevel);
    $stmt->execute();
    $result = $stmt->get_result();

    $stocks = [];
    while ($row = $result->fetch_assoc()) {
        $stocks[] = $row['stock_symbol'];
    }

    if (empty($stocks)) {
        return buildResponse("GET_STOCKS_BASED_ON_RISK_RESPONSE", "FAILED", ["error" => "No stocks found for this risk level"]);
    }

    return buildResponse("GET_STOCKS_BASED_ON_RISK_RESPONSE", "SUCCESS", ["stocks" => $stocks]);
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



?>