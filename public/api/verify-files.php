<?php
// File verification checklist
header('Content-Type: application/json; charset=UTF-8');

$basePath = __DIR__ . '/..';  // Go up 1 level from api/ to booking/

$required_files = [
    'config.php' => $basePath . '/config.php',
    'lib/JWTHandler.php' => $basePath . '/lib/JWTHandler.php',
    'lib/AuthMiddleware.php' => $basePath . '/lib/AuthMiddleware.php',
    'api/login.php' => $basePath . '/api/login.php',
    'api/cors-test.php' => $basePath . '/api/cors-test.php',
];

$verification = [];

foreach ($required_files as $name => $path) {
    if (file_exists($path)) {
        $verification[$name] = [
            'exists' => true,
            'size' => filesize($path) . ' bytes',
            'readable' => is_readable($path)
        ];
    } else {
        $verification[$name] = [
            'exists' => false,
            'path_checked' => $path
        ];
    }
}

// Also check .htaccess
$htaccess_path = __DIR__ . '/../.htaccess';
if (file_exists($htaccess_path)) {
    $verification['.htaccess'] = [
        'exists' => true,
        'size' => filesize($htaccess_path) . ' bytes'
    ];
} else {
    $verification['.htaccess'] = ['exists' => false];
}

echo json_encode($verification, JSON_PRETTY_PRINT);
?>
