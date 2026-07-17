-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_permisos_modulos.sql
-- ESTADO    : Autorizado por el Productor Tzunum (extensión directa del alcance
--             ya autorizado del sistema de login/dashboard — Mandamiento 9).
-- PROPÓSITO : Mapeo Dinámico de Permisos por Módulo (MODULO_01_LOGIN_Y_ACCESO
--             §6.1). super_admin siempre ve todo (no configurable); esta tabla
--             solo restringe selectivamente qué ve el rol `admin`. Fail-safe:
--             un módulo sin fila para un rol se considera habilitado.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `permisos_modulos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `modulo` VARCHAR(60) NOT NULL COMMENT 'Identificador estable: usuarios | seguridad',
    `visible_para_rol` VARCHAR(30) NOT NULL COMMENT 'Rol al que aplica esta fila (nunca super_admin)',
    `habilitado` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permisos_modulos_modulo_rol` (`modulo`, `visible_para_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permisos_modulos` (`modulo`, `visible_para_rol`, `habilitado`)
VALUES
    ('usuarios', 'admin', 1),
    ('seguridad', 'admin', 0)
ON DUPLICATE KEY UPDATE `modulo` = `modulo`;
