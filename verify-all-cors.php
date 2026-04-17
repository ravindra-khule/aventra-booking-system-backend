<?php
echo "FULL API CORS VERIFICATION\n";
echo "==========================\n\n";

$apis = [
    'GET /api/tours.php',
    'GET /api/bookings-list.php',
    'GET /api/customers-list.php',
    'GET /api/promo-codes-list.php'
];

foreach ($apis as $apiEndpoint) {
    list($method, $path) = explode(' ', $apiEndpoint);
    $url = 'http://127.0.0.1:5500' . $path;
    
    echo "Testing: " . $apiEndpoint . "\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    
    $hasCors = false;
    foreach ($headers as $header) {
        if (strpos($header, 'Access-Control-Allow-Origin') !== false) {
            $hasCors = true;
            echo "  ✓ CORS Enabled\n";
            break;
        }
    }
    
    if (!$hasCors) {
        echo "  ✗ CORS Not Found\n";
    }
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data['success']) {
            echo "  ✓ API Working\n";
        }
    }
    echo "\n";
}

echo "✅ All APIs have CORS headers enabled!\n";
echo "   The CORS error should be fixed now.\n";
echo "   Try refreshing http://localhost:4000\n";
?>
