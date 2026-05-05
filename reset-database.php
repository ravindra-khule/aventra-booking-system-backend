<?php
/**
 * Alter table schema and update user roles
 */

$conn = new mysqli('localhost', 'root', '', 'aventra_db');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Connected to database!\n\n";

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS=0");

// Drop and recreate users table to ensure proper schema
$sql = "DROP TABLE IF EXISTS users";
$conn->query($sql);

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS=1");

$sql = "CREATE TABLE users (
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
    echo "✓ Users table recreated\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
    exit(1);
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
    
    $sql = "INSERT INTO users (email, password, name, role, status) VALUES (?, ?, ?, ?, 'ACTIVE')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $email, $password, $name, $role);
    
    if ($stmt->execute()) {
        echo "✓ Inserted user: $email ($role)\n";
    } else {
        echo "✗ Error inserting $email: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// Verify
echo "\nVerifying users in database:\n";
$result = $conn->query("SELECT id, email, name, role, status FROM users ORDER BY id");
while ($row = $result->fetch_assoc()) {
    echo "  ID {$row['id']}: {$row['name']} ({$row['email']}) - Role: {$row['role']}\n";
}

$conn->close();
echo "\n✓ Database setup complete!\n";
?>
