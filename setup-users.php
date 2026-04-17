<?php
/**
 * Create Users table for authentication
 * Run once: php setup-users.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'aventra_db';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff', 'user') DEFAULT 'user',
        status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
        phone VARCHAR(20),
        company_name VARCHAR(255),
        address VARCHAR(255),
        city VARCHAR(100),
        country VARCHAR(100),
        postal_code VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        deleted_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating users table: " . $conn->error);
    }
    echo "✅ Users table created/exists\n";
    
    // Check if admin user exists
    $checkSql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
    $result = $conn->query($checkSql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Create default admin user
        $admin_email = 'admin@aventra.com';
        $admin_password = password_hash('admin123', PASSWORD_BCRYPT);
        $admin_name = 'Aventra Admin';
        
        $insertSql = "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param('sss', $admin_name, $admin_email, $admin_password);
        
        if ($stmt->execute()) {
            echo "✅ Default admin user created\n";
            echo "   Email: $admin_email\n";
            echo "   Password: admin123\n";
            echo "   ⚠️  CHANGE THIS PASSWORD IMMEDIATELY IN PRODUCTION!\n";
        } else {
            echo "⚠️  Admin user may already exist: " . $stmt->error . "\n";
        }
        $stmt->close();
    } else {
        echo "✅ Admin user already exists\n";
    }
    
    $conn->close();
    echo "\n✅ Users table setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
