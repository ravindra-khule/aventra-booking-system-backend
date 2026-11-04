-- ============================================================
-- PRODUCTION EMAIL + NOTIFICATION SETUP (run once in phpMyAdmin)
-- Covers:
--   1. Hostinger SMTP credentials (booking@prismadot.com)
--   2. Notification settings + toggles + disabled events
--   3. scheduled_emails queue table (for cron reminders)
--   4. Missing template content (reminders, payment received)
--   5. New lifecycle templates (received, rejected, cancelled-by-client,
--      rescheduled, completed, payment failed/refunded/reminder)
-- Safe to re-run: INSERT IGNORE / CREATE IF NOT EXISTS.
-- ============================================================

-- 1) Hostinger SMTP settings ----------------------------------
INSERT INTO settings (category, `key`, value) VALUES
    ('Email', 'smtp_host',        'smtp.hostinger.com'),
    ('Email', 'smtp_port',        '465'),
    ('Email', 'service_provider', 'smtp'),
    ('Email', 'smtp_username',    'booking@prismadot.com'),
    ('Email', 'smtp_password',    'i5@ZSmW$oi'),
    ('Email', 'from_email',       'booking@prismadot.com'),
    ('Email', 'from_name',        'Aventra Booking'),
    ('Email', 'reply_to_email',   'booking@prismadot.com')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- 2) Notification settings ------------------------------------
INSERT INTO settings (category, `key`, value) VALUES
    ('Notification', 'notify_customer_enabled',    '1'),
    ('Notification', 'notify_client_enabled',      '1'),
    ('Notification', 'notify_admin_enabled',       '1'),
    ('Notification', 'client_notification_email',  'booking@prismadot.com'),
    ('Notification', 'admin_notification_email',   'booking@prismadot.com'),
    ('Notification', 'booking_reminder_days',      '7,3,1'),
    ('Notification', 'booking_reminder_hours',     '24'),
    ('Notification', 'disabled_events',            '')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- 3) Scheduled emails queue (cron: /api/emails/process-scheduled)
CREATE TABLE IF NOT EXISTS scheduled_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(64) NOT NULL,
    to_email VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    template_name VARCHAR(100) NULL,
    payload LONGTEXT NULL,
    reminder_type VARCHAR(50) NOT NULL DEFAULT 'generic',
    scheduled_for DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    INDEX idx_due (status, scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Missing content for previously-seeded templates ----------

-- Payment Received
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-payment-received', 'en', 'Payment Received - {{tourName}}',
'<html><body><h1>Payment Received</h1><p>Dear {{customerName}},</p><p>We have received your payment for {{tourName}}.</p><p><strong>Payment Details:</strong></p><ul><li>Amount: {{amount}}</li><li>Transaction ID: {{transactionId}}</li><li>Date: {{paymentDate}}</li></ul><p><a href="{{bookingLink}}">View Your Booking</a></p><p>Thank you for your payment!</p></body></html>',
'Payment received for {{tourName}}. Amount: {{amount}}, Transaction: {{transactionId}}. View booking: {{bookingLink}}'),
('template-payment-received', 'sv', 'Betalning mottagen - {{tourName}}',
'<html><body><h1>Betalning mottagen</h1><p>Hej {{customerName}},</p><p>Vi har mottagit din betalning för {{tourName}}.</p><p><strong>Betalningsdetaljer:</strong></p><ul><li>Belopp: {{amount}}</li><li>Transaktions-ID: {{transactionId}}</li><li>Datum: {{paymentDate}}</li></ul><p><a href="{{bookingLink}}">Visa din bokning</a></p><p>Tack för din betalning!</p></body></html>',
'Betalning mottagen för {{tourName}}. Belopp: {{amount}}, Transaktion: {{transactionId}}. Visa bokning: {{bookingLink}}');

