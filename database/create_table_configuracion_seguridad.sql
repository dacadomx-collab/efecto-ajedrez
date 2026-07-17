-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_configuracion_seguridad.sql
-- ESTADO    : Pendiente de autorización explícita del Arquitecto (Mandamiento 9)
--             antes de ejecutarse contra la BD remota (tourfindycom_ajedrez_db).
-- PROPÓSITO : Motor Dinámico de Políticas de Contraseña (MODULO_01_LOGIN_Y_ACCESO
--             §7). Fila única (id=1) con la política activa. Se siembra con
--             'media' por defecto para este proyecto (decisión del Arquitecto,
--             ver modulos/CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md).
--             Consumida por api/auth_helpers.php y api/configuracion_seguridad.php.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `configuracion_seguridad` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `politica_password` ENUM('simple', 'media', 'fuerte') NOT NULL DEFAULT 'media',
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion_seguridad` (`id`, `politica_password`)
VALUES (1, 'media')
ON DUPLICATE KEY UPDATE `id` = `id`;
