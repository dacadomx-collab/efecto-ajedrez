<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const MODULOS_VALIDOS = ['usuarios', 'seguridad', 'landing', 'invitados'];
const ROLES_CONFIGURABLES = ['admin'];

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware (cualquier usuario autenticado puede leer su
// propia matriz efectiva; solo super_admin puede mutarla)
$actor = requireAuth($pdo);

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $matriz = [];
        foreach (MODULOS_VALIDOS as $modulo) {
            $matriz[$modulo] = esModuloVisible($pdo, $modulo, (string) $actor['rol']);
        }

        jsonResponse('success', 'Matriz de permisos.', ['modulos' => $matriz]);
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] permisos_modulos.php GET: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
        jsonResponse('error', 'No pudimos leer los permisos.', [], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

if ($actor['rol'] !== 'super_admin') {
    jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
}

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$modulo = isset($payload['modulo']) ? trim((string) $payload['modulo']) : '';
$rolObjetivo = isset($payload['rol']) ? trim((string) $payload['rol']) : '';
$habilitado = isset($payload['habilitado']) && $payload['habilitado'] === true;

if (!in_array($modulo, MODULOS_VALIDOS, true)) {
    jsonResponse('error', 'Módulo inválido.', [], 422);
}

if (!in_array($rolObjetivo, ROLES_CONFIGURABLES, true)) {
    jsonResponse('error', 'Rol inválido — super_admin no es configurable.', [], 422);
}

// CAPA 5 — Persistencia
try {
    // PDO::ATTR_EMULATE_PREPARES=false usa preparados nativos de MySQL, que
    // no permiten reutilizar el mismo placeholder con nombre dos veces en
    // una sola consulta — de ahí :habilitado y :habilitado_update por separado.
    $stmt = $pdo->prepare(
        'INSERT INTO permisos_modulos (modulo, visible_para_rol, habilitado) VALUES (:modulo, :rol, :habilitado)
         ON DUPLICATE KEY UPDATE habilitado = :habilitado_update'
    );
    $stmt->execute([
        ':modulo' => $modulo,
        ':rol' => $rolObjetivo,
        ':habilitado' => $habilitado ? 1 : 0,
        ':habilitado_update' => $habilitado ? 1 : 0,
    ]);

    registrarActividad($pdo, (int) $actor['id'], 'permisos_modulo_actualizado', "{$modulo} para {$rolObjetivo} = " . ($habilitado ? '1' : '0'));

    jsonResponse('success', 'Permiso actualizado.');
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] permisos_modulos.php POST: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos actualizar el permiso.', [], 500);
}