-- 24 Hour Reminder
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-reminder-24h', 'en', 'Reminder: {{tourName}} starts in 24 hours',
'<html><body><h1>Your tour starts tomorrow!</h1><p>Dear {{customerName}},</p><p>This is a reminder that your tour {{tourName}} starts in 24 hours.</p><p><strong>Booking Details:</strong></p><ul><li>Tour: {{tourName}}</li><li>Date: {{tourDate}}</li><li>Time: {{tourTime}}</li><li>Guests: {{guestCount}}</li></ul><p><a href="{{bookingLink}}">View Your Booking</a></p><p>We look forward to seeing you!</p></body></html>',
'Reminder: {{tourName}} starts in 24 hours on {{tourDate}}. View booking: {{bookingLink}}'),
('template-reminder-24h', 'sv', 'Påminnelse: {{tourName}} börjar om 24 timmar',
'<html><body><h1>Din tur börjar imorgon!</h1><p>Hej {{customerName}},</p><p>Detta är en påminnelse om att din tur {{tourName}} börjar om 24 timmar.</p><p><strong>Bokningsdetaljer:</strong></p><ul><li>Tur: {{tourName}}</li><li>Datum: {{tourDate}}</li><li>Tid: {{tourTime}}</li><li>Gäster: {{guestCount}}</li></ul><p><a href="{{bookingLink}}">Visa din bokning</a></p><p>Vi ser fram emot att träffa dig!</p></body></html>',
'Påminnelse: {{tourName}} börjar om 24 timmar den {{tourDate}}. Visa bokning: {{bookingLink}}');

-- 3 Day Reminder
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-reminder-3d', 'en', 'Reminder: {{tourName}} in 3 days',
'<html><body><h1>Your tour is coming up!</h1><p>Dear {{customerName}},</p><p>This is a reminder that your tour {{tourName}} starts in 3 days.</p><p><strong>Booking Details:</strong></p><ul><li>Tour: {{tourName}}</li><li>Date: {{tourDate}}</li><li>Time: {{tourTime}}</li><li>Guests: {{guestCount}}</li><li>Total Price: {{totalPrice}}</li></ul><p><a href="{{bookingLink}}">View Your Booking</a></p><p>We look forward to seeing you!</p></body></html>',
'Reminder: {{tourName}} starts in 3 days on {{tourDate}}. View booking: {{bookingLink}}'),
('template-reminder-3d', 'sv', 'Påminnelse: {{tourName}} om 3 dagar',
'<html><body><h1>Din tur närmar sig!</h1><p>Hej {{customerName}},</p><p>Detta är en påminnelse om att din tur {{tourName}} börjar om 3 dagar.</p><p><strong>Bokningsdetaljer:</strong></p><ul><li>Tur: {{tourName}}</li><li>Datum: {{tourDate}}</li><li>Tid: {{tourTime}}</li><li>Gäster: {{guestCount}}</li><li>Totalt pris: {{totalPrice}}</li></ul><p><a href="{{bookingLink}}">Visa din bokning</a></p><p>Vi ser fram emot att träffa dig!</p></body></html>',
'Påminnelse: {{tourName}} börjar om 3 dagar den {{tourDate}}. Visa bokning: {{bookingLink}}');

-- 5) New lifecycle templates -----------------------------------
INSERT IGNORE INTO email_templates (id, name, description, category, status, version, is_default, tags, created_by, created_date) VALUES
('template-booking-received',        'Booking Request Received',    'Sent when a new booking is created (pending approval)', 'BOOKING',      'ACTIVE', 1, 1, '["booking","pending"]',      'system', NOW()),
('template-booking-rejected',        'Booking Rejected',            'Sent when a booking is rejected by the client',         'CANCELLATION', 'ACTIVE', 1, 1, '["booking","rejected"]',     'system', NOW()),
('template-booking-cancelled-client','Booking Cancelled by Client', 'Sent when the client/business cancels a booking',       'CANCELLATION', 'ACTIVE', 1, 1, '["booking","cancellation"]', 'system', NOW()),
('template-booking-rescheduled',     'Booking Rescheduled',         'Sent when the booking date/time changes',               'BOOKING',      'ACTIVE', 1, 1, '["booking","reschedule"]',   'system', NOW()),
('template-booking-completed',       'Booking Completed',           'Sent after the tour/service is completed (+ review)',   'BOOKING',      'ACTIVE', 1, 1, '["booking","completed"]',    'system', NOW()),
('template-payment-failed',          'Payment Failed',              'Sent when a payment attempt fails',                     'PAYMENT',      'ACTIVE', 1, 1, '["payment","failed"]',      'system', NOW()),
('template-payment-refunded',        'Payment Refunded',            'Sent when a refund is processed',                       'PAYMENT',      'ACTIVE', 1, 1, '["payment","refund"]',      'system', NOW()),
('template-payment-reminder',        'Payment Reminder',            'Sent to remind the customer about remaining balance',   'PAYMENT',      'ACTIVE', 1, 1, '["payment","reminder"]',    'system', NOW());

