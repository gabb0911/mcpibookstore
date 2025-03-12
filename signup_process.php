<?php
// Error reporting and logging configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$host = 'localhost';
$dbname = 'mcpi_ordering_system';
$username = 'root';
$password = '';

// Create connection with error handling
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database Connection Failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Log and handle database connection error
    error_log($e->getMessage());
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Please try again later.'
    ]));
}

// Set JSON response headers
header('Content-Type: application/json');

// Sanitize and validate input function
function sanitizeInput($conn, $input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $conn->real_escape_string($input);
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Main signup process
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Collect and sanitize form data
        $firstName = sanitizeInput($conn, $_POST['firstName'] ?? '');
        $lastName = sanitizeInput($conn, $_POST['lastName'] ?? '');
        $email = sanitizeInput($conn, $_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $grade = sanitizeInput($conn, $_POST['grade'] ?? '');

        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($grade)) {
            throw new Exception("All fields are required.");
        }

        if (!validateEmail($email)) {
            throw new Exception("Invalid email format.");
        }

        // Check if email already exists
        $checkEmailQuery = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($checkEmailQuery);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Email already registered.");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Prepare SQL to insert new user
        $insertQuery = "INSERT INTO users (first_name, last_name, email, password_hash, grade) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $grade);

        // Execute insertion
        if (!$stmt->execute()) {
            throw new Exception("User registration failed: " . $stmt->error);
        }

        // Success response
        echo json_encode([
            'status' => 'success',
            'message' => 'User registered successfully',
            'userId' => $conn->insert_id
        ]);

    } catch (Exception $e) {
        // Error logging
        error_log("Signup Error: " . $e->getMessage());

        // Error response
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    } finally {
        // Close database connection
        $conn->close();
    }
} else {
    // Invalid request method
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method Not Allowed'
    ]);
}
?>
