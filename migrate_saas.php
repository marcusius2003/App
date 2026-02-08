<?php
// migrate_saas.php
require_once __DIR__ . '/admin/config/db.php';

$database = new Database();
$pdo = $database->getConnection();

$sql = <<<SQL
-- 1. Tenants (Academias)
CREATE TABLE IF NOT EXISTS `academies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `subdomain` VARCHAR(64) UNIQUE,
  `contact_email` VARCHAR(191),
  `logo_url` VARCHAR(255),
  `status` ENUM('active', 'suspended', 'pending') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Features (Registry) - Existing table, just ensuring columns or data
-- Note: 'features' exists with code, label, category, is_core

-- 3. Academy Features
CREATE TABLE IF NOT EXISTS `academy_features` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academy_id` INT UNSIGNED NOT NULL,
  `feature_id` INT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) DEFAULT 1,
  `config_json` JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ac_feat` (`academy_id`, `feature_id`),
  FOREIGN KEY (`academy_id`) REFERENCES `academies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`feature_id`) REFERENCES `features`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Roles
-- Existing roles table has: id, academy_id, code, name, is_system
-- We don't need to create it if it exists, just maybe add columns if missing? 
-- But describe said it exists.

-- 5. Permissions
-- Existing permissions table has: id, code, label, feature_id
-- We should populate it with seed data using 'code', 'label'

-- 6. Role Permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Templates
CREATE TABLE IF NOT EXISTS `templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) UNIQUE,
  `name` VARCHAR(64) NOT NULL,
  `description` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. SEEDS - TEMPLATES
INSERT INTO `templates` (`code`, `name`, `description`) VALUES 
('EDUCATION', 'Educación', 'Cursos, entregas, evaluación, calendario, chat y biblioteca.'),
('RESTAURANT', 'Restauración', 'Gestión de turnos, inventario, reservas y personal.'),
('SERVICES', 'Servicios', 'Citas, clientes, facturación y proyectos.'),
('RETAIL', 'Retail', 'Catálogo, ventas, stock y fidelización.'),
('HEALTH', 'Salud', 'Pacientes, historias clínicas, citas y recordatorios.')
ON DUPLICATE KEY UPDATE description = VALUES(description), name = VALUES(name);

-- 9. SEEDS - FEATURES
INSERT INTO `features` (`code`, `label`, `category`, `is_core`) VALUES
-- CORE
('core.dashboard', 'Dashboard', 'core', 1),
('core.users', 'Usuarios y Roles', 'core', 1),
('core.settings', 'Configuración', 'core', 1),
('core.notifications', 'Notificaciones', 'core', 1),
('core.documents', 'Documentos/Drive', 'core', 0),
('core.calendar', 'Calendario Global', 'core', 0),
('core.tickets', 'Soporte/Incidencias', 'core', 0),
-- EDUCACIÓN
('edu.courses', 'Cursos y Aulas', 'education', 0),
('edu.assignments', 'Entregas/Tareas', 'education', 0),
('edu.exams', 'Exámenes', 'education', 0),
('edu.library', 'Biblioteca', 'education', 0),
-- BUSINESS
('biz.shifts', 'Gestión de Turnos', 'business', 0),
('biz.projects', 'Proyectos', 'business', 0),
('biz.workflows', 'Workflows', 'business', 0),
('biz.appointments', 'Citas/Agenda', 'business', 0),
('biz.inventory', 'Inventario', 'business', 0),
('biz.operations', 'Operaciones', 'business', 0)
ON DUPLICATE KEY UPDATE label = VALUES(label), category = VALUES(category), is_core = VALUES(is_core);

-- 10. SEEDS - PERMISSIONS (using code, label)
INSERT INTO `permissions` (`code`, `label`) VALUES
('users.view', 'Ver usuarios'),
('users.create', 'Crear usuarios'),
('users.edit', 'Editar usuarios'),
('courses.view', 'Ver cursos'),
('courses.create', 'Crear cursos'),
('shifts.view', 'Ver turnos'),
('shifts.manage', 'Gestionar turnos')
ON DUPLICATE KEY UPDATE label = VALUES(label);

SQL;

try {
    $pdo->exec($sql);
    echo "Migration completed successfully.\n";
    
    // Check Academy 1 for Education
    $academyId = 1;
    $stmtChk = $pdo->query("SELECT id FROM academies WHERE id = 1");
    if (!$stmtChk->fetch()) {
         $pdo->exec("INSERT INTO academies (id, name, subdomain, status) VALUES (1, 'Main Academy', 'main', 'active')");
    }
    
    $stmt = $pdo->query("SELECT id FROM features WHERE category = 'education' OR category = 'core'");
    $featIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $insertFeat = $pdo->prepare("INSERT IGNORE INTO academy_features (academy_id, feature_id, enabled) VALUES (1, ?, 1)");
    foreach ($featIds as $fid) {
        $insertFeat->execute([$fid]);
    }
    echo "Enabled Default Education/Core features for Academy ID 1.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
