-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/alter_table_registro_interesados_ciudad_estado_nullable.sql
-- ESTADO    : Autorizado por el Productor Tzunum (Mandamiento 9).
-- PROPÓSITO : El formulario público ya NO solicita Ciudad/Estado manualmente
--             (recorte de campos, punto 5 del mandato) — ahora `ciudad` y
--             `estado` se pueblan desde la resolución de IP (mismo dato que
--             ip_ciudad/ip_estado). Deben ser NULL-ables porque la
--             resolución de IP puede fallar (ej. localhost/desarrollo) y ya
--             no hay respaldo de un valor declarado por el usuario.
-- =============================================================================

ALTER TABLE `registro_interesados`
    MODIFY COLUMN `ciudad` VARCHAR(120) NULL,
    MODIFY COLUMN `estado` VARCHAR(120) NULL;
