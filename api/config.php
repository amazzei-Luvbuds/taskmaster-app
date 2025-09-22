<?php
/**
 * TaskMaster API Configuration
 * Database connection and API settings
 */

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $envFile = file_get_contents(__DIR__ . '/.env');
    $lines = explode("\n", $envFile);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration - Local MySQL server
define('DB_HOST', 'localhost');
define('DB_NAME', 'cosmichq_luvbudstaskmaster');
define('DB_USER', 'cosmichq_luvbudstaskmaster');
define('DB_PASS', 'gyjnix-dumpik-tobHi9');

// API configuration
define('API_VERSION', '1.0.0');
define('CORS_ORIGIN', '*'); // Change to your domain in production
define('DEBUG_MODE', true); // Set to false in production

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

/**
 * Database connection class
 */
class Database {
    private $connection;

    public function __construct() {
        try {
            // MySQL connection
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $this->handleError('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    private function handleError($message) {
        if (DEBUG_MODE) {
            die(json_encode(['error' => $message]));
        } else {
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
}

/**
 * Utility functions
 */
function sendJSON($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($status >= 400) {
        echo json_encode(['error' => $data, 'status' => $status]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'status' => $status,
            'timestamp' => date('c')
        ]);
    }
    exit;
}

function sendError($message, $status = 400) {
    sendJSON($message, $status);
}

function getRequestData() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON data', 400);
    }

    return $data;
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendError("Missing required field: $field", 400);
        }
    }
}

function generateTaskId() {
    $timestamp = substr(time(), -6);
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 3));
    return "NT-{$timestamp}-{$random}";
}

// Handle OPTIONS requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}
?>