-- Multi-tenant templates, labels, settings, overrides, roles

-- 1) Templates y mapping
CREATE TABLE IF NOT EXISTS tenant_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  default_labels_json TEXT NULL,
  default_settings_json TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tenant_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_template_features (
  template_id INT UNSIGNED NOT NULL,
  feature_id INT UNSIGNED NOT NULL,
  enabled_default TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (template_id, feature_id),
  KEY idx_ttf_feature (feature_id),
  CONSTRAINT fk_ttf_template FOREIGN KEY (template_id) REFERENCES tenant_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_ttf_feature FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_tenant_template (
  academy_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (academy_id),
  KEY idx_att_template (template_id),
  CONSTRAINT fk_att_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_template FOREIGN KEY (template_id) REFERENCES tenant_templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Labels y Settings por tenant
CREATE TABLE IF NOT EXISTS tenant_labels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academy_id INT UNSIGNED NOT NULL,
  label_key VARCHAR(128) NOT NULL,
  label_value VARCHAR(255) NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tenant_label (academy_id, label_key),
  CONSTRAINT fk_tenant_labels_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academy_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(128) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tenant_setting (academy_id, setting_key),
  CONSTRAINT fk_tenant_settings_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Overrides de plataforma
CREATE TABLE IF NOT EXISTS academy_feature_overrides (
  academy_id INT UNSIGNED NOT NULL,
  feature_id INT UNSIGNED NOT NULL,
  mode ENUM('inherit','force_on','force_off') NOT NULL DEFAULT 'inherit',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (academy_id, feature_id),
  CONSTRAINT fk_fo_academy FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
  CONSTRAINT fk_fo_feature FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Roles (minimo cambio)
ALTER TABLE users ADD COLUMN platform_role ENUM('platform_owner','platform_admin','support_agent') DEFAULT NULL;
ALTER TABLE users ADD COLUMN tenant_role ENUM('tenant_owner','tenant_manager','tenant_staff','tenant_viewer') DEFAULT NULL;
ALTER TABLE users ADD INDEX idx_users_platform_role (platform_role);
ALTER TABLE users ADD INDEX idx_users_tenant_role (tenant_role);

-- 5) Seeds de templates
INSERT INTO tenant_templates (code, name, description, default_labels_json, default_settings_json) VALUES
('SERVICE', 'Servicio', 'Plantilla base para servicios. Activa modulos a medida.',
 '{"menu.dashboard":"Dashboard","menu.core.users":"Usuarios","menu.core.settings":"Configuracion","menu.core.calendar":"Calendario","menu.core.notifications":"Notificaciones","menu.core.tickets":"Soporte"}',
 '{}'),
('RESTAURANT', 'Restaurante', 'Reservas, sala, turnos y soporte.',
 '{"menu.dashboard":"Dashboard","menu.rest.reservations":"Reservas","menu.rest.floorplan":"Plano de sala","menu.rest.waitlist":"Lista de espera","menu.rest.shifts":"Turnos","menu.rest.menu":"Carta digital","menu.rest.analytics":"Estadisticas","menu.core.notifications":"Notificaciones","menu.core.tickets":"Soporte"}',
 '{"reservations.duration_minutes":90,"reservations.default_pax":2,"waitlist.enabled":1,"restaurant.floorplan.background_url":"assets/floorplans/default_restaurant.svg"}'),
('BAR', 'Bar', 'Eventos, turnos, notificaciones y soporte.',
 '{"menu.dashboard":"Dashboard","menu.core.calendar":"Eventos","menu.rest.floorplan":"Plano de sala","menu.rest.reservations":"Reservas","menu.rest.shifts":"Turnos","menu.rest.analytics":"Estadisticas","menu.core.notifications":"Notificaciones","menu.core.tickets":"Soporte"}',
 '{"reservations.enabled":0,"events.default_duration_minutes":120,"restaurant.floorplan.background_url":"assets/floorplans/default_bar.svg"}'),
('HAIRDRESSER', 'Peluqueria', 'Citas, servicios, agenda y soporte.',
 '{"menu.dashboard":"Dashboard","menu.biz.appointments":"Citas","menu.biz.workflows":"Servicios","menu.biz.shifts":"Agenda de personal","menu.core.notifications":"Recordatorios","menu.core.tickets":"Soporte"}',
 '{"appointments.default_duration_minutes":45,"appointments.buffer_minutes":10}'),
('EDUCATION', 'Educacion', 'Cursos, entregas, calendario y soporte.',
 '{"menu.dashboard":"Dashboard","menu.edu.courses":"Cursos","menu.edu.assignments":"Entregas y tareas","menu.edu.exams":"Examenes","menu.edu.library":"Biblioteca","menu.core.calendar":"Calendario","menu.core.notifications":"Notificaciones","menu.core.tickets":"Soporte"}',
 '{"assignments.default_due_days":7,"notifications.daily_digest":1}')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  default_labels_json = VALUES(default_labels_json),
  default_settings_json = VALUES(default_settings_json);

-- 6) Mapping de features por template
INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 1
FROM tenant_templates t
JOIN features f ON f.code IN ('core.dashboard','core.users','core.settings','core.notifications','core.tickets')
WHERE t.code IN ('SERVICE','RESTAURANT','BAR','HAIRDRESSER','EDUCATION')
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);

INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 1
FROM tenant_templates t
JOIN features f ON f.code IN ('core.calendar')
WHERE t.code = 'SERVICE'
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);

INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 1
FROM tenant_templates t
JOIN features f ON f.code IN ('rest.reservations','rest.floorplan','rest.shifts','rest.waitlist','rest.menu','rest.analytics','rest.kds')
WHERE t.code = 'RESTAURANT'
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);

INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 1
FROM tenant_templates t
JOIN features f ON f.code IN ('core.calendar','rest.floorplan','rest.shifts','rest.analytics')
WHERE t.code = 'BAR'
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);

INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 0
FROM tenant_templates t
JOIN features f ON f.code = 'rest.reservations'
WHERE t.code = 'BAR'
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);

INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 1
FROM tenant_templates t
JOIN features f ON f.code IN ('biz.appointments','biz.workflows','biz.shifts','core.calendar')
WHERE t.code = 'HAIRDRESSER'
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);

INSERT INTO tenant_template_features (template_id, feature_id, enabled_default)
SELECT t.id, f.id, 1
FROM tenant_templates t
JOIN features f ON f.code IN ('edu.courses','edu.assignments','edu.exams','edu.library','core.calendar')
WHERE t.code = 'EDUCATION'
ON DUPLICATE KEY UPDATE enabled_default = VALUES(enabled_default);
