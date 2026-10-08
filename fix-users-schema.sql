-- Add columns used by public/api/users-*.php endpoints
ALTER TABLE users
  ADD COLUMN phone VARCHAR(50) NULL AFTER name,
  ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN invitation_token VARCHAR(64) NULL,
  ADD COLUMN invitation_expires_at DATETIME NULL,
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
