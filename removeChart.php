<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['username'], $data['chartNumber'])) {
        $username = $data['username'];
        $chartNumber = $data['chartNumber'];

        // Check if the user has removed a chart recently
        if (isset($_SESSION['last_removal_time'])) {
            $timeSinceLastRemoval = time() - $_SESSION['last_removal_time'];
            $waitTime = 300; // 5 minutes in seconds

            if ($timeSinceLastRemoval < $waitTime) {
                $remainingTime = $waitTime - $timeSinceLastRemoval;
                echo json_encode(['success' => false, 'message' => 'Please wait ' . ceil($remainingTime / 60) . ' more minutes before removing another chart.']);
                exit();
            }
        }

                // Check if the user has added a chart recently
                if (isset($_SESSION['last_addition_time'])) {
                    $timeSinceLastAddition = time() - $_SESSION['last_addition_time'];
                    $waitTime = 300; // 5 minutes in seconds
        
                    if ($timeSinceLastAddition < $waitTime) {
                        $remainingTime = $waitTime - $timeSinceLastAddition;
                        echo json_encode(['success' => false, 'message' => 'You recently added a chart. Please wait ' . ceil($remainingTime / 60) . ' more minutes before removing a chart.']);
                        exit();
                    }
                }



        // Database connection credentials
        $host = "localhost";
        $dbname = "euporiaf_my_users_db";
        $db_username = "euporiaf_MT4EA";
        $db_password = "mt4_EA##";

        // Connect to the database
        $conn = new mysqli($host, $db_username, $db_password, $dbname);

        // Check the connection
        if ($conn->connect_error) {
            error_log('Database connection error: ' . $conn->connect_error);
            echo json_encode(['success' => false, 'message' => 'Database connection error.']);
            exit();
        }

        // Prepare to remove the chart from the database
        $stmt = $conn->prepare("DELETE FROM charts WHERE username = ? AND chart_number = ?");
        if ($stmt === false) {
            error_log('Prepare failed: ' . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database query preparation error.']);
            exit();
        }

        $stmt->bind_param("si", $username, $chartNumber);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Chart successfully deleted from the database, now delete from the server
                $serverResponse = deleteChartFromServer($username, $chartNumber);

                // Set the last removal time in session
                $_SESSION['last_removal_time'] = time();

                // Return the appropriate response depending on server deletion success
                if ($serverResponse['success']) {
                    echo json_encode(['success' => true, 'message' => 'Chart removed successfully from both database and server.']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Chart removed from database, but failed to remove from server: ' . $serverResponse['message']]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No chart found with the specified parameters.']);
            }
        } else {
            error_log('Execute failed: ' . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error removing chart from database.']);
        }

        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data. Please provide both username and chartNumber.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST requests are accepted.']);
}

// Function to delete the chart from the external server
function deleteChartFromServer($username, $chartNumber) {
    $url = "https://rhino-vocal-finally.ngrok-free.app/deleteEA.php";
    
    $postData = [
        'username' => $username,
        'chartNumber' => $chartNumber
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        return ['success' => true, 'message' => 'Chart removed from server.'];
    } else {
        return ['success' => false, 'message' => 'Server error: ' . $response];
    }
}
?>
