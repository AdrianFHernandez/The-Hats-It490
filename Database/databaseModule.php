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
    // 10 minutes from now it will expire
    $expiresAt = time() + 600;

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
        "data" => ["user" => [
            "userStocks" => $userStocks,
            "userBalance" => [
                "cashBalance" => $cashBalance,
                "stockBalance" => $stockBalance,
                "totalBalance" => $totalBalance
            ]
        ]]
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
        FROM Stocks 
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
        FROM Stocks 
        WHERE marketCap >= ? AND marketCap <= ? LIMIT 7
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



function getUserIDfromSession($sessionId) {
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



function updateTotalBalance($db, $accountId) {
    $buyingPower = 0.0;
    $portfolioValue = 0.0;
    // Get Buying Power
    $stmt = $db->prepare("SELECT buying_power FROM Accounts WHERE account_id = ?");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $stmt->bind_result($buyingPower);
    $stmt->fetch();
    $stmt->close();

    // Ensure buyingPower has a default value
    if ($buyingPower === null) {
        $buyingPower = 0.0; // Default to 0 if no record is found
    }

    // Get Portfolio Value (SUM(quantity * average_price))
    $stmt = $db->prepare("SELECT COALESCE(SUM(quantity * average_price), 0) FROM Portfolios WHERE account_id = ?");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $stmt->bind_result($portfolioValue);
    $stmt->fetch();
    $stmt->close();

    // Ensure portfolioValue is not null
    if ($portfolioValue === null) {
        $portfolioValue = 0.0;
    }

    // Compute total_balance
    $totalBalance = $buyingPower + $portfolioValue;

    // Update total_balance in Accounts table
    $stmt = $db->prepare("UPDATE Accounts SET total_balance = ? WHERE account_id = ?");
    $stmt->bind_param("di", $totalBalance, $accountId);
    $stmt->execute();
    $stmt->close();
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
            $stmt = $db->prepare("UPDATE Accounts SET buying_power = buying_power - ? WHERE account_id = ?");
            $stmt->bind_param("di", $cost, $accountId);
            $stmt->execute();
            $stmt->close();

            // Update Portfolio (average price calculation)
            $stmt = $db->prepare("INSERT INTO Portfolios (account_id, ticker, quantity, average_price) 
                VALUES (?, ?, ?, ?)
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
            $stmt = $db->prepare("UPDATE Accounts SET buying_power = buying_power + ? WHERE account_id = ?");
            $stmt->bind_param("di", $profit, $accountId);
            $stmt->execute();
            $stmt->close();
        }

        // Update total_balance after modifying portfolio and buying power
        updateTotalBalance($db, $accountId);

        $timestamp = time();
        $stmt = $db->prepare("INSERT INTO Transactions (account_id, ticker, quantity, price, transaction_type, timestamp) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isidss", $accountId, $ticker, $quantity, $price, $transactionType, $timestamp);
        $stmt->execute();
        $stmt->close();

        // Fetch user details
        $stmt = $db->prepare("SELECT userID, name, email, username, created_at FROM Users WHERE userID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userDetails = $result->fetch_assoc();
        $stmt->close();

        $db->commit();

        return buildResponse("PERFORM_TRANSACTION_RESPONSE", "SUCCESS", [
            "message" => "Transaction completed successfully",
            "user" => $userDetails
        ]);
    } catch (Exception $e) {
        $db->rollback();
        return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => $e->getMessage()]);
    } finally {
        $db->close();
    }
}
// function performTransaction($sessionId, $ticker, $quantity, $price, $transactionType) {
//     if (($userId = getUserIDfromSession($sessionId)) === null) {
//         return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => "Invalid or expired session."]);
//     }
//     $db = dbConnect();

//     // Step 2: Get accountID from userID
//     $stmt = $db->prepare("SELECT account_id FROM Accounts WHERE user_id = ?");
//     $stmt->bind_param("i", $userId);
//     $stmt->execute();
//     $stmt->bind_result($accountId);
//     $stmt->fetch();
//     $stmt->close();

//     if (!$accountId) {
//         return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => "User has no trading account."]);
//     }

