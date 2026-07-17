-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/alter_table_log_actividad_add_geo.sql
-- ESTADO    : Autorizado por el Productor Tzunum (Mandamiento 9).
-- PROPÓSITO : Panel "Registro de Ingreso" (auditoría de accesos, exclusivo
--             super_admin). Añade IP en claro + geolocalización — nullable,
--             y se puebla SOLO en login_exitoso (nunca en intentos fallidos,
--             para no exponer el endpoint de login a la latencia/límite de
--             tasa de un proveedor externo en cada intento, incluidos los de
--             fuerza bruta). El resto de eventos siguen usando únicamente
--             ip_hash (ya existente) para correlación sin exponer PII.
-- =============================================================================

ALTER TABLE `log_actividad`
    ADD COLUMN `ip` VARCHAR(45) NULL COMMENT 'Solo poblado en login_exitoso — visible en el panel Registro de Ingreso (super_admin)' AFTER `device_hash`,
    ADD COLUMN `ip_pais` VARCHAR(80) NULL AFTER `ip`,
    ADD COLUMN `ip_estado` VARCHAR(80) NULL AFTER `ip_pais`,
    ADD COLUMN `ip_ciudad` VARCHAR(80) NULL AFTER `ip_estado`;