-- Booking Request Received
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-received', 'en', 'Booking Request Received - {{tourName}}',
'<html><body><h1>Booking Request Received</h1><p>Dear {{customerName}},</p><p>We have received your booking request for {{tourName}}. It is currently pending confirmation.</p><p><strong>Booking Details:</strong></p><ul><li>Reference: {{bookingReference}}</li><li>Tour: {{tourName}}</li><li>Date: {{tourDate}}</li><li>Guests: {{guestCount}}</li><li>Total Price: {{totalPrice}} {{currency}}</li></ul><p><a href="{{bookingLink}}">View Your Booking</a></p><p>You will receive another email once your booking is confirmed.</p></body></html>',
'Booking request received for {{tourName}} (ref {{bookingReference}}) on {{tourDate}}. View: {{bookingLink}}'),
('template-booking-received', 'sv', 'Bokningsförfrågan mottagen - {{tourName}}',
'<html><body><h1>Bokningsförfrågan mottagen</h1><p>Hej {{customerName}},</p><p>Vi har mottagit din bokningsförfrågan för {{tourName}}. Den väntar på bekräftelse.</p><p><strong>Bokningsdetaljer:</strong></p><ul><li>Referens: {{bookingReference}}</li><li>Tur: {{tourName}}</li><li>Datum: {{tourDate}}</li><li>Gäster: {{guestCount}}</li><li>Totalt pris: {{totalPrice}} {{currency}}</li></ul><p><a href="{{bookingLink}}">Visa din bokning</a></p><p>Du får ett nytt e-postmeddelande när bokningen bekräftats.</p></body></html>',
'Bokningsförfrågan mottagen för {{tourName}} (ref {{bookingReference}}) den {{tourDate}}. Visa: {{bookingLink}}');

-- Booking Rejected
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-rejected', 'en', 'Booking Request Declined - {{tourName}}',
'<html><body><h1>Booking Request Declined</h1><p>Dear {{customerName}},</p><p>Unfortunately your booking request for {{tourName}} on {{tourDate}} could not be accepted.</p><p><strong>Reference:</strong> {{bookingReference}}</p><p><strong>Reason:</strong> {{reason}}</p><p>Any amount paid will be refunded within 5-10 business days. Please contact us to discuss alternatives.</p></body></html>',
'Your booking {{bookingReference}} for {{tourName}} was declined. Reason: {{reason}}'),
('template-booking-rejected', 'sv', 'Bokningsförfrågan avböjd - {{tourName}}',
'<html><body><h1>Bokningsförfrågan avböjd</h1><p>Hej {{customerName}},</p><p>Tyvärr kunde din bokningsförfrågan för {{tourName}} den {{tourDate}} inte accepteras.</p><p><strong>Referens:</strong> {{bookingReference}}</p><p><strong>Anledning:</strong> {{reason}}</p><p>Eventuellt betalat belopp återbetalas inom 5-10 arbetsdagar. Kontakta oss gärna för alternativ.</p></body></html>',
'Din bokning {{bookingReference}} för {{tourName}} avböjdes. Anledning: {{reason}}');

