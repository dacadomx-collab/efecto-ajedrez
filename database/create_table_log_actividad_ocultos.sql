-- =============================================================================
-- PROYECTO  : El Efecto Ajedrez — Mentores al Revés
-- ARCHIVO   : database/create_table_log_actividad_ocultos.sql
-- ESTADO    : Pendiente de autorización explícita del Arquitecto (Mandamiento 9)
--             antes de ejecutarse contra la BD remota (tourfindycom_ajedrez_db).
-- PROPÓSITO : Resuelve el conflicto entre el panel "Registro de Ingreso"
--             (botones "Borrar 10/15/todos/seleccionados") y el trigger
--             append-only de `log_actividad` (trg_log_actividad_no_delete),
--             que bloquea CUALQUIER DELETE sobre esa bitácora a propósito.
--             En vez de borrar, "Borrar" en el Dashboard pasa a significar
--             "ocultar de la vista del panel" — la fila original de
--             log_actividad permanece intacta e inmutable para auditoría
--             real; solo se registra aquí que un super_admin decidió
--             sacarla de la lista visible. Requiere que `log_actividad` y
--             `usuarios` existan primero (Secuencia SQL→PHP). Consumida por
--             api/registro_ingreso_listar.php e api/registro_ingreso_eliminar.php.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `log_actividad_ocultos` (
    `log_actividad_id` INT UNSIGNED NOT NULL COMMENT 'FK a log_actividad.id — la fila oculta, nunca borrada.',
    `ocultado_por` INT UNSIGNED NULL COMMENT 'usuarios.id del super_admin que ocultó el registro (NULL si esa cuenta se elimina después).',
    `ocultado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_actividad_id`),
    CONSTRAINT `fk_log_actividad_ocultos_log` FOREIGN KEY (`log_actividad_id`) REFERENCES `log_actividad` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_log_actividad_ocultos_usuario` FOREIGN KEY (`ocultado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
