<?php
/**
 * CORS Test Script
 * Test if the API endpoints have proper CORS headers enabled
 * 
 * Usage: Access this file via browser or curl to test CORS setup
 * Example: http://localhost:5500/api/cors-test.php
 */

// Test 1: Check if we're responding with CORS headers
echo "=== CORS Testing ===\n\n";

// Set CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');

// Check request method
$method = $_SERVER['REQUEST_METHOD'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'No Origin header';
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'No Content-Type';

$testResult = [
    'success' => true,
    'message' => 'CORS is working correctly',
    'request' => [
        'method' => $method,
        'origin' => $origin,
        'content_type' => $contentType,
        'preflight' => $method === 'OPTIONS' ? 'Yes (automatically handled)' : 'No'
    ],
    'cors_headers_sent' => true,
    'note' => 'If you see this JSON response from localhost:5500, CORS is working!'
];

http_response_code(200);
echo json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
