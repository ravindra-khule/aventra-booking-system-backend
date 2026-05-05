<?php
// Ultra-simple diagnostic - no dependencies
header('Content-Type: application/json');

$diagnostics = [
    'php_version' => phpversion(),
    'php_sapi' => php_sapi_name(),
    'current_user' => get_current_user(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'mysqli_enabled' => extension_loaded('mysqli') ? 'YES' : 'NO',
    'error_reporting' => ini_get('error_reporting'),
    'display_errors' => ini_get('display_errors'),
];

// Try to connect to database
$host = 'auth-db678.hstgr.io';
$user = 'u946701582_aventra_db';
$pass = 'm5yg8QFeo|E3';
$dbname = 'u946701582_aventra_db';

if (extension_loaded('mysqli')) {
    $conn = @mysqli_connect($host, $user, $pass, $dbname);
    if ($conn) {
        $diagnostics['database_connection'] = 'SUCCESS';
        mysqli_close($conn);
    } else {
        $diagnostics['database_connection'] = 'FAILED: ' . mysqli_connect_error();
    }
} else {
    $diagnostics['database_connection'] = 'SKIPPED - mysqli not enabled';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>
