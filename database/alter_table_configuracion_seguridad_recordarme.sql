-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/alter_table_configuracion_seguridad_recordarme.sql
-- ESTADO    : Autorizado por el Productor Tzunum (extensión directa del alcance
--             ya autorizado del sistema de login/dashboard — Mandamiento 9).
-- PROPÓSITO : Añade la duración de "Mantenerse registrado" (MODULO_01 §3.5/7.5)
--             a la fila única de configuracion_seguridad. Default del proyecto:
--             60 días (2 meses), orden explícita del Productor.
-- =============================================================================

ALTER TABLE `configuracion_seguridad`
    ADD COLUMN `duracion_recordarme_dias` SMALLINT UNSIGNED NOT NULL DEFAULT 60
        COMMENT 'Canónico: 60 (2 meses) o 120 (4 meses)'
        AFTER `politica_password`;

UPDATE `configuracion_seguridad` SET `duracion_recordarme_dias` = 60 WHERE `id` = 1;
