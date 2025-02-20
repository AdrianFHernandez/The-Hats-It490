<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function dbConnect()
{
    $conn = new mysqli("localhost", "testUser", "12345", "authenticationdb");

    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }

    return $conn;
}

function doLogin($username, $password)
{
    $conn = dbConnect();

    
    
    $stmt = $conn->prepare("SELECT hashedpass FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($hashedpass);
        $stmt->fetch();

        if (password_verify($password, $hashedpass)) {
            $stmt->close();
            $conn->close();
            echo "Sucess\n";
            return ["returnCode" => '0', "message" => "Login successful"];
        }
    }

    $stmt->close();
    $conn->close();
    echo "Inv\n";
    return ["returnCode" => '1', "message" => "Invalid username or password"];
}

function doRegister($name, $username, $email, $password)
{
    $conn = dbConnect();

    
    // Check if user exists
    $stmt = $conn->prepare("SELECT userID FROM Users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "already";
        $stmt->close();
        $conn->close();
        return ["returnCode" => '1', "message" => "Username or Email already taken"];
    }

    // Hash password
    $hashedpass = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into database
    $stmt = $conn->prepare("INSERT INTO Users (name, username, email, hashedpass) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $username, $email, $hashedpass);

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

function requestProcessor($request)
{
    echo "Received request" . PHP_EOL;
    var_dump($request);

    if (!isset($request['type'])) {
        return ["returnCode" => '3', "message" => "ERROR: unsupported message type"];
    }

    switch ($request['type']) {
        case "login":
            return doLogin($request['username'], $request['password']);
        case "register":
            return doRegister($request['name'], $request['username'], $request['email'], $request['password']);
        default:
            return ["returnCode" => '3', "message" => "Unsupported message type"];
    }
}

// $server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

// echo "testRabbitMQServer BEGIN" . PHP_EOL;
// $server->process_requests('requestProcessor');
// echo "testRabbitMQServer END" . PHP_EOL;

echo "running register";

// doRegister("TOm", "username", "email@gmail.com", "password");
doLogin("username", "password");
doLogin("kjsdfkjd", "sdfjksdjf");
echo "Finished resistering";




exit();

?>


