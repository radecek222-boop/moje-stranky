ALTER TABLE wgs_users ADD COLUMN registration_key_code VARCHAR(100) UNIQUE AFTER user_id;
ALTER TABLE wgs_users ADD COLUMN registration_key_type VARCHAR(20) AFTER registration_key_code;
CREATE INDEX idx_registration_key ON wgs_users(registration_key_code);
CREATE INDEX idx_user_email ON wgs_users(email);
