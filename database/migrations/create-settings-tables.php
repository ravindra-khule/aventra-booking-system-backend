<?php
/**
 * Settings Database Migration
 * Creates settings and settings_audit tables
 */

// Database configuration
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'aventra_db';
$port = getenv('DB_PORT') ?: 3306;

try {
    // Connect to database
    $conn = new mysqli($host, $user, $password, $database, $port);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database: $database\n";
    
    // Create settings table
    $createSettingsTable = "CREATE TABLE IF NOT EXISTS settings (id INT PRIMARY KEY AUTO_INCREMENT, category VARCHAR(50) NOT NULL, `key` VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, type ENUM('string', 'number', 'boolean', 'json', 'email', 'url') DEFAULT 'string', description VARCHAR(255), is_public BOOLEAN DEFAULT false, updated_by INT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_category_key (category, `key`), INDEX idx_category (category), INDEX idx_key (`key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($createSettingsTable) === TRUE) {
        echo "✅ Settings table created successfully\n";
    } else {
        echo "❌ Error creating settings table: " . $conn->error . "\n";
    }
    
    // Create settings_audit table
    $createAuditTable = "CREATE TABLE IF NOT EXISTS settings_audit (id INT PRIMARY KEY AUTO_INCREMENT, setting_id INT NOT NULL, category VARCHAR(50), `key` VARCHAR(100), old_value LONGTEXT, new_value LONGTEXT, changed_by INT, ip_address VARCHAR(45), user_agent VARCHAR(255), changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_setting_id (setting_id), INDEX idx_category (category), INDEX idx_changed_at (changed_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($createAuditTable) === TRUE) {
        echo "✅ Settings audit table created successfully\n";
    } else {
        echo "❌ Error creating settings_audit table: " . $conn->error . "\n";
    }
    
    // Insert default settings (46 total)
    $defaultSettings = [
        // Company Settings (7)
        ['Company', 'name', 'Aventra Tours', 'string', 'Company legal name', true],
        ['Company', 'email', 'info@aventra.com', 'email', 'Primary email address', true],
        ['Company', 'phone', '+46 (0)8 XXXX XXXX', 'string', 'Company phone number', true],
        ['Company', 'website', 'https://aventra.com', 'url', 'Company website URL', true],
        ['Company', 'address', 'Stockholm, Sweden', 'string', 'Physical address', true],
        ['Company', 'vat_number', 'SE123456789012', 'string', 'VAT/Tax ID', false],
        ['Company', 'logo_url', '/images/aventra-logo.png', 'url', 'Logo file path', true],
        
        // Email Settings (11)
        ['Email', 'from_email', 'noreply@aventra.com', 'email', 'Sender email address', false],
        ['Email', 'from_name', 'Aventra Tours', 'string', 'Sender name', true],
        ['Email', 'reply_to_email', 'support@aventra.com', 'email', 'Reply-to address', true],
        ['Email', 'service_provider', 'smtp', 'string', 'SMTP/SendGrid/Mailgun', false],
        ['Email', 'sendgrid_api_key', '', 'string', 'SendGrid API key (encrypted)', false],
        ['Email', 'smtp_host', 'smtp.gmail.com', 'string', 'SMTP server hostname', false],
        ['Email', 'smtp_port', '587', 'string', 'SMTP port', false],
        ['Email', 'smtp_username', '', 'string', 'SMTP username', false],
        ['Email', 'smtp_password', '', 'string', 'SMTP password (encrypted)', false],
        ['Email', 'mailgun_api_key', '', 'string', 'Mailgun API key (encrypted)', false],
        ['Email', 'mailgun_domain', '', 'string', 'Mailgun domain', false],
        ['Email', 'mailgun_secret', '', 'string', 'Mailgun signing key (encrypted)', false],
        
        // Payment Settings (9)
        ['Payment', 'currency', 'SEK', 'string', 'Default currency', true],
        ['Payment', 'stripe_public_key', '', 'string', 'Stripe publishable key', false],
        ['Payment', 'stripe_secret_key', '', 'string', 'Stripe secret key (encrypted)', false],
        ['Payment', 'stripe_mode', 'test', 'string', 'test or live', false],
        ['Payment', 'stripe_webhook_secret', '', 'string', 'Webhook secret (encrypted)', false],
        ['Payment', 'payment_methods', '["card","bank_transfer"]', 'json', 'Enabled methods', true],
        ['Payment', 'minimum_payment_amount', '0', 'number', 'Minimum booking amount', true],
        ['Payment', 'transaction_fee_percent', '2.5', 'number', 'Platform fee percentage', true],
        ['Payment', 'tax_id', '', 'string', 'Tax identifier', false],
        
        // Integration Settings (7)
        ['Integration', 'fortnox_enabled', 'false', 'boolean', 'Enable Fortnox sync', true],
        ['Integration', 'fortnox_client_id', '', 'string', 'Fortnox API client ID (encrypted)', false],
        ['Integration', 'fortnox_client_secret', '', 'string', 'Fortnox API secret (encrypted)', false],
        ['Integration', 'fortnox_sync_interval', '24', 'number', 'Sync frequency in hours', true],
        ['Integration', 'fortnox_last_sync', '', 'string', 'Last successful sync timestamp', false],
        ['Integration', 'webhook_enable', 'true', 'boolean', 'Enable webhook callbacks', true],
        ['Integration', 'api_rate_limit', '100', 'number', 'API rate limit per minute', true],
        
        // Notification Settings (8)
        ['Notification', 'booking_confirmation_enabled', 'true', 'boolean', 'Send booking confirmations', true],
        ['Notification', 'booking_confirmation_template', 'booking_confirmation', 'string', 'Email template ID', false],
        ['Notification', 'booking_reminder_enabled', 'true', 'boolean', 'Send booking reminders', true],
        ['Notification', 'booking_reminder_days', '7', 'number', 'Days before booking to send reminder', true],
        ['Notification', 'cancellation_email_enabled', 'true', 'boolean', 'Send cancellation emails', true],
        ['Notification', 'support_notification_channel', 'email', 'string', 'Email/SMS/both', true],
        ['Notification', 'notification_from_email', 'notifications@aventra.com', 'email', 'Notification sender email', true],
        ['Notification', 'sms_service_provider', '', 'string', 'SMS provider (Twilio, etc.)', false],
        
        // System Settings (9)
        ['System', 'timezone', 'Europe/Stockholm', 'string', 'Application timezone', true],
        ['System', 'language', 'en', 'string', 'Default system language', true],
        ['System', 'date_format', 'DD/MM/YYYY', 'string', 'Date display format', true],
        ['System', 'time_format', '24h', 'string', 'Time display format (24h/12h)', true],
        ['System', 'maintenance_mode', 'false', 'boolean', 'Maintenance mode enabled', true],
        ['System', 'maintenance_message', 'System maintenance in progress', 'string', 'Custom maintenance message', true],
        ['System', 'backup_enabled', 'true', 'boolean', 'Automatic backups enabled', true],
        ['System', 'backup_frequency', 'daily', 'string', 'Backup frequency (hourly, daily, weekly, monthly)', true],
        ['System', 'audit_log_retention_days', '90', 'number', 'How long to keep audit logs', true],
        
        // Security Settings (12)
        ['Security', 'session_timeout', '1440', 'number', 'Session timeout in minutes', true],
        ['Security', 'password_min_length', '8', 'number', 'Minimum password length', true],
        ['Security', 'password_require_uppercase', 'true', 'boolean', 'Uppercase required', true],
        ['Security', 'password_require_numbers', 'true', 'boolean', 'Numbers required', true],
        ['Security', 'password_require_special', 'true', 'boolean', 'Special chars required', true],
        ['Security', 'password_expiry_days', '0', 'number', 'Password expiry (0=never)', true],
        ['Security', 'mfa_enabled', 'false', 'boolean', 'Multi-factor authentication enabled', true],
        ['Security', 'mfa_methods', '["totp"]', 'json', 'Enabled MFA methods', true],
        ['Security', 'login_attempt_limit', '5', 'number', 'Failed login attempts before lockout', true],
        ['Security', 'login_lockout_duration', '15', 'number', 'Lockout duration in minutes', true],
        ['Security', 'ip_whitelist_enabled', 'false', 'boolean', 'IP whitelist enabled', true],
        ['Security', 'ip_whitelist', '[]', 'json', 'Allowed IPs (JSON array)', false],
    ];
    
    // Check if settings already exist
    $checkResult = $conn->query("SELECT COUNT(*) as count FROM settings");
    $row = $checkResult->fetch_assoc();
    $existingCount = (int)$row['count'];
    
    if ($existingCount == 0) {
        $inserted = 0;
        $failed = 0;
        
        foreach ($defaultSettings as $setting) {
            $category = $conn->real_escape_string($setting[0]);
            $key = $conn->real_escape_string($setting[1]);
            $value = $conn->real_escape_string($setting[2]);
            $type = $conn->real_escape_string($setting[3]);
            $description = $conn->real_escape_string($setting[4]);
            $is_public = $setting[5] ? 1 : 0;
            
            $insertSetting = "INSERT INTO settings (category, `key`, value, type, description, is_public) VALUES ('$category', '$key', '$value', '$type', '$description', $is_public)";
            
            if ($conn->query($insertSetting) === TRUE) {
                $inserted++;
            } else {
                echo "❌ Error inserting setting $category.$key: " . $conn->error . "\n";
                $failed++;
            }
        }
        
        echo "✅ Inserted $inserted default settings\n";
        if ($failed > 0) {
            echo "⚠️  Failed to insert $failed settings\n";
        }
    } else {
        echo "ℹ️  Settings already exist ($existingCount records). Skipping default data insertion.\n";
    }
    
    // Verify tables
    $verifySettings = $conn->query("SELECT COUNT(*) as count FROM settings");
    $verifyAudit = $conn->query("SELECT COUNT(*) as count FROM settings_audit");
    
    if ($verifySettings && $verifyAudit) {
        $settingsCount = $verifySettings->fetch_assoc()['count'];
        $auditCount = $verifyAudit->fetch_assoc()['count'];
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "✅ MIGRATION COMPLETE\n";
        echo "Settings table: $settingsCount records\n";
        echo "Audit table: $auditCount records\n";
        echo str_repeat("=", 50) . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
