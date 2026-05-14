-- Production fix: allow multiple booking items per one m_payment_id.
-- Run this once on live DB.

-- Drop legacy unique index if present (common name from column-level UNIQUE)
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_payment_attempts'
    AND INDEX_NAME = 'm_payment_id'
    AND NON_UNIQUE = 0
);
SET @sql = IF(@idx_exists > 0, 'ALTER TABLE booking_payment_attempts DROP INDEX m_payment_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop alternate unique index name if used
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_payment_attempts'
    AND INDEX_NAME = 'uq_bpa_m_payment_id'
    AND NON_UNIQUE = 0
);
SET @sql = IF(@idx_exists > 0, 'ALTER TABLE booking_payment_attempts DROP INDEX uq_bpa_m_payment_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure fast lookup index exists for ITN/status checks
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_payment_attempts'
    AND INDEX_NAME = 'idx_bpa_payment_id'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE booking_payment_attempts ADD INDEX idx_bpa_payment_id (m_payment_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
