<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Database credentials
$host = "localhost";
$dbname = "euporiaf_my_users_db"; // Change this to your actual database name
$dbUsername = "euporiaf_MT4EA"; // Change this to your actual database username
$dbPassword = "mt4_EA##"; // Change this to your actual database password

// Create connection
$conn = new mysqli($host, $dbUsername, $dbPassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if the user exists and retrieve the stored plain-text password
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($storedPassword);
    $stmt->fetch();

    // Compare the entered password with the stored plain-text password
    if ($storedPassword && $password === $storedPassword) {
        // Set session variable for logged-in user
        $_SESSION['username'] = $username;

        echo json_encode(["success" => true, "message" => "Login successful"]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid username or password"]);
    }

    $stmt->close();
}

$conn->close();
?>
