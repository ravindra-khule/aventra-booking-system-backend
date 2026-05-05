<?php
header('Content-Type: application/json; charset=UTF-8');

$steps = [];

// Step 1: Test basic PHP
$steps['php_version'] = phpversion();
$steps['mysqli_enabled'] = extension_loaded('mysqli');

// Step 2: Test config.php inclusion
$config_ok = false;
$config_error = null;
try {
    require_once __DIR__ . '/../../config.php';
    $config_ok = true;
} catch (Exception $e) {
    $config_error = $e->getMessage();
}
$steps['config_included'] = $config_ok ? 'YES' : 'NO - ' . $config_error;

// Step 3: Test database connection using the getDB function
if ($config_ok) {
    try {
        $conn = getDB();
        $steps['database_connected'] = 'YES';
        mysqli_close($conn);
    } catch (Exception $e) {
        $steps['database_connected'] = 'NO - ' . $e->getMessage();
    }
}

// Step 4: Test JWT Handler
$jwt_ok = false;
$jwt_error = null;
try {
    require_once __DIR__ . '/../lib/JWTHandler.php';
    $jwt_ok = true;
} catch (Exception $e) {
    $jwt_error = $e->getMessage();
}
$steps['jwt_handler_loaded'] = $jwt_ok ? 'YES' : 'NO - ' . $jwt_error;

// Step 5: Manual database test
if ($config_ok && $steps['database_connected'] === 'YES') {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check users table
    $users_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
    if ($users_check) {
        $row = mysqli_fetch_assoc($users_check);
        $steps['users_table_count'] = $row['count'] . ' users';
    } else {
        $steps['users_table_check'] = 'FAILED: ' . mysqli_error($conn);
    }
    
    // Check superadmin user
    $admin_check = mysqli_query($conn, "SELECT id, email, password FROM users WHERE email = 'superadmin@swett.com'");
    if ($admin_check && mysqli_num_rows($admin_check) > 0) {
        $admin = mysqli_fetch_assoc($admin_check);
        $steps['superadmin_exists'] = 'YES (ID: ' . $admin['id'] . ')';
    } else {
        $steps['superadmin_exists'] = 'NO';
    }
    
    mysqli_close($conn);
}

echo json_encode($steps, JSON_PRETTY_PRINT);
?>
