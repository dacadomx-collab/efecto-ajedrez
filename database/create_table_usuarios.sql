-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_usuarios.sql
-- ESTADO    : Pendiente de autorización explícita del Arquitecto (Mandamiento 9)
--             antes de ejecutarse contra la BD remota (tourfindycom_ajedrez_db).
-- PROPÓSITO : Soporta el Dashboard administrativo (MODULO_01_LOGIN_Y_ACCESO):
--             First-Run Provisioning, Login, Alta directa e Invitación de
--             usuarios. Consumida por api/setup_genesis.php, api/login.php,
--             api/usuarios_crear.php e api/usuarios_invitar.php.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` CHAR(60) NULL COMMENT 'BCrypt cost=12. NULL mientras estatus=pendiente (invitación sin confirmar).',
    `rol` ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    `estatus` ENUM('activo', 'pendiente', 'suspendido') NOT NULL DEFAULT 'pendiente',
    `token_acceso` CHAR(64) NULL COMMENT 'Token opaco de sesión activa (hex 256 bits).',
    `token_expira_en` DATETIME NULL,
    `device_hash` CHAR(64) NULL COMMENT 'SHA-256(IP + User-Agent + APP_SECRET) del dispositivo vinculado.',
    `intentos_fallidos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `bloqueado_hasta` DATETIME NULL COMMENT 'Tarpitting — NULL si no hay bloqueo activo.',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_email` (`email`),
    KEY `idx_usuarios_token` (`token_acceso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
