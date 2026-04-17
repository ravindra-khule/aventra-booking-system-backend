<?php
/**
 * Settings Database Setup
 * Run once: php create-settings-tables.php
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
    
    echo "Setting up Settings System...\n";
    echo "==============================\n\n";
    
    // Create settings table
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100) NOT NULL,
        key_name VARCHAR(255) NOT NULL,
        value LONGTEXT,
        type ENUM('string', 'number', 'boolean', 'json', 'email', 'url') DEFAULT 'string',
        description TEXT,
        is_encrypted TINYINT DEFAULT 0,
        is_public TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_category_key (category, key_name),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating settings table: " . $conn->error);
    }
    echo "✅ Settings table created/exists\n";
    
    // Create settings data table for audit trail
    $sql = "CREATE TABLE IF NOT EXISTS settings_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_id INT,
        user_id INT,
        old_value LONGTEXT,
        new_value LONGTEXT,
        action VARCHAR(50),
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_setting_id (setting_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating settings_audit table: " . $conn->error);
    }
    echo "✅ Settings Audit table created/exists\n\n";
    
    // Insert default settings
    $defaultSettings = [
        // Company Information
        ['Company', 'name', 'Swett AB', 'string', 'Company name', 0, 0],
        ['Company', 'email', 'info@swett.se', 'email', 'Company email', 0, 1],
        ['Company', 'phone', '+46 (0)8 123 45 67', 'string', 'Company phone', 0, 1],
        ['Company', 'website', 'https://swett.se', 'url', 'Company website', 0, 1],
        ['Company', 'address', '', 'string', 'Company physical address', 0, 0],
        ['Company', 'city', '', 'string', 'Company city', 0, 0],
        ['Company', 'postal_code', '', 'string', 'Company postal code', 0, 0],
        ['Company', 'country', 'Sweden', 'string', 'Company country', 0, 0],
        ['Company', 'vat_number', '', 'string', 'Company VAT number', 0, 0],
        ['Company', 'logo_url', '', 'url', 'Company logo URL', 0, 0],
        
        // Email Configuration
        ['Email', 'smtp_host', 'smtp.gmail.com', 'string', 'SMTP server host', 1, 0],
        ['Email', 'smtp_port', '587', 'number', 'SMTP server port', 0, 0],
        ['Email', 'smtp_username', '', 'email', 'SMTP username', 1, 0],
        ['Email', 'smtp_password', '', 'string', 'SMTP password', 1, 0],
        ['Email', 'from_email', 'noreply@swett.se', 'email', 'Default from email', 0, 0],
        ['Email', 'from_name', 'Swett Booking System', 'string', 'Default from name', 0, 0],
        ['Email', 'reply_to_email', 'support@swett.se', 'email', 'Reply-to email', 0, 0],
        ['Email', 'service_provider', 'sendgrid', 'string', 'Email service (sendgrid/mailgun/smtp)', 0, 0],
        ['Email', 'sendgrid_api_key', '', 'string', 'SendGrid API key', 1, 0],
        ['Email', 'mailgun_domain', '', 'string', 'Mailgun domain', 1, 0],
        ['Email', 'mailgun_api_key', '', 'string', 'Mailgun API key', 1, 0],
        
        // Payment Settings
        ['Payment', 'stripe_public_key', '', 'string', 'Stripe publishable key', 0, 1],
        ['Payment', 'stripe_secret_key', '', 'string', 'Stripe secret key', 1, 0],
        ['Payment', 'stripe_mode', 'test', 'string', 'Stripe mode (test/live)', 0, 0],
        ['Payment', 'stripe_webhook_secret', '', 'string', 'Stripe webhook secret', 1, 0],
        ['Payment', 'currency', 'SEK', 'string', 'Default currency code', 0, 1],
        ['Payment', 'payment_methods', '["card","bank_transfer"]', 'json', 'Enabled payment methods', 0, 0],
        ['Payment', 'minimum_amount', '100', 'number', 'Minimum booking amount', 0, 0],
        
        // Integrations
        ['Integration', 'fortnox_enabled', '1', 'boolean', 'Enable Fortnox integration', 0, 0],
        ['Integration', 'fortnox_client_id', '', 'string', 'Fortnox client ID', 1, 0],
        ['Integration', 'fortnox_client_secret', '', 'string', 'Fortnox client secret', 1, 0],
        ['Integration', 'fortnox_sync_enabled', '1', 'boolean', 'Auto sync with Fortnox', 0, 0],
        ['Integration', 'fortnox_sync_frequency', 'hourly', 'string', 'Sync frequency', 0, 0],
        
        // Notifications
        ['Notification', 'booking_confirmation_enabled', '1', 'boolean', 'Send booking confirmation', 0, 0],
        ['Notification', 'booking_reminder_days', '7,3,1', 'string', 'Reminder days before tour', 0, 0],
        ['Notification', 'payment_reminder_enabled', '1', 'boolean', 'Send payment reminders', 0, 0],
        ['Notification', 'cancellation_email_enabled', '1', 'boolean', 'Send cancellation emails', 0, 0],
        ['Notification', 'admin_notification_email', '', 'email', 'Admin notification email', 0, 0],
        
        // System
        ['System', 'site_name', 'Swett Booking System', 'string', 'Application name', 0, 1],
        ['System', 'timezone', 'Europe/Stockholm', 'string', 'Default timezone', 0, 0],
        ['System', 'language', 'sv', 'string', 'Default language (sv/en)', 0, 0],
        ['System', 'date_format', 'Y-m-d', 'string', 'Date format', 0, 0],
        ['System', 'time_format', 'H:i:s', 'string', 'Time format', 0, 0],
        ['System', 'maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 0, 0],
        ['System', 'auto_backup_enabled', '1', 'boolean', 'Enable automatic backups', 0, 0],
        ['System', 'backup_frequency', 'daily', 'string', 'Backup frequency', 0, 0],
        
        // Security
        ['Security', 'session_timeout', '3600', 'number', 'Session timeout in seconds', 0, 0],
        ['Security', 'password_min_length', '8', 'number', 'Minimum password length', 0, 0],
        ['Security', 'mfa_enabled', '1', 'boolean', 'Require MFA for admin', 0, 0],
        ['Security', 'ip_whitelist', '[]', 'json', 'Whitelisted IP addresses', 0, 0],
        ['Security', 'max_login_attempts', '5', 'number', 'Max login attempts', 0, 0],
        ['Security', 'lockout_duration', '900', 'number', 'Lockout duration in seconds', 0, 0],
    ];
    
    foreach ($defaultSettings as [$category, $key, $value, $type, $description, $encrypted, $public]) {
        $sql = "INSERT IGNORE INTO settings (category, key_name, value, type, description, is_encrypted, is_public)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "⚠️ Error preparing statement: " . $conn->error . "\n";
            continue;
        }
        $stmt->bind_param('sssssii', $category, $key, $value, $type, $description, $encrypted, $public);
        if (!$stmt->execute()) {
            echo "⚠️ Could not insert setting '$category.$key': " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    echo "✅ Default settings inserted\n\n";
    
    $conn->close();
    
    echo "✅ Settings system setup completed!\n";
    echo "==============================\n";
    echo "\nSettings Categories:\n";
    echo "  • Company Information\n";
    echo "  • Email Configuration\n";
    echo "  • Payment Settings\n";
    echo "  • Integrations\n";
    echo "  • Notifications\n";
    echo "  • System Settings\n";
    echo "  • Security\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