//     $db->begin_transaction();
//     try {
//         if ($transactionType === 'BUY') {
//             // Check Buying Power
//             $stmt = $db->prepare("SELECT buying_power FROM Accounts WHERE account_id = ?");
//             $stmt->bind_param("i", $accountId);
//             $stmt->execute();
//             $stmt->bind_result($buyingPower);
//             $stmt->fetch();
//             $stmt->close();

//             $cost = $quantity * $price;
//             if ($buyingPower < $cost) {
//                 throw new Exception("Insufficient buying power.");
//             }

//             // Deduct buying power (
//             $stmt = $db->prepare("UPDATE Accounts SET buying_power = buying_power - ? WHERE account_id = ?");
//             $stmt->bind_param("di", $cost, $accountId);
//             $stmt->execute();
//             $stmt->close();

//             // Update Portfolio (average price calculation)
//             $stmt = $db->prepare("INSERT INTO Portfolios (account_id, ticker, quantity, average_price) 
//                 VALUES (?, ?, ?, ?)
//                 ON DUPLICATE KEY UPDATE 
//                 quantity = quantity + VALUES(quantity), 
//                 average_price = ((quantity * average_price) + (VALUES(quantity) * VALUES(average_price))) / (quantity + VALUES(quantity))");
//             $stmt->bind_param("isid", $accountId, $ticker, $quantity, $price);
//             $stmt->execute();
//             $stmt->close();
//         } 
//         elseif ($transactionType === 'SELL') {
//             // Check Portfolio Holdings
//             $stmt = $db->prepare("SELECT quantity FROM Portfolios WHERE account_id = ? AND ticker = ?");
//             $stmt->bind_param("is", $accountId, $ticker);
//             $stmt->execute();
//             $stmt->bind_result($currentShares);
//             $stmt->fetch();
//             $stmt->close();

//             if ($currentShares < $quantity) {
//                 throw new Exception("Not enough shares to sell.");
//             }

//             // Update Portfolio
//             $remainingShares = $currentShares - $quantity;
//             if ($remainingShares == 0) {
//                 $stmt = $db->prepare("DELETE FROM Portfolios WHERE account_id = ? AND ticker = ?");
//                 $stmt->bind_param("is", $accountId, $ticker);
//             } else {
//                 $stmt = $db->prepare("UPDATE Portfolios SET quantity = ? WHERE account_id = ? AND ticker = ?");
//                 $stmt->bind_param("iis", $remainingShares, $accountId, $ticker);
//             }
//             $stmt->execute();
//             $stmt->close();

//             // Add money to buying power
//             $profit = $quantity * $price;
//             $stmt = $db->prepare("UPDATE Accounts SET buying_power = buying_power + ? WHERE account_id = ?");
//             $stmt->bind_param("di", $profit, $accountId);
//             $stmt->execute();
//             $stmt->close();
//         }

//         // Update total_balance after modifying portfolio and buying power
//         updateTotalBalance($db, $accountId);

//         $timestamp = time();
//         $stmt = $db->prepare("INSERT INTO Transactions (account_id, ticker, quantity, price, transaction_type, timestamp) 
//                               VALUES (?, ?, ?, ?, ?, ?)");
//         $stmt->bind_param("isidss", $accountId, $ticker, $quantity, $price, $transactionType, $timestamp);
//         $stmt->execute();
//         $stmt->close();

//         $db->commit();
//         return buildResponse("PERFORM_TRANSACTION_RESPONSE", "SUCCESS", ["message" => "Transaction completed successfully"]);
//     } catch (Exception $e) {
//         $db->rollback();
//         return buildResponse("PERFORM_TRANSACTION_RESPONSE", "FAILED", ["message" => $e->getMessage()]);
//     } finally {
//         $db->close();
//     }
// }

