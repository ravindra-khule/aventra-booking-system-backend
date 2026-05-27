<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config.php';

try {
    $conn = getDB();
    
    // Create settings table
    $createSettingsTable = "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category VARCHAR(50) NOT NULL,
        `key` VARCHAR(100) NOT NULL,
        value LONGTEXT NOT NULL,
        type ENUM('string', 'number', 'boolean', 'json', 'email', 'url') DEFAULT 'string',
        description VARCHAR(255),
        is_public BOOLEAN DEFAULT false,
        updated_by INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_category_key (category, `key`),
        INDEX idx_category (category),
        INDEX idx_key (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($createSettingsTable)) {
        echo "Settings table created successfully\n";
    } else {
        echo "Error creating settings table: " . $conn->error . "\n";
    }
    
    // Insert basic settings
    $insertSettings = "INSERT IGNORE INTO settings (category, `key`, value, type, description, is_public) VALUES
        ('Company', 'name', 'Aventra Tours', 'string', 'Company legal name', true),
        ('Company', 'email', 'info@aventra.com', 'email', 'Primary email address', true),
        ('Company', 'phone', '+46 (0)8 XXXX XXXX', 'string', 'Company phone number', true),
        ('Company', 'website', 'https://aventra.com', 'url', 'Company website URL', true),
        ('Company', 'address', 'Stockholm, Sweden', 'string', 'Physical address', true),
        ('System', 'maintenance_mode', 'false', 'boolean', 'Maintenance mode status', false),
        ('System', 'version', '1.0.0', 'string', 'Application version', true)";
    
    if ($conn->query($insertSettings)) {
        echo "Default settings inserted successfully\n";
    } else {
        echo "Error inserting default settings: " . $conn->error . "\n";
    }
    
    sendJSON(['success' => true, 'message' => 'Settings table created successfully']);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()]);
}
?>
