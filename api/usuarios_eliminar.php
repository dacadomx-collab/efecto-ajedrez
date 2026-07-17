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

// CAPA 2 — Auth middleware: exclusivo super_admin
$actor = requireAuth($pdo, ['super_admin']);

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
$usuarioId = isset($payload['usuario_id']) ? (int) $payload['usuario_id'] : 0;

if ($usuarioId <= 0) {
    jsonResponse('error', 'Usuario inválido.', [], 422);
}

if ($usuarioId === (int) $actor['id']) {
    jsonResponse('error', 'No puedes eliminar tu propia cuenta.', [], 422);
}

// CAPA 5 — Persistencia
try {
    $stmt = $pdo->prepare('SELECT id, rol, email FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $usuario = $stmt->fetch();

    if ($usuario === false) {
        jsonResponse('error', 'El usuario no existe.', [], 404);
    }

    // Nunca dejar el sistema sin al menos un super_admin — evita un
    // bloqueo total irreversible del propio Dashboard.
    if ($usuario['rol'] === 'super_admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'super_admin' AND id != :id");
        $stmt->execute([':id' => $usuarioId]);

        if ((int) $stmt->fetchColumn() === 0) {
            jsonResponse('error', 'No puedes eliminar al único super_admin del sistema.', [], 422);
        }
    }

    // ON DELETE CASCADE ya definido en invitaciones/recuperacion_password
    // (Secuencia SQL→PHP) — se limpian automáticamente al eliminar la fila.
    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $usuarioId]);

    // El log de actividad NO tiene FK hacia usuarios (deliberado — es una
    // bitácora inmutable append-only, Sección 1.2) — conserva el historial
    // del usuario eliminado con su id, sin romper ni requerir cascada.
    registrarActividad($pdo, (int) $actor['id'], 'usuario_eliminado', "usuario #{$usuarioId} ({$usuario['email']})");

    jsonResponse('success', 'Usuario eliminado del sistema.');
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] usuarios_eliminar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos eliminar al usuario. Intenta de nuevo.', [], 500);
}
