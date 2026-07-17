-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_historial_sesiones.sql
-- ESTADO    : Autorizado por el Productor Tzunum (Mandamiento 9).
-- PROPÓSITO : Orquestador de Sesiones en Vivo (MODULO_03_CRM_EVENTOS_EN_VIVO
--             §3). Requiere que `registro_interesados` exista primero
--             (Secuencia SQL→PHP) para la FK de la tabla de asistentes.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `historial_sesiones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fecha_hora` DATETIME NOT NULL,
    `enlace` VARCHAR(255) NOT NULL COMMENT 'URL de la videollamada — nunca se expone en el HTML público fuera de la ventana de acceso',
    `tema` VARCHAR(200) NULL COMMENT 'Libro/plática abordada',
    `creado_por` INT UNSIGNED NULL COMMENT 'usuarios.id de quien compartió la sesión',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `historial_sesiones_asistentes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sesion_id` INT UNSIGNED NOT NULL,
    `interesado_id` INT UNSIGNED NOT NULL,
    `token_checkin` CHAR(64) NOT NULL COMMENT 'Token opaco único por (sesión, interesado)',
    `notificado_en` DATETIME NULL,
    `checkin_en` DATETIME NULL COMMENT 'NULL = nunca asistió. NOT NULL = asistencia real confirmada',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_asistente_token` (`token_checkin`),
    UNIQUE KEY `uq_sesion_interesado` (`sesion_id`, `interesado_id`),
    CONSTRAINT `fk_asistente_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `historial_sesiones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_asistente_interesado` FOREIGN KEY (`interesado_id`) REFERENCES `registro_interesados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
