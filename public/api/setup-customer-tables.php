<?php
// ⚠️ CORS and Content-Type headers MUST be set first, before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Credentials: false');
header('Content-Type: application/json; charset=UTF-8');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit;
}

/**
 * GET /api/setup-customer-tables.php
 * One-time setup: creates customer_groups and communication_logs tables.
 * Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
 * Delete this file from the server after running it once.
 */

require_once __DIR__ . '/../../config.php';

try {
    $conn = getDB();
    
    $results = [];
    
    $groupsSql = "CREATE TABLE IF NOT EXISTS customer_groups (
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
    
    if ($conn->query($groupsSql)) {
        $results['customer_groups'] = 'created/exists';
    } else {
        $results['customer_groups'] = 'FAILED: ' . $conn->error;
    }
    
    $logsSql = "CREATE TABLE IF NOT EXISTS communication_logs (
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
    
    if ($conn->query($logsSql)) {
        $results['communication_logs'] = 'created/exists';
    } else {
        $results['communication_logs'] = 'FAILED: ' . $conn->error;
    }
    
    $failed = array_filter($results, fn($v) => str_starts_with($v, 'FAILED'));
    $conn->close();
    
    sendJSON([
        'success' => empty($failed),
        'message' => empty($failed) ? 'Tables created successfully' : 'Some tables failed to create',
        'results' => $results
    ], empty($failed) ? 200 : 500);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>
