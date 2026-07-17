-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/alter_table_historial_sesiones_add_material.sql
-- ESTADO    : Autorizado por el Productor Tzunum (Mandamiento 9).
-- PROPÓSITO : Carga Protegida de Material Literario (MODULO_03_CRM_EVENTOS_
--             EN_VIVO). Añade la ruta del PDF de la sesión — la descarga
--             queda restringida a asistentes con check-in confirmado
--             (api/material_descargar.php).
-- =============================================================================

ALTER TABLE `historial_sesiones`
    ADD COLUMN `material_pdf_path` VARCHAR(255) NULL
        COMMENT 'Ruta relativa ya validada y renombrada — NULL si no se cargó material'
        AFTER `tema`;
