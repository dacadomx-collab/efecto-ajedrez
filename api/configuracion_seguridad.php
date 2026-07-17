<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

$pdo = (new Database())->getConnection();

// GET — lectura pública (sin requireAuth): las páginas públicas de creación
// de contraseña (setup-genesis.php, invitacion.php, restablecer-password.php)
// y el checkbox "Mantenerme registrado" del login necesitan calibrar su UI
// antes de que exista cualquier sesión. Solo expone umbrales, nunca datos de
// usuarios (MODULO_01_LOGIN_Y_ACCESO §7.4).
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $config = obtenerConfiguracionSeguridad($pdo);

        jsonResponse('success', 'Política de seguridad activa.', [
            'perfil' => $config['politica_password'],
            'definicion' => politicaSeguridadDefinicion($config['politica_password']),
            'duracion_recordarme_dias' => $config['duracion_recordarme_dias'],
        ]);
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] configuracion_seguridad.php GET: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
        jsonResponse('error', 'No pudimos leer la configuración de seguridad.', [], 500);
    }
}

// CAPA 3 — Método HTTP (la mutación es exclusiva de super_admin)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

// CAPA 2 — Auth middleware. La matriz de permisos (permisos_modulos) es
// exclusiva de super_admin; el módulo "seguridad" en sí puede habilitarse
// para admin desde esa matriz (MODULO_01_LOGIN_Y_ACCESO §6.1).
$actor = requireAuth($pdo, ['super_admin', 'admin']);

if ($actor['rol'] !== 'super_admin' && !esModuloVisible($pdo, 'seguridad', (string) $actor['rol'])) {
    jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
}

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
$perfilNuevo = isset($payload['perfil']) ? trim((string) $payload['perfil']) : '';
$duracionNueva = isset($payload['duracion_recordarme_dias']) ? (int) $payload['duracion_recordarme_dias'] : 0;

if (!in_array($perfilNuevo, ['simple', 'media', 'fuerte'], true)) {
    jsonResponse('error', 'Perfil de seguridad inválido.', [], 422);
}

if (!in_array($duracionNueva, [60, 120], true)) {
    jsonResponse('error', 'La duración de "Mantenerme registrado" debe ser 60 o 120 días.', [], 422);
}

// CAPA 5 — Persistencia
try {
    // PDO::ATTR_EMULATE_PREPARES=false usa preparados nativos de MySQL, que
    // no permiten reutilizar el mismo placeholder con nombre dos veces en
    // una sola consulta — de ahí los sufijos _update por separado.
    $stmt = $pdo->prepare(
        'INSERT INTO configuracion_seguridad (id, politica_password, duracion_recordarme_dias)
         VALUES (1, :perfil, :duracion)
         ON DUPLICATE KEY UPDATE politica_password = :perfil_update, duracion_recordarme_dias = :duracion_update'
    );
    $stmt->execute([
        ':perfil' => $perfilNuevo,
        ':duracion' => $duracionNueva,
        ':perfil_update' => $perfilNuevo,
        ':duracion_update' => $duracionNueva,
    ]);

    jsonResponse('success', 'Política de seguridad actualizada: perfil "' . $perfilNuevo . '", recordarme ' . $duracionNueva . ' días.');
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] configuracion_seguridad.php POST: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos actualizar la política de seguridad.', [], 500);
}
