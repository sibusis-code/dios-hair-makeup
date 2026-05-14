-- ============================================================================
-- MIGRATION: Add missing columns to salon_bookings table
-- ============================================================================
-- Run this on your database to add the missing booking detail columns
-- This ensures all client booking information is captured and accessible

-- Add service column (captured from booking form)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'service');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN service VARCHAR(100) NULL AFTER appointment_time', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add location column (captured from booking form)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'location');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN location VARCHAR(100) NULL AFTER service', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add sub_type column (e.g., "color", "cut", "treatment" for hair)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'sub_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN sub_type VARCHAR(100) NULL AFTER location', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add hair_length column (e.g., "short", "medium", "long")
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'hair_length');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN hair_length VARCHAR(100) NULL AFTER sub_type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add client_notes column (special requests, allergies, preferences)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'client_notes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN client_notes TEXT NULL AFTER hair_length', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add staff_assignment column (which stylist is assigned - may differ from preferred)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'preferred_stylist');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN preferred_stylist VARCHAR(150) NULL AFTER hair_length', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add pf_payment_id (PayFast transaction ID from ITN response)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'pf_payment_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN pf_payment_id VARCHAR(100) NULL AFTER m_payment_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Create indexes for better query performance
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND INDEX_NAME = 'idx_service');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_service ON salon_bookings(service)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND INDEX_NAME = 'idx_location');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_location ON salon_bookings(location)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done
SELECT 'Migration completed: salon_bookings enhanced with booking details columns' AS status;
