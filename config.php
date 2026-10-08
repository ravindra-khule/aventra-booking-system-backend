<?php
/**
 * Database Configuration
 * Works for both Docker (env vars) and Hostinger (fallback values)
 */

// Load .env file if present (Hostinger has no env-var UI; Docker already provides real env vars)
// Existing environment variables always take precedence.
(function () {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name !== '' && getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
})();

// Check if running in Docker (env vars set), otherwise use Hostinger values
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'u946701582_aventra');
define('DB_PASS', getenv('DB_PASS') ?: '3*5PZb~aS');
define('DB_NAME', getenv('DB_NAME') ?: 'u946701582_aventra');

// JWT Secret for authentication (env var for JWTHandler + constant for legacy use)
putenv('JWT_SECRET=BHpuFnqhZJ4bYNzjWG7Sm9o3rcltC2OTvUVRw1I6gDfeEAXMPd0K8axL5Qkysi');
define('JWT_SECRET', 'BHpuFnqhZJ4bYNzjWG7Sm9o3rcltC2OTvUVRw1I6gDfeEAXMPd0K8axL5Qkysi');

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
