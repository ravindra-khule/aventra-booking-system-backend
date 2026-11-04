<?php
/**
 * Create communication_logs table for customer communication history
 * Run once: php setup-communication-logs.php
 */

require_once __DIR__ . '/config.php';

try {
    $conn = getDB();
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS communication_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        type ENUM('email','sms','call','note') NOT NULL,
        direction ENUM('inbound','outbound') NOT NULL DEFAULT 'outbound',
        status VARCHAR(20) NOT NULL DEFAULT 'sent',
        subject VARCHAR(500),
        content TEXT,
        sender_name VARCHAR(255),
        sender_email VARCHAR(255),
        sender_phone VARCHAR(50),
        sender_type ENUM('customer','staff') DEFAULT 'staff',
        recipient_name VARCHAR(255),
        recipient_email VARCHAR(255),
        recipient_phone VARCHAR(50),
        sent_at TIMESTAMP NULL,
        delivered_at TIMESTAMP NULL,
        read_at TIMESTAMP NULL,
        response_time INT NULL,
        template_id VARCHAR(100),
        template_name VARCHAR(255),
        internal_notes TEXT,
        attachments JSON,
        tags JSON,
        related_booking_id VARCHAR(100),
        related_booking_reference VARCHAR(100),
        metadata JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        
        INDEX idx_customer_id (customer_id),
        INDEX idx_type (type),
        INDEX idx_status (status),
        INDEX idx_sent_at (sent_at),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating communication_logs table: " . $conn->error);
    }
    echo "✅ communication_logs table created/exists\n";
    
    $conn->close();
    echo "\n✅ Communication logs setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
