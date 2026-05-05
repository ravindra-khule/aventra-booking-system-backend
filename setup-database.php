<?php
/**
 * Setup database and insert demo users
 */

$conn = new mysqli('localhost', 'root', '', 'aventra_db');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Connected to database!\n";

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    role VARCHAR(100) DEFAULT 'CUSTOMER',
    status VARCHAR(20) DEFAULT 'ACTIVE',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Users table created/exists\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

// Insert demo users
$demoUsers = [
    ['superadmin@swett.com', 'Swett2025!Super', 'Super Admin', 'SUPER_ADMIN'],
    ['admin@swett.com', 'Swett2025!Admin', 'Admin User', 'ADMIN'],
    ['support@swett.com', 'Swett2025!Support', 'Support Team', 'SUPPORT'],
    ['accountant@swett.com', 'Swett2025!Finance', 'Accountant', 'ACCOUNTANT'],
    ['developer@swett.com', 'Swett2025!Dev', 'Developer', 'DEVELOPER'],
    ['guest@swett.com', 'Swett2025!Guest', 'Guest User', 'CUSTOMER']
];

foreach ($demoUsers as $user) {
    $email = $user[0];
    $password = password_hash($user[1], PASSWORD_BCRYPT);
    $name = $user[2];
    $role = $user[3];
    
    $sql = "INSERT IGNORE INTO users (email, password, name, role, status) 
            VALUES ('$email', '$password', '$name', '$role', 'ACTIVE')";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Inserted user: $email\n";
    } else {
        echo "✗ Error inserting $email: " . $conn->error . "\n";
    }
}

$conn->close();
echo "\n✓ Database setup complete!\n";
?>
