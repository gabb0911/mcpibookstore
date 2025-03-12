<?php
// Error logging script with improved request handling
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ensure only POST requests are processed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Method Not Allowed. Only POST requests are accepted.'
    ]);
    exit();
}

// Include database connection
require_once 'db_connection.php';

// Receive JSON payload
$json = file_get_contents('php://input');

// Check if JSON is valid
if (!$json) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'No data received'
    ]);
    exit();
}

// Decode JSON
$data = json_decode($json, true);

// Validate JSON decoding
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid JSON: ' . json_last_error_msg()
    ]);
    exit();
}

// Validate required fields
$requiredFields = ['context', 'errorName', 'errorMessage', 'timestamp'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => "Missing required field: $field"
        ]);
        exit();
    }
}

// Prepare SQL statement to log the error
try {
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO error_logs (
        context, 
        error_name, 
        error_code, 
        error_message, 
        email, 
        timestamp
    ) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Bind parameters
    $stmt->bind_param(
        "ssssss", 
        $data['context'], 
        $data['errorName'], 
        $data['errorCode'] ?? 'N/A', 
        $data['errorMessage'], 
        $data['email'] ?? 'N/A', 
        $data['timestamp']
    );
    
    // Execute the statement
    $stmt->execute();
    
    // Close statement
    $stmt->close();
    
    // Respond with success
    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'message' => 'Error logged successfully'
    ]);

} catch (Exception $e) {
    // Log any database-related errors
    error_log('Error logging failed: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Internal Server Error: Failed to log error'
    ]);
} finally {
    // Always close the database connection
    $conn->close();
}
?>
