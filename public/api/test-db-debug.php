<?php
header('Content-Type: application/json; charset=UTF-8');

$host = 'auth-db678.hstgr.io';
$user = 'u946701582_aventra_db';
$pass = 'm5yg8QFeo|E3';
$dbname = 'u946701582_aventra_db';

echo json_encode([
    'attempt' => 'Connecting to database...',
    'host' => $host,
    'user' => $user,
    'dbname' => $dbname
], JSON_PRETTY_PRINT) . "\n";

$conn = @mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_connect_error(),
        'error_code' => mysqli_connect_errno()
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Database connected successfully!',
        'database' => $dbname
    ], JSON_PRETTY_PRINT);
    mysqli_close($conn);
}
?>
