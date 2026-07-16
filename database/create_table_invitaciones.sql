-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_invitaciones.sql
-- ESTADO    : Pendiente de autorización explícita del Arquitecto (Mandamiento 9)
--             antes de ejecutarse contra la BD remota (tourfindycom_ajedrez_db).
-- PROPÓSITO : Método B (Invitación Segura por Plantilla) del Dashboard
--             administrativo. Requiere que `usuarios` exista primero
--             (Secuencia SQL→PHP). Consumida por api/usuarios_invitar.php
--             e api/invitacion_confirmar.php.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `invitaciones` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 del token — el token en claro solo viaja en el email, nunca se persiste.',
    `expira_en` DATETIME NOT NULL,
    `usado` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invitaciones_token_hash` (`token_hash`),
    KEY `idx_invitaciones_usuario` (`usuario_id`),
    CONSTRAINT `fk_invitaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
