-- Creación tabla ticket_messages para Learnnect
-- Ajusta tipos/longitudes si tu esquema lo requiere

CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `sender` VARCHAR(64) NOT NULL DEFAULT 'cliente',
  `message` TEXT NOT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la columna tickets.id tiene otro nombre o tipo, ajústalo.
-- Opcional: añadir FOREIGN KEY si la tabla tickets existe con id INT UNSIGNED
-- ALTER TABLE `ticket_messages` ADD CONSTRAINT `fk_tm_tickets` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE;