function fetchSpecificStockData($sessionId, $ticker, $startTime, $endTime) {
    // Validate session
    if (($userId = getUserIDfromSession($sessionId)) === null) {
        return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "FAILED", ["message" => "Invalid or expired session."]);
    }

    //  Ensure `startTime` and `endTime` are converted correctly
    $startTime = is_numeric($startTime) ? intval($startTime) : strtotime($startTime);
    $endTime = is_numeric($endTime) ? intval($endTime) : strtotime($endTime);

    //  Ensure `endTime` is later than `startTime`
    if ($endTime < $startTime) {
        return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "FAILED", ["message" => "End time must be later than start time."]);
    }

    //  Ensure `startTime` is not older than January 1, 2024
    $minStartTime = strtotime("2024-01-01 00:00:00");
    if ($startTime < $minStartTime) {
        $startTime = $minStartTime;
    }

    //  Ensure `startTime` is within 21 days of `endTime`
    $maxAllowedStartTime = $endTime - (21 * 24 * 60 * 60); // 21 days before `endTime`
    if ($startTime < $maxAllowedStartTime) {
        $startTime = $maxAllowedStartTime;
    }

    // Connect to the database once
    $db = dbConnect();

    // Fetch data from PriceHistory
    $stmt = $db->prepare("SELECT * FROM PriceHistory WHERE ticker = ? AND timestamp BETWEEN ? AND ?");
    $stmt->bind_param("sii", $ticker, $startTime, $endTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);  // Fetch all rows for PriceHistory
    $stmt->close();

    // Fetch data from Stocks
    $stmt = $db->prepare("SELECT * FROM Stocks WHERE ticker = ?");
    $stmt->bind_param("s", $ticker);
    $stmt->execute();
    $result = $stmt->get_result();
    $dataStock = $result->fetch_assoc();  // Fetch single row for Stocks
    $stmt->close();

    // Close the database connection once
    $db->close();

    //  Return if data exists in DB
    if (!empty($data)) {
        return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "SUCCESS", ["data" => ["stockData" => $data, "stockInfo" => $dataStock], "message" => "Stock data fetched from database."]);
    }

    //  Fetch from external API if DB has no data
    $client = getClientForDMZ();
    $formattedStartTime = date("Y-m-d", $startTime);
    $formattedEndTime = date("Y-m-d", $endTime);
    
    $request = buildRequest("FETCH_SPECIFIC_STOCK_DATA", ["ticker" => $ticker, "startTime" => $formattedStartTime, "endTime" => $formattedEndTime]);
    $response = $client->send_request($request);

    //  If external API provides data, store it in DB
    if ($response && $response["status"] === "SUCCESS" && !empty($response["payload"]["data"])) {
        // $db = dbConnect();
        // $stmt = $db->prepare("
        //     INSERT INTO PriceHistory (ticker, timestamp, open, high, low, close, volume) 
        //     VALUES (?, ?, ?, ?, ?, ?, ?)
        //     ON DUPLICATE KEY UPDATE 
        //     open = VALUES(open), high = VALUES(high), 
        //     low = VALUES(low), close = VALUES(close), volume = VALUES(volume)
        // ");

        // foreach ($response["payload"]["data"] as $row) {
        //     $ticker = $row["ticker"];
        //     $timestamp = intval($row["timestamp"]);
        //     $open = floatval($row["open"]);
        //     $high = floatval($row["high"]);
        //     $low = floatval($row["low"]);
        //     $close = floatval($row["close"]);
        //     $volume = intval($row["volume"]);

        //     $stmt->bind_param("siddddi", $ticker, $timestamp, $open, $high, $low, $close, $volume);
        //     $stmt->execute();
        // }

        // $stmt->close();
        // $db->close();
        $tempFile = tempnam(sys_get_temp_dir(), 'stock_data_');
        file_put_contents($tempFile, json_encode($response["payload"]["data"]));

        // Trigger background process to insert data into DB
        insertDataInBackground($tempFile);
        return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "SUCCESS", ["data" => ["stockData" => $response["payload"]["data"], "stockInfo" => $dataStock], "message" => "Stock data fetched from external API."]);
    }

    //  If data is still unavailable
    return buildResponse("FETCH_SPECIFIC_STOCK_DATA_RESPONSE", "FAILED", ["message" => "Stock data unavailable."]);
}


function insertDataInBackground($filePath) {
    // Use exec() to run the insertion process in the background
    exec("php dbAsyncInsertion.php $filePath > /dev/null 2>&1 &");
}

?>
