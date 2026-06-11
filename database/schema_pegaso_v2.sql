-- =============================================================================
-- PEGASO EXPEDICIONES — SCHEMA v2 (Motor Dinámico)
-- Arquitecto: Gemini | Ejecutor: Claude (Lead Backend PHP/PDO)
-- Motor: MySQL 8.0+ | Charset: utf8mb4_unicode_ci
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- TABLE: expeditions
-- Atributos fijos + columna JSON para campos dinámicos por tipo de expedición.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expeditions` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(120)    NOT NULL,
    `slug`              VARCHAR(140)    NOT NULL,
    `description`       TEXT            NULL,
    `difficulty_level`  ENUM('easy','moderate','hard','extreme') NOT NULL DEFAULT 'moderate',
    `duration_days`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `base_price`        DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    `max_capacity`      SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `status`            ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    -- Motor Dinámico: atributos variables según tipo de expedición
    -- Ej: {"altitude_m": 4800, "requires_permit": true, "gear_list": ["crampons","ice_axe"]}
    `custom_fields`     JSON            NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_expeditions_slug` (`slug`),
    KEY `idx_expeditions_status` (`status`),
    KEY `idx_expeditions_difficulty` (`difficulty_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de expediciones con soporte de campos dinámicos via JSON';

-- -----------------------------------------------------------------------------
-- TABLE: expedition_dates
-- Una expedición puede tener múltiples fechas de salida con cupos independientes.
-- Esta tabla es el "inventario" de cupos que bookings consume y libera.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expedition_dates` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `expedition_id`     INT UNSIGNED    NOT NULL,
    `departure_date`    DATE            NOT NULL,
    `return_date`       DATE            NOT NULL,
    `total_spots`       SMALLINT UNSIGNED NOT NULL,
    `available_spots`   SMALLINT UNSIGNED NOT NULL,
    `status`            ENUM('open','full','cancelled') NOT NULL DEFAULT 'open',
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_exp_dates_expedition` (`expedition_id`),
    KEY `idx_exp_dates_departure` (`departure_date`),
    CONSTRAINT `fk_exp_dates_expedition`
        FOREIGN KEY (`expedition_id`) REFERENCES `expeditions` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_spots_consistency`
        CHECK (`available_spots` <= `total_spots`),
    CONSTRAINT `chk_date_range`
        CHECK (`return_date` >= `departure_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fechas de salida con inventario de cupos por expedición';

-- -----------------------------------------------------------------------------
-- TABLE: bookings
-- Ciclo de vida: intent → confirmed → (failed | cancelled)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `expedition_id`         INT UNSIGNED    NOT NULL,
    `expedition_date_id`    INT UNSIGNED    NOT NULL,
    -- Datos del cliente (desnormalizados por snapshot en el momento de la reserva)
    `customer_name`         VARCHAR(120)    NOT NULL,
    `customer_email`        VARCHAR(180)    NOT NULL,
    `customer_phone`        VARCHAR(30)     NULL,
    `spots_reserved`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price`            DECIMAL(10, 2)  NOT NULL,
    `total_amount`          DECIMAL(10, 2)  NOT NULL,
    -- Ciclo de vida de la reserva
    `status`                ENUM('intent','confirmed','failed','cancelled') NOT NULL DEFAULT 'intent',
    `cancelled_at`          TIMESTAMP       NULL DEFAULT NULL,
    `cancellation_reason`   VARCHAR(255)    NULL DEFAULT NULL,
    `confirmed_at`          TIMESTAMP       NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_bookings_status` (`status`),
    KEY `idx_bookings_email` (`customer_email`),
    KEY `idx_bookings_date` (`expedition_date_id`),
    KEY `idx_bookings_expedition` (`expedition_id`),
    CONSTRAINT `fk_bookings_expedition`
        FOREIGN KEY (`expedition_id`) REFERENCES `expeditions` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bookings_date`
        FOREIGN KEY (`expedition_date_id`) REFERENCES `expedition_dates` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_spots_positive`
        CHECK (`spots_reserved` >= 1),
    CONSTRAINT `chk_total_amount`
        CHECK (`total_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reservas con ciclo de vida: intent → confirmed → failed/cancelled';

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- DATOS SEMILLA (SEEDER) — Solo para entorno LOCAL/STAGING
-- =============================================================================
-- INSERT INTO `expeditions` (`name`, `slug`, `difficulty_level`, `duration_days`,
--     `base_price`, `max_capacity`, `status`, `custom_fields`) VALUES
-- ('Trekking Patagonia Sur', 'trekking-patagonia-sur', 'hard', 7, 1250.00, 12, 'active',
--  '{"altitude_m": 2000, "requires_permit": true, "gear_list": ["trekking_poles","waterproof_jacket"]}'),
-- ('Kayak Fiordos del Norte', 'kayak-fiordos-norte', 'moderate', 3, 680.00, 8, 'active',
--  '{"water_temp_c": 12, "requires_wetsuit": true, "difficulty_notes": "Corrientes moderadas"}');
