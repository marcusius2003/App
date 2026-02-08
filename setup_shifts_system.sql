-- =====================================================
-- SHIFT MANAGEMENT SYSTEM - DATABASE SCHEMA
-- =====================================================
-- Sistema completo de gestión de turnos para restaurantes
-- Incluye: horarios programados, fichajes, y notificaciones

-- 1. Tabla de Horarios Semanales Programados
CREATE TABLE IF NOT EXISTS shift_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academy_id INT NOT NULL,
  user_id INT NOT NULL,
  day_of_week TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  role_label VARCHAR(64) DEFAULT NULL COMMENT 'Cocina, Sala, Barra, Manager, etc.',
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_schedule_academy (academy_id),
  KEY idx_schedule_user (user_id),
  KEY idx_schedule_day (day_of_week),
  KEY idx_schedule_active (is_active),
  CONSTRAINT fk_schedule_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Registros de Fichaje (Clock In/Out)
CREATE TABLE IF NOT EXISTS clock_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academy_id INT NOT NULL,
  user_id INT NOT NULL,
  shift_schedule_id INT UNSIGNED DEFAULT NULL COMMENT 'Referencia al horario programado si existe',
  clock_in DATETIME NOT NULL,
  clock_out DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL COMMENT 'Notas opcionales del fichaje',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clock_academy (academy_id),
  KEY idx_clock_user (user_id),
  KEY idx_clock_in (clock_in),
  KEY idx_clock_out (clock_out),
  KEY idx_clock_active (clock_out),
  CONSTRAINT fk_clock_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
  CONSTRAINT fk_clock_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_clock_schedule FOREIGN KEY (shift_schedule_id) REFERENCES shift_schedules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Índices adicionales para optimización de consultas
CREATE INDEX idx_clock_active_workers ON clock_records(academy_id, clock_out);
CREATE INDEX idx_schedule_user_day ON shift_schedules(user_id, day_of_week, is_active);

-- 4. Verificar que la tabla restaurant_shifts existe (compatibilidad)
-- Si no existe, crearla para mantener compatibilidad con código existente
CREATE TABLE IF NOT EXISTS restaurant_shifts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academy_id INT NOT NULL,
  user_id INT NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME DEFAULT NULL,
  role_label VARCHAR(64) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rest_shift_academy (academy_id),
  KEY idx_rest_shift_user (user_id),
  CONSTRAINT fk_rest_shift_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
  CONSTRAINT fk_rest_shift_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Datos de ejemplo (opcional - comentado por defecto)
-- Descomentar para insertar datos de prueba

/*
-- Ejemplo: Horario semanal para un usuario
INSERT INTO shift_schedules (academy_id, user_id, day_of_week, start_time, end_time, role_label) VALUES
(1, 2, 1, '09:00:00', '17:00:00', 'Sala'),
(1, 2, 2, '09:00:00', '17:00:00', 'Sala'),
(1, 2, 3, '09:00:00', '17:00:00', 'Sala'),
(1, 2, 4, '09:00:00', '17:00:00', 'Sala'),
(1, 2, 5, '09:00:00', '17:00:00', 'Sala');
*/

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================
