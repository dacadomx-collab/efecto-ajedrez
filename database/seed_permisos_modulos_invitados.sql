-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/seed_permisos_modulos_invitados.sql
-- ESTADO    : Autorizado por el Productor Tzunum.
-- PROPÓSITO : Registra el módulo "invitados" (panel Invitados Confirmados) en
--             la matriz de permisos dinámicos ya existente.
-- =============================================================================

INSERT INTO `permisos_modulos` (`modulo`, `visible_para_rol`, `habilitado`)
VALUES ('invitados', 'admin', 1)
ON DUPLICATE KEY UPDATE `modulo` = `modulo`;
