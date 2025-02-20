<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "authenticationdb");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        // Registration process
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Check if email already exists
        $stmt = $conn->prepare("SELECT userID FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "Email already registered!";
        } else {
            // Hash password
            $hashedpass = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $stmt = $conn->prepare("INSERT INTO Users (name, email, hashedpass) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashedpass);

            if ($stmt->execute()) {
                echo "Registration successful! <a href='index.php'>Login here</a>";
            } else {
                echo "Error: " . $stmt->error;
            }
        }

        $stmt->close();
    } elseif (isset($_POST['login'])) {
        // Login process
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT userID, name, hashedpass FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($userID, $name, $hashedpass);
            $stmt->fetch();

            if (password_verify($password, $hashedpass)) {
                $_SESSION['userID'] = $userID;
                $_SESSION['name'] = $name;
                header("Location: home.php");
                exit();
            } else {
                echo "Invalid password!";
            }
        } else {
            echo "User not found!";
        }

        $stmt->close();
    }
}

$conn->close();
?>
