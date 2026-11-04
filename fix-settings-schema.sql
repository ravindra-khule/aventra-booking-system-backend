-- Fix settings schema: rename key_name -> `key` to match API code
ALTER TABLE settings CHANGE key_name `key` VARCHAR(255) NOT NULL;

-- Fix settings_audit schema to match settings-update.php INSERT
ALTER TABLE settings_audit
  CHANGE user_id changed_by INT,
  ADD COLUMN category VARCHAR(100) AFTER setting_id,
  ADD COLUMN `key` VARCHAR(255) AFTER category,
  ADD COLUMN user_agent VARCHAR(255) AFTER ip_address,
  CHANGE created_at changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Seed new Company settings keys used by the Company Information page
INSERT IGNORE INTO settings (category, `key`, value, type, description, is_encrypted, is_public) VALUES
('Company', 'logo_filename', '', 'string', 'Company logo file name', 0, 0),
('Company', 'registration_number', '', 'string', 'Business registration number', 0, 0),
('Company', 'statutory_ids', '', 'string', 'Additional statutory IDs', 0, 0),
('Company', 'bank_name', '', 'string', 'Bank name', 0, 0),
('Company', 'bank_account_number', '', 'string', 'Bank account number', 0, 0),
('Company', 'bank_swift_code', '', 'string', 'IFSC/SWIFT code', 0, 0),
('Company', 'bank_branch_name', '', 'string', 'Bank branch name', 0, 0),
('Company', 'social_media', '[]', 'json', 'Social media links (JSON array)', 0, 1),
('Company', 'business_hours', '[]', 'json', 'Business hours (JSON array)', 0, 1),
('Company', 'about_text', '', 'string', 'Company about/description text', 0, 1),
('Company', 'language_content', '[]', 'json', 'Per-language company content (JSON array)', 0, 1);
