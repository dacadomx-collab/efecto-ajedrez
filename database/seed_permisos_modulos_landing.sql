-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/seed_permisos_modulos_landing.sql
-- ESTADO    : Autorizado por el Productor Tzunum.
-- PROPÓSITO : Registra el nuevo módulo "landing" (Editar Landing Page) en la
--             matriz de permisos dinámicos ya existente (permisos_modulos).
--             Habilitado por defecto para admin — es un módulo de contenido,
--             no de seguridad (mismo criterio que "usuarios").
-- =============================================================================

INSERT INTO `permisos_modulos` (`modulo`, `visible_para_rol`, `habilitado`)
VALUES ('landing', 'admin', 1)
ON DUPLICATE KEY UPDATE `modulo` = `modulo`;
