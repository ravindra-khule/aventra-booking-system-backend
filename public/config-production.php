<?php
/**
 * Aventra Booking System - Configuration
 * Supports both local development and Hostinger production environments
 */

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            putenv("$key=$value");
        }
    }
}

// Database Configuration
// First try environment variables, then defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'aventra_db');

// Application Configuration
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'http://localhost:4000');
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true' ? true : false);

// JWT Secret Key (use environment variable)
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production');

/**
 * Get database connection
 * 
 * @return mysqli
 * @throws Exception
 */
function getDB() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => APP_DEBUG ? $e->getMessage() : 'Database connection failed'
        ]);
        exit;
    }
}

/**
 * Send JSON response
 * 
 * @param array $data
 * @param int $code
 */
function sendJSON($data, $code = 200) {
    // Set response code
    http_response_code($code);
    
    // Content-Type and CORS headers should already be set by calling endpoint
    // But ensure they're set if not
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
    echo json_encode($data);
    exit;
}

/**
 * Log API request (optional - for debugging)
 * 
 * @param string $endpoint
 * @param string $method
 * @param array $data
 */
function logRequest($endpoint, $method, $data = []) {
    if (!APP_DEBUG) {
        return;
    }
    
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/api-' . date('Y-m-d') . '.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] $method $endpoint - " . json_encode($data) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Verify AJAX request
 * 
 * @return bool
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request body as array
 * 
 * @return array
 */
function getRequestBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

?>
