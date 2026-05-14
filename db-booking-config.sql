-- DB-driven booking configuration and business settings
-- Run this once on your MySQL database.

CREATE TABLE IF NOT EXISTS booking_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_key VARCHAR(100) NOT NULL UNIQUE,
  service_name VARCHAR(150) NOT NULL,
  category_label VARCHAR(150) NOT NULL,
  base_price DECIMAL(10,2) NOT NULL,
  requires_sub_type TINYINT(1) NOT NULL DEFAULT 0,
  requires_hair_length TINYINT(1) NOT NULL DEFAULT 0,
  sub_type_label VARCHAR(150) NULL,
  info_text TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_services_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_service_subtypes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  subtype_key VARCHAR(120) NOT NULL,
  subtype_label VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_booking_service_subtype (service_id, subtype_key),
  INDEX idx_booking_service_subtypes_active_sort (is_active, sort_order),
  CONSTRAINT fk_booking_service_subtypes_service
    FOREIGN KEY (service_id) REFERENCES booking_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_key VARCHAR(100) NOT NULL UNIQUE,
  location_name VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_locations_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_stylists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stylist_key VARCHAR(100) NOT NULL UNIQUE,
  stylist_name VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_stylists_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_time_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_key VARCHAR(50) NOT NULL UNIQUE,
  slot_label VARCHAR(120) NOT NULL,
  db_time TIME NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking_time_slots_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_service_stylists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  stylist_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_booking_service_stylist_location (service_id, stylist_id, location_id),
  INDEX idx_booking_service_stylists_active (is_active),
  CONSTRAINT fk_booking_service_stylists_service
    FOREIGN KEY (service_id) REFERENCES booking_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_service_stylists_stylist
    FOREIGN KEY (stylist_id) REFERENCES booking_stylists(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_service_stylists_location
    FOREIGN KEY (location_id) REFERENCES booking_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_service_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  slot_id INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_booking_service_slot (service_id, slot_id),
  INDEX idx_booking_service_slots_active (is_active),
  CONSTRAINT fk_booking_service_slots_service
    FOREIGN KEY (service_id) REFERENCES booking_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_service_slots_slot
    FOREIGN KEY (slot_id) REFERENCES booking_time_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_business_settings_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed services
INSERT INTO booking_services (service_key, service_name, category_label, base_price, requires_sub_type, requires_hair_length, sub_type_label, info_text, sort_order, is_active)
VALUES
('braids', 'Braids', 'Braiding Services', 1600.00, 1, 1, 'Type of Braids', 'Duration: 3-4 hours. Team: 1 client / 2 Braiders.', 10, 1),
('cornrows', 'Cornrows', 'Braiding Services', 1200.00, 1, 0, 'Cornrow Style', 'Duration: 2-3 hours.', 20, 1),
('ponytail', 'Ponytail', 'Hair Styling', 800.00, 1, 0, 'Ponytail Style', '', 30, 1),
('frontal-ponytail', 'Frontal Ponytail', 'Hair Styling', 1350.00, 0, 0, '', 'Swiss frontal closure + synthetic bundles.', 35, 1),
('relaxer', 'Relaxer', 'Hair Styling', 300.00, 0, 0, '', 'Dark n Lovely.', 37, 1),
('wig-installation', 'Wig Installation', 'Hair Styling', 1500.00, 1, 0, 'Wig Type', 'Pricing varies by wig type and customisation.', 40, 1),
('hair-colour', 'Hair Colour', 'Hair Styling', 1000.00, 1, 0, 'Hair Colour Service', 'Duration: 1-2 hours.', 50, 1),
('other-styling', 'Other Hair Styling', 'Hair Styling', 1000.00, 1, 0, 'Styling Type', '', 60, 1),
('makeup', 'Makeup Artistry', 'Makeup', 800.00, 1, 0, 'Makeup Type', '', 70, 1),
('bridal-makeup', 'Bridal Makeup', 'Makeup', 1350.00, 0, 0, '', '', 75, 1),
('mobile', 'Mobile Service', 'Other', 400.00, 1, 0, 'Mobile Service Type', 'Travel fee: Additional R200.', 80, 1),
('other', 'Other', 'Other', 1000.00, 1, 0, 'Other Service', '', 90, 1)
ON DUPLICATE KEY UPDATE
service_name = VALUES(service_name),
category_label = VALUES(category_label),
base_price = VALUES(base_price),
requires_sub_type = VALUES(requires_sub_type),
requires_hair_length = VALUES(requires_hair_length),
sub_type_label = VALUES(sub_type_label),
info_text = VALUES(info_text),
sort_order = VALUES(sort_order),
is_active = VALUES(is_active);

-- Seed locations
INSERT INTO booking_locations (location_key, location_name, sort_order, is_active)
VALUES
('midrand', 'Midrand Studio', 10, 1),
('copperleaf', 'Copperleaf Studio', 20, 1),
('mobile', 'Mobile (come to me)', 30, 1)
ON DUPLICATE KEY UPDATE
location_name = VALUES(location_name),
sort_order = VALUES(sort_order),
is_active = VALUES(is_active);

-- Seed stylists
INSERT INTO booking_stylists (stylist_key, stylist_name, sort_order, is_active)
VALUES
('caro', 'Caro', 10, 1),
('emma', 'Emma', 20, 1),
('patience', 'Patience', 30, 1),
('lincy', 'Lincy', 40, 1),
('charity', 'Charity', 50, 1),
('charmaine', 'Charmaine', 60, 1),
('pamela', 'Pamela', 70, 1),
('marlyn', 'Marlyn', 80, 1),
('ibongiwe', 'Ibongiwe', 90, 1)
ON DUPLICATE KEY UPDATE
stylist_name = VALUES(stylist_name),
sort_order = VALUES(sort_order),
is_active = VALUES(is_active);

-- Seed time slots
INSERT INTO booking_time_slots (slot_key, slot_label, db_time, sort_order, is_active)
VALUES
('07:30', '07:30 AM', '07:30:00', 10, 1),
('08:00', '08:00 AM', '08:00:00', 20, 1),
('09:00', '09:00 AM', '09:00:00', 30, 1),
('10:00', '10:00 AM', '10:00:00', 40, 1),
('11:00', '11:00 AM', '11:00:00', 50, 1),
('11:30', '11:30 AM', '11:30:00', 60, 1),
('12:00', '12:00 PM', '12:00:00', 70, 1),
('13:00', '01:00 PM', '13:00:00', 80, 1),
('14:00', '02:00 PM', '14:00:00', 90, 1),
('14:30', '02:30 PM', '14:30:00', 100, 1),
('15:00', '03:00 PM', '15:00:00', 110, 1),
('16:00', '04:00 PM', '16:00:00', 120, 1),
('17:00', '05:00 PM', '17:00:00', 130, 1),
('before-hours', 'Before Hours (extra R200)', '07:00:00', 140, 1),
('after-hours', 'After Hours (extra R200)', '18:00:00', 150, 1)
ON DUPLICATE KEY UPDATE
slot_label = VALUES(slot_label),
db_time = VALUES(db_time),
sort_order = VALUES(sort_order),
is_active = VALUES(is_active);

-- Seed service subtypes
INSERT INTO booking_service_subtypes (service_id, subtype_key, subtype_label, sort_order, is_active)
SELECT s.id, x.subtype_key, x.subtype_label, x.sort_order, 1
FROM booking_services s
JOIN (
  SELECT 'braids' AS service_key, 'knotless-braids' AS subtype_key, 'Knotless Braids' AS subtype_label, 10 AS sort_order
  UNION ALL SELECT 'braids', 'box-braids', 'Box Braids', 20
  UNION ALL SELECT 'braids', 'feed-in-braids', 'Feed-in Braids', 30
  UNION ALL SELECT 'braids', 'goddess-braids', 'Goddess Braids', 40
  UNION ALL SELECT 'braids', 'faux-locs', 'Faux Locs', 50
  UNION ALL SELECT 'cornrows', 'straight-back-cornrows', 'Straight-back Cornrows', 10
  UNION ALL SELECT 'cornrows', 'stitch-cornrows', 'Stitch Cornrows', 20
  UNION ALL SELECT 'cornrows', 'fulani-cornrows', 'Fulani Cornrows', 30
  UNION ALL SELECT 'cornrows', 'cornrows-with-extensions', 'Cornrows with Extensions', 40
  UNION ALL SELECT 'ponytail', 'sleek-ponytail', 'Sleek Ponytail', 10
  UNION ALL SELECT 'ponytail', 'curly-ponytail', 'Curly Ponytail', 20
  UNION ALL SELECT 'ponytail', 'braided-ponytail', 'Braided Ponytail', 30
  UNION ALL SELECT 'wig-installation', 'closure-install', 'Closure Install', 10
  UNION ALL SELECT 'wig-installation', 'frontal-install', 'Frontal Install', 20
  UNION ALL SELECT 'wig-installation', 'glueless-install', 'Glueless Install', 30
  UNION ALL SELECT 'hair-colour', 'full-colour', 'Full Colour', 10
  UNION ALL SELECT 'hair-colour', 'highlights', 'Highlights', 20
  UNION ALL SELECT 'hair-colour', 'root-touch-up', 'Root Touch-up', 30
  UNION ALL SELECT 'hair-colour', 'toner', 'Toner', 40
  UNION ALL SELECT 'other-styling', 'silk-press', 'Silk Press', 10
  UNION ALL SELECT 'other-styling', 'updo', 'Updo', 20
  UNION ALL SELECT 'other-styling', 'treatment-style', 'Treatment & Style', 30
  UNION ALL SELECT 'other-styling', 'custom-styling', 'Custom Styling', 40
  UNION ALL SELECT 'makeup', 'soft-glam', 'Soft Glam', 10
  UNION ALL SELECT 'makeup', 'full-glam', 'Full Glam', 20
  UNION ALL SELECT 'makeup', 'photoshoot-makeup', 'Photoshoot Makeup', 40
  UNION ALL SELECT 'mobile', 'hair-service-at-home', 'Hair Service at Home', 10
  UNION ALL SELECT 'mobile', 'makeup-at-home', 'Makeup at Home', 20
  UNION ALL SELECT 'mobile', 'hair-makeup-at-home', 'Hair + Makeup at Home', 30
  UNION ALL SELECT 'other', 'other-service', 'Other Service', 10
) x ON x.service_key = s.service_key
ON DUPLICATE KEY UPDATE
subtype_label = VALUES(subtype_label),
sort_order = VALUES(sort_order),
is_active = VALUES(is_active);

-- Disable legacy Bridal Makeup subtype under Makeup Artistry
UPDATE booking_service_subtypes bst
INNER JOIN booking_services bs ON bs.id = bst.service_id
SET bst.is_active = 0
WHERE bs.service_key = 'makeup' AND bst.subtype_key = 'bridal-makeup';

-- Braids only slot restrictions (if no rows for a service, all active slots are allowed)
INSERT INTO booking_service_slots (service_id, slot_id, is_active)
SELECT s.id, ts.id, 1
FROM booking_services s
INNER JOIN booking_time_slots ts ON ts.slot_key IN ('07:30', '11:30', '14:30')
WHERE s.service_key = 'braids'
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

-- Seed service-stylist mappings by location
INSERT INTO booking_service_stylists (service_id, stylist_id, location_id, is_active)
SELECT s.id, st.id, l.id, 1
FROM booking_services s
INNER JOIN booking_stylists st ON (
  (s.service_key IN ('braids', 'cornrows', 'ponytail', 'frontal-ponytail', 'relaxer', 'hair-colour', 'other-styling', 'mobile', 'other') AND st.stylist_key IN ('caro', 'emma', 'patience') AND 1=1)
  OR
  (s.service_key IN ('braids', 'cornrows', 'ponytail', 'frontal-ponytail', 'relaxer', 'hair-colour', 'other-styling', 'mobile', 'other') AND st.stylist_key IN ('lincy', 'charity') AND 1=1)
  OR
  (s.service_key = 'makeup' AND st.stylist_key IN ('charmaine', 'pamela'))
  OR
  (s.service_key = 'bridal-makeup' AND st.stylist_key IN ('charmaine', 'pamela'))
  OR
  (s.service_key = 'wig-installation' AND st.stylist_key IN ('marlyn', 'ibongiwe'))
)
INNER JOIN booking_locations l ON (
  (l.location_key = 'midrand' AND st.stylist_key IN ('caro', 'emma', 'patience', 'charmaine', 'pamela', 'marlyn', 'ibongiwe'))
  OR
  (l.location_key = 'copperleaf' AND st.stylist_key IN ('lincy', 'charity', 'charmaine', 'pamela', 'marlyn', 'ibongiwe'))
  OR
  (l.location_key = 'mobile')
)
WHERE s.is_active = 1 AND st.is_active = 1 AND l.is_active = 1
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

-- Seed business settings (editable without touching code)
INSERT INTO business_settings (setting_key, setting_value, is_active)
VALUES
('brand_name', 'DIOS Hair | Makeup', 1),
('phone_whatsapp', '073 266 8348', 1),
('phone_call', '073 266 8348', 1),
('whatsapp_url', 'https://wa.me/27732668348', 1),
('hours_midrand', 'Mon-Sat: 5am-6pm | Sun: Closed', 1),
('hours_copperleaf', 'Tue-Sat: 5am-6pm | Sun: Closed', 1),
('address_midrand', '5 Liebenberg Road, Noordwyk', 1),
('address_copperleaf', 'Copperleaf Golf & Country Estate (Appointment only)', 1)
ON DUPLICATE KEY UPDATE
setting_value = VALUES(setting_value),
is_active = VALUES(is_active);
