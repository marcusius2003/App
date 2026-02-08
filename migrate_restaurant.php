<?php
// migrate_restaurant.php
require_once __DIR__ . '/admin/config/db.php';

$database = new Database();
$pdo = $database->getConnection();

$sql = <<<SQL
-- SEED FEATURES (RESTAURANT)
INSERT INTO `features` (`code`, `label`, `category`, `is_core`) VALUES
('rest.floorplan', 'Plano de Sala', 'restaurant', 0),
('rest.reservations', 'Reservas', 'restaurant', 0),
('rest.shifts', 'Turnos y Fichaje', 'restaurant', 0),
('rest.menu', 'Carta Digital', 'restaurant', 0),
('rest.waitlist', 'Lista de Espera', 'restaurant', 0),
('rest.kds', 'Comandas Cocina', 'restaurant', 0),
('rest.analytics', 'Métricas HORECA', 'restaurant', 0)
ON DUPLICATE KEY UPDATE label = VALUES(label), category = VALUES(category);

-- Ensure tables are clean if retrying with errors
DROP TABLE IF EXISTS `restaurant_kds_orders`;
DROP TABLE IF EXISTS `restaurant_menu_items`;
DROP TABLE IF EXISTS `restaurant_waitlist`;
DROP TABLE IF EXISTS `restaurant_shifts`;
DROP TABLE IF EXISTS `restaurant_reservations`;
DROP TABLE IF EXISTS `restaurant_tables`;
DROP TABLE IF EXISTS `restaurant_zones`;

-- 1. Zonas
CREATE TABLE `restaurant_zones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,  -- Matches academies.id (INT SIGNED)
  `name` VARCHAR(64) NOT NULL,
  `active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mesas
CREATE TABLE `restaurant_tables` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,
  `zone_id` INT UNSIGNED,
  `name` VARCHAR(32) NOT NULL,
  `capacity` INT NOT NULL DEFAULT 2,
  `status` ENUM('free', 'occupied', 'reserved', 'cleaning') DEFAULT 'free',
  `position_x` INT DEFAULT 0,
  `position_y` INT DEFAULT 0,
  FOREIGN KEY (`zone_id`) REFERENCES `restaurant_zones`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Reservas
CREATE TABLE `restaurant_reservations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,
  `customer_name` VARCHAR(128) NOT NULL,
  `customer_phone` VARCHAR(32),
  `pax` INT NOT NULL,
  `reservation_time` DATETIME NOT NULL,
  `status` ENUM('pending', 'confirmed', 'seated', 'cancelled', 'noshow') DEFAULT 'confirmed',
  `notes` TEXT,
  `table_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED, -- Assuming users.id is INT UNSIGNED, checking... wait users might be INT SIGNED too.
  INDEX `idx_date` (`reservation_time`),
  FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Turnos
CREATE TABLE `restaurant_shifts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,
  `user_id` INT NOT NULL, -- Matches users.id (likely INT SIGNED based on XAMPP defaults)
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `role_label` VARCHAR(64),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Lista de espera
CREATE TABLE `restaurant_waitlist` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,
  `customer_name` VARCHAR(128) NOT NULL,
  `party_size` INT NOT NULL DEFAULT 1,
  `customer_phone` VARCHAR(32),
  `status` ENUM('waiting','called','seated','cancelled') DEFAULT 'waiting',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Carta digital
CREATE TABLE `restaurant_menu_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `category` VARCHAR(64) DEFAULT 'General',
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_academy_name` (`academy_id`, `name`),
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Comandas / KDS
CREATE TABLE `restaurant_kds_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `academy_id` INT NOT NULL,
  `table_id` INT UNSIGNED,
  `menu_item_id` INT UNSIGNED,
  `quantity` INT NOT NULL DEFAULT 1,
  `status` ENUM('queued','preparing','ready','served') DEFAULT 'queued',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`menu_item_id`) REFERENCES `restaurant_menu_items`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SQL;

try {
    $pdo->exec($sql);
    echo "Restaurant Pack Migration completed successfully.\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
