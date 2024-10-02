<?php
session_start();

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$username = $_SESSION['username'];

// Database connection credentials
$host = "localhost";
$dbname = "euporiaf_my_users_db"; 
$db_username = "euporiaf_MT4EA"; 
$db_password = "mt4_EA##";

// Connect to the database
$conn = new mysqli($host, $db_username, $db_password, $dbname);

// Check the connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $conn->connect_error]);
    exit();
}

// Fetch chart settings from the database
$stmt = $conn->prepare("SELECT instrument, timeframe, chart_number FROM charts WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$charts = [];
while ($row = $result->fetch_assoc()) {
    // Ensure the correct keys are used for JavaScript compatibility
    $charts[] = [
        'instrument' => $row['instrument'],
        'timeframe' => $row['timeframe'],
        'chart_number' => $row['chart_number']
    ];
}

echo json_encode(['success' => true, 'charts' => $charts]);

$stmt->close();
$conn->close();
?>
