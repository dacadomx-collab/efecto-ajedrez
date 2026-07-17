-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_recuperacion_password.sql
-- ESTADO    : Autorizado por el Productor Tzunum (extensión directa del alcance
--             ya autorizado del sistema de login/dashboard — Mandamiento 9).
-- PROPÓSITO : Flujo "Olvidé mi contraseña" (MODULO_01_LOGIN_Y_ACCESO §3.6).
--             Requiere que `usuarios` exista primero (Secuencia SQL→PHP).
--             Consumida por api/recuperar_password.php e
--             api/restablecer_password.php.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `recuperacion_password` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 del token — el token en claro solo viaja en el email.',
    `expira_en` DATETIME NOT NULL COMMENT 'TTL corto: 1 hora.',
    `usado` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_recuperacion_password_token_hash` (`token_hash`),
    KEY `idx_recuperacion_password_usuario` (`usuario_id`),
    CONSTRAINT `fk_recuperacion_password_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
