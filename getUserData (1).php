<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session
session_start();

// Database credentials
$host = "localhost";
$dbname = "euporiaf_my_users_db"; // Change this to your actual database name
$username = "euporiaf_MT4EA"; // Change this to your actual database username
$password = "mt4_EA##"; // Change this to your actual database password

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Debug: Check if session is started and username is set
if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "Not authenticated"]);
    exit();
}

// Fetch user data
$user = $_SESSION['username'];
$stmt = $conn->prepare("SELECT username, mt4_account_id, mt4_server FROM users WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$stmt->bind_result($username, $mt4_account_id, $mt4_server);

$data = [];
if ($stmt->fetch()) {
    $data = [
        "success" => true,
        "username" => $username,
        "mt4_account_id" => $mt4_account_id,
        "mt4_server" => $mt4_server
    ];
} else {
    $data = ["success" => false, "message" => "User not found"];
}

$stmt->close();
$conn->close();

echo json_encode($data);
?>