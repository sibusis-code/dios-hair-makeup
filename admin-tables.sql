-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  role ENUM('admin', 'staff', 'manager') NOT NULL DEFAULT 'staff',
  is_active BOOLEAN NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stylists/staff table
CREATE TABLE IF NOT EXISTS stylists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150),
  phone VARCHAR(30),
  specialization VARCHAR(150),
  is_active BOOLEAN NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Booking notes/history table
CREATE TABLE IF NOT EXISTS booking_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES salon_bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_booking_id (booking_id),
  INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add CRM columns to salon_bookings in a MySQL-compatible idempotent way
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'stylist_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN stylist_id INT UNSIGNED NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'status_updated_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN status_updated_at TIMESTAMP NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'cancellation_reason');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN cancellation_reason TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'payment_method');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN payment_method VARCHAR(50) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'admin_notes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE salon_bookings ADD COLUMN admin_notes TEXT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND INDEX_NAME = 'idx_salon_bookings_stylist_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_salon_bookings_stylist_id ON salon_bookings(stylist_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_stylist');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE salon_bookings ADD CONSTRAINT fk_stylist FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salon_bookings' AND COLUMN_NAME = 'status');
SET @sql = IF(@col_exists = 1, 'ALTER TABLE salon_bookings MODIFY COLUMN status ENUM(\'pending\',\'confirmed\',\'paid\',\'completed\',\'cancelled\') NOT NULL DEFAULT \'pending\'', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Sample admin user (password: admin123)
-- To create more: hash password with password_hash('password', PASSWORD_BCRYPT)
INSERT IGNORE INTO admin_users (username, email, password_hash, first_name, last_name, role) VALUES
('admin', 'admin@dios.local', '$2y$12$IUrRaUYBQz0XYhWtg3OdE.4BaukMjmq9XKK/hqGcZVysUiXYRDUcC', 'Admin', 'User', 'admin');

-- Sample stylists
INSERT IGNORE INTO stylists (name, email, phone, specialization) VALUES
('Thandi', 'thandi@dios.local', '+27123456789', 'Braids & Cornrows'),
('Naledi', 'naledi@dios.local', '+27123456790', 'Makeup & Styling'),
('Kyla', 'kyla@dios.local', '+27123456791', 'Hair Colour & Styling');
