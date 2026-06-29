-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_leads_captura.sql
-- ESTADO    : Pendiente de autorización explícita del Arquitecto (Mandamiento 9)
--             antes de ejecutarse contra la BD remota (tourfindycom_ajedrez_db).
-- PROPÓSITO : Soporta el formulario de captación del Lead Magnet
--             ("Botiquín Musical de Emergencia") consumido por
--             api/captura_lead.php (pendiente de construir).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `leads_captura` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `origen` VARCHAR(60) NOT NULL DEFAULT 'home_hero',
    `sincronizado_mailerlite` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_leads_captura_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
