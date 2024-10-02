<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetch the POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Check if the data is valid
    if (isset($data['username'], $data['instrument'], $data['timeFrame'])) {
        $username = $data['username'];
        $instrument = $data['instrument'];
        $timeFrame = $data['timeFrame'];

        // Check if the user has removed a chart recently
        if (isset($_SESSION['last_removal_time'])) {
            $timeSinceLastRemoval = time() - $_SESSION['last_removal_time'];
            $waitTime = 300; // 5 minutes in seconds

            if ($timeSinceLastRemoval < $waitTime) {
                $remainingTime = $waitTime - $timeSinceLastRemoval;
                echo json_encode(['success' => false, 'message' => 'You recently removed a chart. Please wait ' . ceil($remainingTime / 60) . ' more minutes before adding another chart.']);
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
            echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $conn->connect_error]);
            exit();
        }


        // Check the number of active charts for the user
        $stmt = $conn->prepare("SELECT COUNT(*) as active_charts FROM charts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['active_charts'] >= 3) {
            echo json_encode(['success' => false, 'message' => 'You have reached the limit of 3 active charts. Please remove a chart before adding a new one.']);
            $stmt->close();
            $conn->close();
            exit();
        }

        // Check the last added chart time
        $stmt = $conn->prepare("SELECT MAX(added_at) as last_added FROM charts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $current_time = new DateTime();
        if ($row['last_added']) {
            $last_added_time = new DateTime($row['last_added']);
            $interval = $current_time->diff($last_added_time);
            if ($interval->i < 5 && $interval->h == 0) { // Check if less than 5 minutes
                echo json_encode(['success' => false, 'message' => 'Please wait ' . (5 - $interval->i) . ' minutes before adding another setting.']);
                $stmt->close();
                $conn->close();
                exit();
            }
        }

        // Generate a new chart number by checking for available IDs
        $stmt = $conn->prepare("SELECT chart_number FROM charts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $usedIds = [];
        while ($row = $result->fetch_assoc()) {
            $usedIds[] = $row['chart_number'];
        }

        // Find the lowest unused chart number or set a new one if all are used
        $newChartNumber = 1; // Start checking from 1
        while (in_array($newChartNumber, $usedIds)) {
            $newChartNumber++;
        }

        // Insert the chart data into the database
        $stmt = $conn->prepare("INSERT INTO charts (username, instrument, timeframe, chart_number, added_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $username, $instrument, $timeFrame, $newChartNumber);

        if ($stmt->execute()) {
            // Set the last addition time in the session
            $_SESSION['last_addition_time'] = time();

            // Send the updated settings to the specified server immediately after adding the chart
            $settingsData = [
                'username' => $username,
                'instrument' => $instrument,
                'timeFrame' => $timeFrame,
                'settingIndex' => $newChartNumber // Use chart number as setting index
            ];
            $response = sendSettingsToServer($settingsData);

            // Return the response from the server if needed, or just indicate success
            echo json_encode(['success' => true, 'chart_number' => $newChartNumber, 'server_response' => $response]); // Return the new chart number and server response
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving chart: ' . $stmt->error]);
        }

        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to send settings data to the external server
function sendSettingsToServer($settingsData) {
    $url = "https://rhino-vocal-finally.ngrok-free.app/receivesettings.php";

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($settingsData));

    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        return json_encode(['success' => false, 'message' => 'cURL error: ' . curl_error($ch)]);
    }

    curl_close($ch);

    // Return the response from the server
    return json_encode(['success' => true, 'response' => json_decode($response, true), 'http_code' => $httpCode]);
}
?>
