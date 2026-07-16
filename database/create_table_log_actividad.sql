-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_log_actividad.sql
-- ESTADO    : Pendiente de autorización explícita del Arquitecto (Mandamiento 9)
--             antes de ejecutarse contra la BD remota (tourfindycom_ajedrez_db).
-- PROPÓSITO : Bitácora append-only de autenticación (login/logout/bloqueos).
--             Requiere que `usuarios` exista primero (Secuencia SQL→PHP).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `log_actividad` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NULL COMMENT 'NULL si el intento falló antes de resolver el usuario (anti-enumeración).',
    `evento` VARCHAR(60) NOT NULL COMMENT 'login_exitoso | login_fallido | logout | device_mismatch | bloqueo_temporal',
    `ip_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 de la IP — nunca IP en claro.',
    `device_hash` CHAR(64) NOT NULL,
    `detalle` VARCHAR(255) NULL COMMENT 'Mensaje técnico genérico. JAMÁS credenciales ni tokens.',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_log_actividad_usuario` (`usuario_id`),
    KEY `idx_log_actividad_evento_fecha` (`evento`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTA: sin `DELIMITER` — es una convención exclusiva del cliente CLI `mysql`
-- y no es SQL válido para el protocolo cliente-servidor que usa PDO
-- (api/conexion.php / database/run_migration.php). El propio parser del
-- servidor delimita correctamente el cuerpo BEGIN...END de cada CREATE
-- TRIGGER sin necesidad de cambiar el delimitador.

CREATE TRIGGER `trg_log_actividad_no_update`
BEFORE UPDATE ON `log_actividad`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Bitácora append-only: UPDATE prohibido sobre log_actividad.';
END;

CREATE TRIGGER `trg_log_actividad_no_delete`
BEFORE DELETE ON `log_actividad`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Bitácora append-only: DELETE prohibido sobre log_actividad.';
END;