-- Booking Cancelled by Client
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-cancelled-client', 'en', 'Booking Cancelled - {{tourName}}',
'<html><body><h1>Booking Cancelled</h1><p>Dear {{customerName}},</p><p>We regret to inform you that your booking for {{tourName}} on {{tourDate}} has been cancelled by us.</p><p><strong>Reference:</strong> {{bookingReference}}</p><p><strong>Reason:</strong> {{reason}}</p><p><strong>Refund Amount:</strong> {{refundAmount}} {{currency}}</p><p>The refund will be processed within 5-10 business days. We apologize for the inconvenience.</p></body></html>',
'Your booking {{bookingReference}} for {{tourName}} was cancelled. Refund: {{refundAmount}} {{currency}}'),
('template-booking-cancelled-client', 'sv', 'Bokning avbokad - {{tourName}}',
'<html><body><h1>Bokning avbokad</h1><p>Hej {{customerName}},</p><p>Vi beklagar att din bokning för {{tourName}} den {{tourDate}} har avbokats av oss.</p><p><strong>Referens:</strong> {{bookingReference}}</p><p><strong>Anledning:</strong> {{reason}}</p><p><strong>Återbetalningsbelopp:</strong> {{refundAmount}} {{currency}}</p><p>Återbetalningen behandlas inom 5-10 arbetsdagar. Vi ber om ursäkt för besväret.</p></body></html>',
'Din bokning {{bookingReference}} för {{tourName}} har avbokats. Återbetalning: {{refundAmount}} {{currency}}');

-- Booking Rescheduled
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-rescheduled', 'en', 'Booking Rescheduled - {{tourName}}',
'<html><body><h1>Booking Rescheduled</h1><p>Dear {{customerName}},</p><p>Your booking for {{tourName}} has been rescheduled.</p><p><strong>Reference:</strong> {{bookingReference}}</p><p><strong>Previous Date:</strong> {{previousDate}}</p><p><strong>New Date:</strong> {{newDate}}</p><p><strong>Guests:</strong> {{guestCount}}</p><p><a href="{{bookingLink}}">View Your Booking</a></p></body></html>',
'Booking {{bookingReference}} rescheduled: {{previousDate}} -> {{newDate}}. View: {{bookingLink}}'),
('template-booking-rescheduled', 'sv', 'Bokning ombokad - {{tourName}}',
'<html><body><h1>Bokning ombokad</h1><p>Hej {{customerName}},</p><p>Din bokning för {{tourName}} har bokats om.</p><p><strong>Referens:</strong> {{bookingReference}}</p><p><strong>Tidigare datum:</strong> {{previousDate}}</p><p><strong>Nytt datum:</strong> {{newDate}}</p><p><strong>Gäster:</strong> {{guestCount}}</p><p><a href="{{bookingLink}}">Visa din bokning</a></p></body></html>',
'Bokning {{bookingReference}} ombokad: {{previousDate}} -> {{newDate}}. Visa: {{bookingLink}}');

-- Booking Completed
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-booking-completed', 'en', 'Thank You - {{tourName}} Completed',
'<html><body><h1>Thank you for joining us!</h1><p>Dear {{customerName}},</p><p>We hope you enjoyed {{tourName}} on {{tourDate}}. Your booking {{bookingReference}} is now complete.</p><p>We would love to hear about your experience:</p><p><a href="{{reviewLink}}">Leave a Review</a></p><p>We hope to see you on your next adventure!</p></body></html>',
'Thank you for joining {{tourName}}! Leave a review: {{reviewLink}}'),
('template-booking-completed', 'sv', 'Tack - {{tourName}} genomförd',
'<html><body><h1>Tack för att du följde med oss!</h1><p>Hej {{customerName}},</p><p>Vi hoppas att du uppskattade {{tourName}} den {{tourDate}}. Din bokning {{bookingReference}} är nu genomförd.</p><p>Vi skulle gärna höra om din upplevelse:</p><p><a href="{{reviewLink}}">Lämna en recension</a></p><p>Vi hoppas att se dig på nästa äventyr!</p></body></html>',
'Tack för att du följde med på {{tourName}}! Lämna en recension: {{reviewLink}}');

