<?php
// Comprehensive error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set content type
header('Content-Type: application/json');

// Detailed logging function
function comprehensive_log($message, $data = null) {
    $log_file = __DIR__ . '/debug_password_handler.log';
    $timestamp = date('[Y-m-d H:i:s]');
    
    // Ensure log directory exists
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Format log entry
    $log_entry = $timestamp . ' ' . $message . PHP_EOL;
    if ($data !== null) {
        $log_entry .= print_r($data, true) . PHP_EOL;
    }
    
    // Add server and environment information
    $log_entry .= "SERVER INFO:\n" . print_r([
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
        'PHP_VERSION' => PHP_VERSION,
        'EXTENSIONS' => get_loaded_extensions()
    ], true) . "\n\n";
    
    // Write to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Debug function to check PHP environment
function check_php_environment() {
    comprehensive_log('PHP Environment Check', [
        'PHP Version' => PHP_VERSION,
        'Loaded Extensions' => get_loaded_extensions(),
        'Password Hashing Algorithms' => hash_algos(),
        'Firestore Extension' => extension_loaded('google_cloud_firestore') ? 'Loaded' : 'Not Loaded',
        'OpenSSL Extension' => extension_loaded('openssl') ? 'Loaded' : 'Not Loaded'
    ]);
}

// Comprehensive Firebase connection diagnostic function
function diagnose_firebase_connection() {
    $diagnostics = [
        'PHP_VERSION' => PHP_VERSION,
        'LOADED_EXTENSIONS' => get_loaded_extensions(),
        'COMPOSER_AUTOLOAD_EXISTS' => file_exists('vendor/autoload.php'),
        'GOOGLE_CLOUD_LIBRARIES_INSTALLED' => class_exists('\Google\Cloud\Core\ServiceBuilder'),
        'FIRESTORE_CLIENT_CLASS_EXISTS' => class_exists('\Google\Cloud\Firestore\FirestoreClient'),
        'ENVIRONMENT_VARIABLES' => [
            'GOOGLE_APPLICATION_CREDENTIALS' => getenv('GOOGLE_APPLICATION_CREDENTIALS'),
            'FIREBASE_PROJECT_ID' => getenv('FIREBASE_PROJECT_ID')
        ]
    ];

    comprehensive_log('Firebase Connection Diagnostics', $diagnostics);
    return $diagnostics;
}

// Alternative Firebase connection methods
function connect_to_firestore() {
    // Method 1: Using environment variable credentials
    if ($credentials = getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
        try {
            return new \Google\Cloud\Firestore\FirestoreClient([
                'keyFilePath' => $credentials
            ]);
        } catch (\Exception $e) {
            comprehensive_log('Environment Credentials Failed', $e->getMessage());
        }
    }

    // Method 2: Using explicit service account file
    $possible_credentials = [
        __DIR__ . '/firebase_credentials.json',
        __DIR__ . '/credentials.json',
        $_SERVER['DOCUMENT_ROOT'] . '/firebase_credentials.json'
    ];

    foreach ($possible_credentials as $credentials_path) {
        if (file_exists($credentials_path)) {
            try {
                return new \Google\Cloud\Firestore\FirestoreClient([
                    'keyFilePath' => $credentials_path
                ]);
            } catch (\Exception $e) {
                comprehensive_log('Credentials File Failed: ' . $credentials_path, $e->getMessage());
            }
        }
    }

    // Method 3: Using default Google Cloud credentials
    try {
        return new \Google\Cloud\Firestore\FirestoreClient();
    } catch (\Exception $e) {
        comprehensive_log('Default Credentials Failed', $e->getMessage());
    }

    // If all methods fail
    throw new \Exception('Unable to establish Firestore connection. Please check your Firebase configuration.');
}

// Main error handling wrapper
function handle_error($message, $data = null, $http_code = 400) {
    comprehensive_log("ERROR: $message", $data);
    
    http_response_code($http_code);
    echo json_encode([
        'status' => 'error',
        'message' => $message,
        'debug_data' => $data
    ]);
    exit;
}

// Start debugging
check_php_environment();

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handle_error('Invalid request method', $_SERVER);
    }

    // Read raw POST data with error handling
    $raw_data = file_get_contents('php://input');
    if ($raw_data === false) {
        handle_error('Unable to read request body');
    }

    // Log raw input for debugging
    comprehensive_log('Raw Input Data', $raw_data);

    // Parse JSON with error handling
    $data = json_decode($raw_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        handle_error('Invalid JSON', [
            'json_error' => json_last_error_msg(),
            'raw_data' => $raw_data
        ]);
    }

    // Validate input
    $required_fields = ['userId', 'email', 'password'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            handle_error("Missing required field: $field", $data);
        }
    }

    // Sanitize and validate inputs
    $userId = filter_var($data['userId'], FILTER_SANITIZE_STRING);
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        handle_error('Invalid email format', $email);
    }

    // Password strength validation
    if (strlen($password) < 8) {
        handle_error('Password too short', ['password_length' => strlen($password)]);
    }

    // Require Firestore client with comprehensive error handling
    try {
        require_once 'vendor/autoload.php';

        // Diagnose Firebase connection
        $diagnostics = diagnose_firebase_connection();

        // Attempt to connect to Firestore
        $firestore = connect_to_firestore();

        // Secure password hashing
        $hashed_password = password_hash($password . $userId, PASSWORD_DEFAULT);

        // Reference the user document
        $userRef = $firestore->collection('users')->document($userId);

        // Atomic update
        $userRef->update([
            ['path' => 'hashedPassword', 'value' => $hashed_password],
            ['path' => 'email', 'value' => $email]
        ]);

        // Log successful operation
        comprehensive_log('Password Successfully Stored', [
            'userId' => $userId,
            'email' => $email
        ]);

        // Respond with success
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Password securely stored',
            'diagnostics' => $diagnostics
        ]);

    } catch (\Google\Cloud\Core\Exception\GoogleException $e) {
        handle_error('Firestore Initialization Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'diagnostics' => diagnose_firebase_connection()
        ], 500);
    }

} catch (Exception $e) {
    handle_error('Unexpected Error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
