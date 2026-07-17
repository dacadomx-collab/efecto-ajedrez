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
try {
    $eliminados = 0;

    if ($modo === 'todos') {
        $stmt = $pdo->prepare("DELETE FROM log_actividad WHERE evento = 'login_exitoso'");
        $stmt->execute();
        $eliminados = $stmt->rowCount();
    } elseif ($modo === 'cantidad') {
        $cantidad = isset($payload['cantidad']) ? (int) $payload['cantidad'] : 0;

        if ($cantidad <= 0 || $cantidad > 1000) {
            jsonResponse('error', 'Cantidad inválida.', [], 422);
        }

        // Elimina los registros más antiguos primero — conserva la
        // auditoría más reciente, que es la de mayor valor operativo.
        $stmt = $pdo->prepare(
            "DELETE FROM log_actividad WHERE evento = 'login_exitoso'
             ORDER BY created_at ASC LIMIT :cantidad"
        );
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
        $stmt = $pdo->prepare("DELETE FROM log_actividad WHERE evento = 'login_exitoso' AND id IN ({$marcadores})");
        $stmt->execute(array_values($ids));
        $eliminados = $stmt->rowCount();
    }

    registrarActividad($pdo, (int) $actor['id'], 'registro_ingreso_purgado', "modo={$modo}, eliminados={$eliminados}");

    jsonResponse('success', $eliminados . ' registro(s) eliminado(s) del historial de accesos.', ['eliminados' => $eliminados]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] registro_ingreso_eliminar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos eliminar los registros. Intenta de nuevo.', [], 500);
}
