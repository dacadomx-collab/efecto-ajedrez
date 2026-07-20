<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware: exclusivo super_admin — borrado de bitácora es
// una acción destructiva e irreversible.
$actor = requireAuth($pdo, ['super_admin']);

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
$modo = isset($payload['modo']) ? (string) $payload['modo'] : '';

if (!in_array($modo, ['seleccionados', 'cantidad', 'todos'], true)) {
    jsonResponse('error', 'Modo de borrado inválido.', [], 422);
}

// CAPA 5 — Persistencia
//
// "Borrar" aquí nunca hace DELETE real sobre log_actividad: esa tabla es una
// bitácora append-only con un trigger a nivel de BD
// (trg_log_actividad_no_delete) que rechaza cualquier DELETE, a propósito
// (ni un super_admin puede borrar el rastro de accesos). En vez de eso, se
// inserta el id en log_actividad_ocultos — la fila original permanece
// intacta para auditoría real, y registro_ingreso_listar.php la excluye de
// la vista. INSERT IGNORE evita error de clave duplicada si un registro ya
// estaba oculto.
try {
    $eliminados = 0;
    $actorId = (int) $actor['id'];

    if ($modo === 'todos') {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO log_actividad_ocultos (log_actividad_id, ocultado_por)
             SELECT la.id, :actor FROM log_actividad la
             LEFT JOIN log_actividad_ocultos o ON o.log_actividad_id = la.id
             WHERE la.evento = 'login_exitoso' AND o.log_actividad_id IS NULL"
        );
        $stmt->bindValue(':actor', $actorId, PDO::PARAM_INT);
        $stmt->execute();
        $eliminados = $stmt->rowCount();
    } elseif ($modo === 'cantidad') {
        $cantidad = isset($payload['cantidad']) ? (int) $payload['cantidad'] : 0;

        if ($cantidad <= 0 || $cantidad > 1000) {
            jsonResponse('error', 'Cantidad inválida.', [], 422);
        }

        // Oculta los registros visibles más antiguos primero — conserva la
        // auditoría más reciente, que es la de mayor valor operativo.
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO log_actividad_ocultos (log_actividad_id, ocultado_por)
             SELECT la.id, :actor FROM log_actividad la
             LEFT JOIN log_actividad_ocultos o ON o.log_actividad_id = la.id
             WHERE la.evento = 'login_exitoso' AND o.log_actividad_id IS NULL
             ORDER BY la.created_at ASC LIMIT :cantidad"
        );
        $stmt->bindValue(':actor', $actorId, PDO::PARAM_INT);
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->execute();
        $eliminados = $stmt->rowCount();
    } else {
        $ids = isset($payload['ids']) && is_array($payload['ids']) ? array_values(array_unique(array_map('intval', $payload['ids']))) : [];
        $ids = array_filter($ids, fn (int $id): bool => $id > 0);

        if (empty($ids)) {
            jsonResponse('error', 'No seleccionaste ningún registro.', [], 422);
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO log_actividad_ocultos (log_actividad_id, ocultado_por)
             SELECT la.id, ? FROM log_actividad la
             LEFT JOIN log_actividad_ocultos o ON o.log_actividad_id = la.id
             WHERE la.evento = 'login_exitoso' AND o.log_actividad_id IS NULL AND la.id IN ({$marcadores})"
        );
        $stmt->execute(array_merge([$actorId], array_values($ids)));
        $eliminados = $stmt->rowCount();
    }

    registrarActividad($pdo, $actorId, 'registro_ingreso_ocultado', "modo={$modo}, ocultados={$eliminados}");

    jsonResponse('success', $eliminados . ' registro(s) eliminado(s) del historial de accesos.', ['eliminados' => $eliminados]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] registro_ingreso_eliminar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos eliminar los registros. Intenta de nuevo.', [], 500);
}
