<?php
/**
 * Production Database Configuration for Hostinger
 * 
 * Steps to configure:
 * 1. Create MySQL database in Hostinger control panel
 * 2. Note the database credentials (host, database name, username, password)
 * 3. Update the values below
 * 4. Import database/init/*.sql files via phpMyAdmin
 */

// Hostinger Database Configuration
// Update these with your actual Hostinger credentials
define('DB_HOST', 'localhost');  // Usually 'localhost' or Hostinger provides a specific host
define('DB_USER', 'u123456789_aventra');  // Your Hostinger database username
define('DB_PASS', 'your_database_password');  // Your Hostinger database password
define('DB_NAME', 'u123456789_aventra_db');  // Your Hostinger database name

// JWT Secret - Generate a strong random string for production
// You can use: https://www.random.org/strings/ or command: openssl rand -base64 32
define('JWT_SECRET', 'your-strong-random-secret-key-here-min-32-chars');

// CORS Settings
// In production, restrict this to your actual domain
define('FRONTEND_URL', 'https://booking.prismadot.com');

function getDB() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        $errorMsg = $conn->connect_error;
        error_log("Database Connection Error: " . $errorMsg);
        
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'details' => $errorMsg
        ]);
        exit;
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

function sendJSON($data, $code = 200) {
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($data);
    exit;
}

/**
 * Debug logging (disable detailed logging in production)
 */
function debugLog($message, $data = null) {
    // In production, only log errors, not all debug info
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Only log if it's an actual error or critical
    if (strpos($message, 'ERROR') !== false || strpos($message, 'CRITICAL') !== false) {
        $logMessage = "[$timestamp] $message";
        
        if ($data !== null) {
            $sanitized = $data;
            if (is_array($sanitized)) {
                if (isset($sanitized['password'])) $sanitized['password'] = '***HIDDEN***';
                if (isset($sanitized['token'])) $sanitized['token'] = '***HIDDEN***';
            }
            $logMessage .= "\n" . json_encode($sanitized, JSON_PRETTY_PRINT);
        }
        
        $logMessage .= "\n---\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>
