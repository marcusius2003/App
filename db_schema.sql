-- Esquema mínimo para Learnnect (ajústalo según necesites)
-- Revisa el nombre de la BD en `soporte.php` (variable $db). El proyecto usa 'iuconect' en el código.

CREATE DATABASE IF NOT EXISTS `iuconect` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `iuconect`;

-- Tabla tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` VARCHAR(64) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'nuevo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subject` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(128) DEFAULT NULL,
  `nombre` VARCHAR(191) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `priority` VARCHAR(32) DEFAULT 'normal',
  `message` TEXT DEFAULT NULL,
  `kanban_stage` VARCHAR(64) DEFAULT 'Nuevo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_id` (`ticket_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla ticket_messages
CREATE TABLE IF NOT EXISTS `ticket_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `sender` VARCHAR(64) NOT NULL DEFAULT 'cliente',
  `message` TEXT NOT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla ticket_tags
CREATE TABLE IF NOT EXISTS `ticket_tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_tag` (`tag_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla ticket_categories
CREATE TABLE IF NOT EXISTS `ticket_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla tags
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de ejemplo (opcional)
INSERT INTO ticket_categories (name) VALUES
('Incidencia'), ('Consulta'), ('Solicitud'), ('Mejora')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO tags (name) VALUES
('Prioridad Alta'), ('Urgente'), ('Administración')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Modulo de chats privados estilo Teams
CREATE TABLE IF NOT EXISTS `chat_threads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academy_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(191) DEFAULT NULL,
  `type` VARCHAR(32) NOT NULL DEFAULT 'direct',
  `visibility` VARCHAR(32) NOT NULL DEFAULT 'general',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_chat_threads_academy` (`academy_id`),
  INDEX `idx_chat_threads_activity` (`last_activity_at`),
  INDEX `idx_chat_threads_creator` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_participants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_thread_user` (`thread_id`, `user_id`),
  INDEX `idx_chat_participants_user` (`user_id`),
  CONSTRAINT `fk_chat_participants_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_chat_messages_thread` (`thread_id`),
  INDEX `idx_chat_messages_user` (`user_id`),
  CONSTRAINT `fk_chat_messages_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


