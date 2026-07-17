<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/conexion.php';

const TTL_RESET_HORAS = 48;

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware: exclusivo super_admin (Controlador Central de Usuarios)
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

// CAPA 5 — Persistencia
try {
    $stmt = $pdo->prepare('SELECT id, nombre, email FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $usuario = $stmt->fetch();

    if ($usuario === false) {
        jsonResponse('error', 'El usuario no existe.', [], 404);
    }

    $pdo->beginTransaction();

    // Invalida la clave actual y cualquier sesión activa — el usuario debe
    // pasar de nuevo por el flujo de activación para volver a entrar.
    $stmt = $pdo->prepare(
        'UPDATE usuarios SET password_hash = NULL, estatus = :estatus, token_acceso = NULL, token_expira_en = NULL WHERE id = :id'
    );
    $stmt->execute([':estatus' => 'pendiente', ':id' => $usuarioId]);

    $tokenClaro = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenClaro);
    $expiraEn = (new DateTimeImmutable())->modify('+' . TTL_RESET_HORAS . ' hours')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO invitaciones (usuario_id, token_hash, expira_en) VALUES (:usuario_id, :token_hash, :expira_en)'
    );
    $stmt->execute([':usuario_id' => $usuarioId, ':token_hash' => $tokenHash, ':expira_en' => $expiraEn]);

    $pdo->commit();

    $enlaceReset = obtenerAppUrl() . '/invitacion.php?token=' . $tokenClaro;

    $correoEnviado = enviarCorreoTransaccional(
        $usuario['email'],
        'Redefine tu contraseña — El Efecto Ajedrez',
        construirPlantillaInvitacion($usuario['nombre'], $enlaceReset, $usuario['email'])
    );

    registrarActividad($pdo, (int) $actor['id'], 'usuario_password_reseteado', "usuario #{$usuarioId} ({$usuario['email']})");

    jsonResponse('success', 'Contraseña invalidada. Se envió un enlace de redefinición por correo.', ['correo_enviado' => $correoEnviado]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] usuarios_resetear_password.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos resetear la contraseña. Intenta de nuevo.', [], 500);
}
