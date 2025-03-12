<?php
session_start();
header('Content-Type: application/json');

// Require Composer autoloader
require_once 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\Auth\InvalidPassword;

// Logging function
function secure_log($message, $level = 'INFO') {
    $log_file = __DIR__ . '/login_security.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_entry = "{$timestamp} [{$level}] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Input validation and sanitization
function sanitize_input($input) {
    return trim(htmlspecialchars(strip_tags($input)));
}

// Main login process
try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Parse JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Invalid input data');
    }

    $email = sanitize_input($data['email']);
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Initialize Firebase
    $firebase = (new Factory)
        ->withServiceAccount(__DIR__ . '/mcpi-ordering-system-firebase-adminsdk.json');
    
    $auth = $firebase->createAuth();
    $firestore = $firebase->createFirestore();

    try {
        // Attempt Firebase Authentication
        $user = $auth->getUserByEmail($email);

        // Verify password
        try {
            $signInResult = $auth->signInWithEmailAndPassword($email, $password);
            
            // Fetch additional user data from Firestore
            $userRef = $firestore->database()->collection('users')->document($user->uid);
            $userSnapshot = $userRef->snapshot();
            
            if (!$userSnapshot->exists()) {
                throw new Exception('User profile not found');
            }

            $userData = $userSnapshot->data();

            // Start session
            $_SESSION['user_id'] = $user->uid;
            $_SESSION['email'] = $user->email;
            $_SESSION['firstName'] = $userData['firstName'] ?? '';
            $_SESSION['lastName'] = $userData['lastName'] ?? '';

            // Log successful login
            secure_log("Successful login for user: {$email}");

            // Update last login timestamp
            $userRef->update([
                ['path' => 'lastLogin', 'value' => date('Y-m-d H:i:s')]
            ]);

            // Respond with success
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => 'dashboard.php'
            ]);
            exit;

        } catch (InvalidPassword $e) {
            throw new Exception('Invalid credentials');
        }

    } catch (UserNotFound $e) {
        throw new Exception('User not found');
    }

} catch (Exception $e) {
    // Log and respond with error
    secure_log($e->getMessage(), 'ERROR');
    
    http_response_code(401); // Unauthorized
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
