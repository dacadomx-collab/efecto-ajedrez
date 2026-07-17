-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_configuracion_layout.sql
-- ESTADO    : Autorizado por el Productor Tzunum (extensión directa del alcance
--             ya autorizado del ecosistema Dashboard — Mandamiento 9).
-- PROPÓSITO : Motor de Edición Visual en Caliente (MODULO_02_CMS_EDICION_VISUAL).
--             Almacena únicamente overrides por bloque — sin fila, la página
--             pública sigue mostrando su contenido original hardcodeado.
--             Consumida por club-lectura.php, api/layout_bloque_guardar.php
--             e api/layout_imagen_subir.php.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `configuracion_layout` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pagina` VARCHAR(60) NOT NULL COMMENT 'Identificador estable de la página pública, ej. "club-lectura"',
    `bloque_id` VARCHAR(80) NOT NULL COMMENT 'Identificador estable del bloque (data-block-id)',
    `tipo` ENUM('texto', 'imagen') NOT NULL,
    `contenido` TEXT NULL COMMENT 'Texto editado — NULL si tipo=imagen',
    `imagen_path` VARCHAR(255) NULL COMMENT 'Ruta relativa ya validada y renombrada — NULL si tipo=texto',
    `actualizado_por` INT UNSIGNED NULL COMMENT 'usuarios.id de quien hizo el último cambio',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_configuracion_layout_pagina_bloque` (`pagina`, `bloque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
