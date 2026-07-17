-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_frase_bienvenida_diaria.sql
-- ESTADO    : Autorizado por el Productor Tzunum (extensión directa del alcance
--             ya autorizado del sistema de login/dashboard — Mandamiento 9).
-- PROPÓSITO : Persistencia diaria de la cápsula motivacional del Núcleo
--             Cognitivo de Bienvenida (MODULO_01_LOGIN_Y_ACCESO §10.4).
--             Este proyecto NO tiene contratado ningún proveedor de IA
--             ("AURA") — no hay endpoint ni API key en .env para eso. La
--             columna `origen` queda sembrada como 'banco_estatico' porque
--             la frase se selecciona de un banco curado en PHP, nunca de una
--             llamada a un servicio que no existe (Mandamiento 4).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `frase_bienvenida_diaria` (
    `fecha` DATE NOT NULL,
    `frase` VARCHAR(280) NOT NULL,
    `origen` ENUM('ia', 'banco_estatico') NOT NULL DEFAULT 'banco_estatico',
    PRIMARY KEY (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
