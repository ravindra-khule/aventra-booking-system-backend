<?php
/**
 * Router for PHP Development Server
 * Handles all requests and routes them to the appropriate file
 */

$requested_file = __DIR__ . $_SERVER["REQUEST_URI"];

// If it's a real file or directory, serve it directly
if (is_file($requested_file)) {
    return false;
}

// If directory, try index.php
if (is_dir($requested_file)) {
    $requested_file = $requested_file . '/index.php';
    if (is_file($requested_file)) {
        include $requested_file;
        return;
    }
}

// Default behavior - just return false to let PHP serve as is
return false;
?>
