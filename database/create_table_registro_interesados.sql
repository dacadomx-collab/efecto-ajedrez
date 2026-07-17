-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_registro_interesados.sql
-- ESTADO    : Autorizado por el Productor Tzunum (extensión directa del alcance
--             ya autorizado del ecosistema Dashboard — Mandamiento 9).
-- PROPÓSITO : Captación de leads del Círculo de Lectura (MODULO_03_CRM_
--             EVENTOS_EN_VIVO §1/§2). Consumida por api/registro_interesado.php
--             y por el panel "Invitados Confirmados" del Dashboard.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `registro_interesados` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL COMMENT 'Requerido para la notificación masiva de sesiones (api/sesiones_compartir.php)',
    `edad` TINYINT UNSIGNED NULL,
    `ciudad` VARCHAR(120) NOT NULL,
    `estado` VARCHAR(120) NOT NULL,
    `ip` VARCHAR(45) NOT NULL COMMENT 'IPv4/IPv6 en claro — visible solo en el panel admin autenticado',
    `ip_pais` VARCHAR(80) NULL,
    `ip_estado` VARCHAR(80) NULL,
    `ip_ciudad` VARCHAR(80) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
