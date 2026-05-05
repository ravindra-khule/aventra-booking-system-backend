-- Email Templates Table
CREATE TABLE IF NOT EXISTS email_templates (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'DRAFT',
    version INT NOT NULL DEFAULT 1,
    is_default BOOLEAN NOT NULL DEFAULT 0,
    tags JSON,
    usage_count INT NOT NULL DEFAULT 0,
    created_by VARCHAR(100) NOT NULL,
    created_date DATETIME NOT NULL,
    last_modified DATETIME,
    last_modified_by VARCHAR(100),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_created_date (created_date)
);

-- Email Template Content Table (for multilingual support)
CREATE TABLE IF NOT EXISTS email_template_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id VARCHAR(50) NOT NULL,
    language VARCHAR(10) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_content LONGTEXT NOT NULL,
    text_content LONGTEXT,
    UNIQUE KEY unique_template_language (template_id, language),
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
    INDEX idx_language (language)
);

-- Email Template Versions Table
CREATE TABLE IF NOT EXISTS email_template_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id VARCHAR(50) NOT NULL,
    version INT NOT NULL,
    content JSON NOT NULL,
    change_description VARCHAR(500),
    created_by VARCHAR(100) NOT NULL,
    created_date DATETIME NOT NULL,
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
    INDEX idx_template_version (template_id, version)
);

-- Email Templates Audit Log Table
CREATE TABLE IF NOT EXISTS email_templates_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details JSON,
    created_by VARCHAR(100),
    created_date DATETIME NOT NULL,
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
    INDEX idx_template_date (template_id, created_date),
    INDEX idx_action (action)
);

-- Insert default email templates
INSERT INTO email_templates (id, name, description, category, status, version, is_default, tags, created_by, created_date) VALUES
('template-booking-confirmation', 'Booking Confirmation', 'Sent when a booking is confirmed', 'BOOKING', 'ACTIVE', 1, 1, '["booking", "confirmation"]', 'system', NOW()),
('template-booking-cancellation', 'Booking Cancellation', 'Sent when a booking is cancelled', 'CANCELLATION', 'ACTIVE', 1, 1, '["booking", "cancellation"]', 'system', NOW()),
('template-payment-received', 'Payment Received', 'Sent when payment is received', 'PAYMENT', 'ACTIVE', 1, 1, '["payment", "receipt"]', 'system', NOW()),
('template-reminder-24h', '24 Hour Reminder', 'Sent 24 hours before the tour', 'REMINDER', 'ACTIVE', 1, 1, '["reminder", "booking"]', 'system', NOW()),
('template-reminder-3d', '3 Day Reminder', 'Sent 3 days before the tour', 'REMINDER', 'ACTIVE', 1, 1, '["reminder", "booking"]', 'system', NOW());

-- Insert default content for confirmation email
INSERT INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-confirmation', 'en', 'Your Booking Confirmation - {{tourName}}',
'<html><body><h1>Booking Confirmed!</h1><p>Dear {{customerName}},</p><p>Your booking for {{tourName}} on {{tourDate}} has been confirmed.</p><p><strong>Booking Details:</strong></p><ul><li>Tour: {{tourName}}</li><li>Date: {{tourDate}}</li><li>Time: {{tourTime}}</li><li>Guests: {{guestCount}}</li><li>Total Price: {{totalPrice}}</li></ul><p><a href="{{bookingLink}}">View Your Booking</a></p><p>Thank you for booking with us!</p></body></html>',
'Your booking for {{tourName}} on {{tourDate}} has been confirmed. View your booking: {{bookingLink}}'),
('template-booking-confirmation', 'sv', 'Din bokningsbekräftelse - {{tourName}}',
'<html><body><h1>Bokning bekräftad!</h1><p>Hej {{customerName}},</p><p>Din bokning för {{tourName}} den {{tourDate}} har bekräftats.</p><p><strong>Bokningsdetaljer:</strong></p><ul><li>Tur: {{tourName}}</li><li>Datum: {{tourDate}}</li><li>Tid: {{tourTime}}</li><li>Gäster: {{guestCount}}</li><li>Totalt pris: {{totalPrice}}</li></ul><p><a href="{{bookingLink}}">Visa din bokning</a></p><p>Tack för att du bokade med oss!</p></body></html>',
'Din bokning för {{tourName}} den {{tourDate}} har bekräftats. Visa din bokning: {{bookingLink}}');

-- Insert default content for cancellation email
INSERT INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-cancellation', 'en', 'Your Booking Has Been Cancelled - {{tourName}}',
'<html><body><h1>Booking Cancelled</h1><p>Dear {{customerName}},</p><p>Your booking for {{tourName}} on {{tourDate}} has been cancelled.</p><p><strong>Cancellation Details:</strong></p><ul><li>Tour: {{tourName}}</li><li>Original Date: {{tourDate}}</li><li>Refund Amount: {{refundAmount}}</li></ul><p>The refund will be processed within 5-10 business days.</p><p>If you have any questions, please contact us.</p></body></html>',
'Your booking for {{tourName}} has been cancelled. Refund: {{refundAmount}}'),
('template-booking-cancellation', 'sv', 'Din bokning har avbokats - {{tourName}}',
'<html><body><h1>Bokning avbokad</h1><p>Hej {{customerName}},</p><p>Din bokning för {{tourName}} den {{tourDate}} har avbokats.</p><p><strong>Avbokningsdetaljer:</strong></p><ul><li>Tur: {{tourName}}</li><li>Ursprungligt datum: {{tourDate}}</li><li>Återbetalningsbelopp: {{refundAmount}}</li></ul><p>Återbetalningen behandlas inom 5-10 arbetsdagar.</p></body></html>',
'Din bokning för {{tourName}} har avbokats. Återbetalning: {{refundAmount}}');
