<?php
/**
 * Create customer_groups table for customer group management
 * Run once: php setup-customer-groups.php
 */

require_once __DIR__ . '/config.php';

try {
    $conn = getDB();
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS customer_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        type ENUM('manual','smart') NOT NULL DEFAULT 'manual',
        member_ids JSON,
        smart_rules JSON,
        pricing_rules JSON,
        default_discount JSON,
        tags JSON,
        color VARCHAR(20),
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        
        INDEX idx_type (type),
        INDEX idx_is_active (is_active),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating customer_groups table: " . $conn->error);
    }
    echo "✅ customer_groups table created/exists\n";
    
    $conn->close();
    echo "\n✅ Customer groups setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