-- Payment Failed
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-payment-failed', 'en', 'Payment Failed - {{tourName}}',
'<html><body><h1>Payment Failed</h1><p>Dear {{customerName}},</p><p>Unfortunately the payment for your booking {{bookingReference}} ({{tourName}}) could not be processed.</p><p><strong>Amount:</strong> {{amount}} {{currency}}</p><p>Please try again:</p><p><a href="{{paymentLink}}">Retry Payment</a></p><p>If the problem persists, please contact us.</p></body></html>',
'Payment failed for booking {{bookingReference}} ({{tourName}}). Retry: {{paymentLink}}'),
('template-payment-failed', 'sv', 'Betalning misslyckades - {{tourName}}',
'<html><body><h1>Betalning misslyckades</h1><p>Hej {{customerName}},</p><p>Tyvärr kunde betalningen för din bokning {{bookingReference}} ({{tourName}}) inte genomföras.</p><p><strong>Belopp:</strong> {{amount}} {{currency}}</p><p>Försök igen:</p><p><a href="{{paymentLink}}">Försök betala igen</a></p><p>Kontakta oss om problemet kvarstår.</p></body></html>',
'Betalning misslyckades för bokning {{bookingReference}} ({{tourName}}). Försök igen: {{paymentLink}}');

-- Payment Refunded
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-payment-refunded', 'en', 'Refund Processed - {{tourName}}',
'<html><body><h1>Refund Processed</h1><p>Dear {{customerName}},</p><p>A refund has been processed for your booking {{bookingReference}} ({{tourName}}).</p><p><strong>Refund Amount:</strong> {{refundAmount}} {{currency}}</p><p>The amount will appear on your account within 5-10 business days.</p><p><a href="{{bookingLink}}">View Your Booking</a></p></body></html>',
'Refund of {{refundAmount}} {{currency}} processed for booking {{bookingReference}}.'),
('template-payment-refunded', 'sv', 'Återbetalning genomförd - {{tourName}}',
'<html><body><h1>Återbetalning genomförd</h1><p>Hej {{customerName}},</p><p>En återbetalning har genomförts för din bokning {{bookingReference}} ({{tourName}}).</p><p><strong>Återbetalningsbelopp:</strong> {{refundAmount}} {{currency}}</p><p>Beloppet syns på ditt konto inom 5-10 arbetsdagar.</p><p><a href="{{bookingLink}}">Visa din bokning</a></p></body></html>',
'Återbetalning av {{refundAmount}} {{currency}} genomförd för bokning {{bookingReference}}.');

-- Payment Reminder
INSERT IGNORE INTO email_template_content (template_id, language, subject, html_content, text_content) VALUES
('template-payment-reminder', 'en', 'Reminder: Payment Due for {{tourName}}',
'<html><body><h1>Payment Reminder</h1><p>Dear {{customerName}},</p><p>This is a reminder that the remaining balance for your booking {{bookingReference}} ({{tourName}}) is due.</p><p><strong>Remaining Amount:</strong> {{remainingAmount}} {{currency}}</p><p><strong>Due Date:</strong> {{paymentDueDate}}</p><p><a href="{{paymentLink}}">Pay Now</a></p><p><a href="{{bookingLink}}">View Your Booking</a></p></body></html>',
'Reminder: {{remainingAmount}} {{currency}} due for booking {{bookingReference}} ({{tourName}}). Pay: {{paymentLink}}'),
('template-payment-reminder', 'sv', 'Påminnelse: Betalning för {{tourName}}',
'<html><body><h1>Betalningspåminnelse</h1><p>Hej {{customerName}},</p><p>Detta är en påminnelse om att resterande belopp för din bokning {{bookingReference}} ({{tourName}}) förfaller.</p><p><strong>Kvarvarande belopp:</strong> {{remainingAmount}} {{currency}}</p><p><strong>Förfallodatum:</strong> {{paymentDueDate}}</p><p><a href="{{paymentLink}}">Betala nu</a></p><p><a href="{{bookingLink}}">Visa din bokning</a></p></body></html>',
'Påminnelse: {{remainingAmount}} {{currency}} förfaller för bokning {{bookingReference}} ({{tourName}}). Betala: {{paymentLink}}');
