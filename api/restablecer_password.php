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

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$token = isset($payload['token']) ? trim((string) $payload['token']) : '';
$password = isset($payload['password']) ? (string) $payload['password'] : '';
$passwordConfirmacion = isset($payload['password_confirmacion']) ? (string) $payload['password_confirmacion'] : '';

const MENSAJE_TOKEN_INVALIDO = 'Este enlace ya no es válido.';

if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    jsonResponse('error', MENSAJE_TOKEN_INVALIDO, [], 410);
}

if ($password === '' || $password !== $passwordConfirmacion) {
    jsonResponse('error', 'Las contraseñas no coinciden.', [], 422);
}

// CAPA 5 — Persistencia
try {
    $pdo = (new Database())->getConnection();

    $definicionPolitica = politicaSeguridadDefinicion(obtenerPoliticaActiva($pdo));
    if (!passwordCumplePolitica($password, $definicionPolitica)) {
        jsonResponse('error', mensajePoliticaPassword($definicionPolitica), [], 422);
    }

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT id, usuario_id, expira_en, usado FROM recuperacion_password WHERE token_hash = :token_hash LIMIT 1'
    );
    $stmt->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
    $stmt->execute();
    $recuperacion = $stmt->fetch();

    $tokenValido = $recuperacion !== false
        && (int) $recuperacion['usado'] === 0
        && strtotime((string) $recuperacion['expira_en']) > time();

    if (!$tokenValido) {
        jsonResponse('error', MENSAJE_TOKEN_INVALIDO, [], 410);
    }

    $pdo->beginTransaction();

    // Reset de contraseña revoca cualquier sesión activa previa — una sesión
    // potencialmente comprometida deja de ser válida de inmediato.
    $stmt = $pdo->prepare(
        'UPDATE usuarios SET password_hash = :hash, token_acceso = NULL, token_expira_en = NULL WHERE id = :id'
    );
    $stmt->bindValue(':hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
    $stmt->bindValue(':id', $recuperacion['usuario_id'], PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare('UPDATE recuperacion_password SET usado = 1 WHERE id = :id');
    $stmt->bindValue(':id', $recuperacion['id'], PDO::PARAM_INT);
    $stmt->execute();

    $pdo->commit();

    registrarActividad($pdo, (int) $recuperacion['usuario_id'], 'recuperacion_confirmada');

    jsonResponse('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] restablecer_password.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos restablecer tu contraseña. Intenta de nuevo.', [], 500);
}
