<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
$host = "localhost";
$dbname = "euporiaf_my_users_db"; 
$username = "euporiaf_MT4EA"; 
$password = "mt4_EA##";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $mt4AccountId = $_POST['mt4AccountId'];
    $mt4AccountPassword = $_POST['mt4AccountPassword'];  // Plain-text MT4 password
    $mt4Server = $_POST['mt4Server'];
    $password = $_POST['password']; // Plain-text login password
    $registrationCode = $_POST['registrationCode']; // Registration code input

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Validate the registration code first
        $stmt = $conn->prepare("SELECT id, used FROM registration_codes WHERE code = ? FOR UPDATE");
        $stmt->bind_param("s", $registrationCode);
        $stmt->execute();
        $stmt->bind_result($codeId, $used);
        $stmt->fetch();
        $stmt->close();

        // Check if the registration code is invalid or already used
        if (!$codeId) {
            throw new Exception("Invalid registration code");
        }

        if ($used) {
            throw new Exception("This registration code has already been used");
        }

        // Proceed to check if the username already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            throw new Exception("Username already in use");
        }

        // Check if the MT4 account ID already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE mt4_account_id = ?");
        $stmt->bind_param("s", $mt4AccountId);
        $stmt->execute();
        $stmt->bind_result($mt4Count);
        $stmt->fetch();
        $stmt->close();

        if ($mt4Count > 0) {
            throw new Exception("MT4 Account ID already in use");
        }

        // Send plain-text MT4 data to VPS
        $response = sendDataToVPS($username, $mt4AccountId, $mt4AccountPassword, $mt4Server);

        // Decode the response from the VPS
        $responseData = json_decode($response, true);

        if ($responseData['success']) {
            // Store plain-text MT4 and login passwords directly
            $stmt = $conn->prepare("INSERT INTO users (username, mt4_account_id, mt4_account_password, mt4_server, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $mt4AccountId, $mt4AccountPassword, $mt4Server, $password);

            if ($stmt->execute()) {
                // Mark the registration code as used and associate it with the new user
                $updateCodeStmt = $conn->prepare("UPDATE registration_codes SET used = 1, username = ? WHERE id = ?");
                $updateCodeStmt->bind_param("si", $username, $codeId);
                $updateCodeStmt->execute();
                $updateCodeStmt->close();

                echo json_encode(["success" => true, "message" => "Account created and data sent to VPS successfully"]);
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }

            $stmt->close();
        } else {
            // Return the VPS error message if VPS call fails
            throw new Exception($responseData['message']);
        }

        // Commit transaction if all steps succeed
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction if an error occurs
        $conn->rollback();
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

$conn->close();

// Function to send data to VPS
function sendDataToVPS($username, $mt4AccountId, $mt4AccountPassword, $mt4Server) {
    $vps_url = "https://rhino-vocal-finally.ngrok-free.app/deployEA.php";

    $data = [
        'username' => $username,
        'mt4AccountId' => $mt4AccountId,
        'mt4AccountPassword' => $mt4AccountPassword,
        'mt4Server' => $mt4Server
    ];

    // Initialize cURL request to send data to VPS
    $ch = curl_init($vps_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Execute the request and get the response
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}


?>
