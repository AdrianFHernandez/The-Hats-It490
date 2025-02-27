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







?>
