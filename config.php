<?php
/**
 * Database Configuration - Hostinger Production
 * booking.prismadot.com
 */

// Hostinger Database Credentials
define('DB_HOST', 'auth-db678.hstgr.io');
define('DB_USER', 'u946701582_aventra');
define('DB_PASS', 'Prismadot@123');
define('DB_NAME', 'u946701582_aventra');

// JWT Secret for authentication
define('JWT_SECRET', 'xoJGiyVAIyKO4OIf5WOv256EcQN0Blnx7JxyvmCXsCs=');

function getDB() {
    // Suppress warnings during connection attempt
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        $errorMsg = $conn->connect_error;
        
        // Log the detailed error
        error_log("Database Connection Error: " . $errorMsg);
        debugLog("DATABASE CONNECTION ERROR", [
            'host' => DB_HOST,
            'user' => DB_USER,
            'database' => DB_NAME,
            'error' => $errorMsg
        ]);
        
        // Prepare error response
        $response = [
            'success' => false,
            'error' => 'Database connection failed',
            'details' => $errorMsg  // Include details for debugging
        ];
        
        // Ensure proper JSON output
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response);
        exit;
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

function sendJSON($data, $code = 200) {
    // Set response code
    http_response_code($code);
    
    // Content-Type should already be set by the calling endpoint
    // Only set if not already set
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
    echo json_encode($data);
    exit;
}

/**
 * Debug logging function
 * Logs to both server error log and custom log file
 * Automatically sanitizes sensitive data (passwords, tokens)
 */
function debugLog($message, $data = null) {
    // Create logs directory if it doesn't exist
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/debug.log';
    
    // Format the message
    $timestamp = date('Y-m-d H:i:s.u');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        // Sanitize sensitive data before logging
        $sanitized = $data;
        if (is_array($sanitized)) {
            if (isset($sanitized['password'])) {
                $sanitized['password'] = '***HIDDEN***';
            }
            if (isset($sanitized['token'])) {
                $sanitized['token'] = '***HIDDEN***';
            }
        }
        $logMessage .= "\n" . json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    $logMessage .= "\n---\n";
    
    // Write to file
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Also write to PHP error log
    error_log($logMessage);
}

/**
 * Get latest log entries from debug log
 */
function getDebugLogs($lines = 50) {
    $logFile = __DIR__ . '/storage/logs/debug.log';
    if (!file_exists($logFile)) {
        return "No log file found at: $logFile";
    }
    
    $content = @file_get_contents($logFile);
    if (!$content) {
        return "Log file is empty";
    }
    
    $allLines = explode("\n", $content);
    $lastLines = array_slice($allLines, -$lines);
    
    return implode("\n", $lastLines);
}
?>
