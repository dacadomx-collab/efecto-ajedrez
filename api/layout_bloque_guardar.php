<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const PAGINAS_VALIDAS = ['club-lectura'];
const LONGITUD_MAXIMA_BLOQUE = 2000;

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware
$actor = requireAuth($pdo, ['super_admin', 'admin']);

if ($actor['rol'] !== 'super_admin' && !esModuloVisible($pdo, 'landing', (string) $actor['rol'])) {
    jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
}

// CAPA 4 — Payload + Sanitización estricta
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$pagina = isset($payload['pagina']) ? trim((string) $payload['pagina']) : '';
$bloqueId = isset($payload['bloque_id']) ? trim((string) $payload['bloque_id']) : '';
$contenido = isset($payload['contenido']) ? (string) $payload['contenido'] : '';

if (!in_array($pagina, PAGINAS_VALIDAS, true)) {
    jsonResponse('error', 'Página inválida.', [], 422);
}

if ($bloqueId === '' || !preg_match('/^[a-z0-9_]{1,80}$/', $bloqueId)) {
    jsonResponse('error', 'Identificador de bloque inválido.', [], 422);
}

// Anti-XSS: se eliminan etiquetas HTML y se escapan entidades antes de
// persistir — el mismo valor sanitizado es el que se vuelve a mostrar
// (club-lectura.php ya escapa también al leer, defensa en profundidad).
$contenidoSanitizado = trim(htmlspecialchars(strip_tags($contenido), ENT_QUOTES, 'UTF-8'));

if ($contenidoSanitizado === '') {
    jsonResponse('error', 'El contenido no puede estar vacío.', [], 422);
}

if (mb_strlen($contenidoSanitizado) > LONGITUD_MAXIMA_BLOQUE) {
    jsonResponse('error', 'El texto supera el máximo de ' . LONGITUD_MAXIMA_BLOQUE . ' caracteres.', [], 422);
}

// CAPA 5 — Persistencia (PDO sin emulación, binding explícito)
try {
    $stmt = $pdo->prepare(
        'INSERT INTO configuracion_layout (pagina, bloque_id, tipo, contenido, actualizado_por)
         VALUES (:pagina, :bloque_id, :tipo, :contenido, :actor_id)
         ON DUPLICATE KEY UPDATE contenido = :contenido_update, actualizado_por = :actor_id_update, tipo = :tipo_update'
    );
    $stmt->execute([
        ':pagina' => $pagina,
        ':bloque_id' => $bloqueId,
        ':tipo' => 'texto',
        ':contenido' => $contenidoSanitizado,
        ':actor_id' => $actor['id'],
        ':contenido_update' => $contenidoSanitizado,
        ':actor_id_update' => $actor['id'],
        ':tipo_update' => 'texto',
    ]);

    registrarActividad($pdo, (int) $actor['id'], 'layout_bloque_guardado', "{$pagina}/{$bloqueId}");

    jsonResponse('success', 'Bloque guardado.', ['contenido' => $contenidoSanitizado]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] layout_bloque_guardar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos guardar el bloque. Intenta de nuevo.', [], 500);
}
