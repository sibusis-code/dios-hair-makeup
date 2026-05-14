-- Performance indexes for operations/maintenance

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_payment_attempts'
    AND INDEX_NAME = 'idx_bpa_status_created'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE booking_payment_attempts ADD INDEX idx_bpa_status_created (status, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_service_stylists'
    AND INDEX_NAME = 'idx_bss_service_location'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE booking_service_stylists ADD INDEX idx_bss_service_location (service_id, location_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'booking_service_slots'
    AND INDEX_NAME = 'idx_bsslots_service'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE booking_service_slots ADD INDEX idx_bsslots_service (service_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
