<?php
/**
 * Create Customers table for customer management
 * Run once: php setup-customers.php
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
    
    // Create customers table
    $sql = "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL,
        last_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        address VARCHAR(255),
        zip_code VARCHAR(20),
        city VARCHAR(100),
        country VARCHAR(100),
        notes TEXT,
        last_booking_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        
        INDEX idx_email (email),
        INDEX idx_created_at (created_at),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating customers table: " . $conn->error);
    }
    echo "✅ Customers table created/exists\n";
    
    // Add customer_id column to bookings table if it doesn't exist
    $checkColumnSql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                       WHERE TABLE_NAME = 'bookings' AND COLUMN_NAME = 'customer_id' 
                       AND TABLE_SCHEMA = ?";
    $checkStmt = $conn->prepare($checkColumnSql);
    $checkStmt->bind_param('s', $db);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($row['count'] == 0) {
        // Add customer_id foreign key column to bookings
        $alterSql = "ALTER TABLE bookings ADD COLUMN customer_id INT NULL AFTER user_id";
        if ($conn->query($alterSql)) {
            echo "✅ customer_id column added to bookings table\n";
            
            // Add foreign key constraint
            $fkSql = "ALTER TABLE bookings ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL";
            if ($conn->query($fkSql)) {
                echo "✅ Foreign key constraint added\n";
            } else {
                echo "⚠️  Foreign key constraint not added (may already exist): " . $conn->error . "\n";
            }
        } else {
            echo "⚠️  customer_id column may already exist: " . $conn->error . "\n";
        }
    } else {
        echo "✅ customer_id column already exists in bookings table\n";
    }
    
    $conn->close();
    echo "\n✅ Customers table setup complete!\n";
    echo "ℹ️  Next step: Run 'php populate-customers.php' to import existing booking customers\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
