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

// Mensaje único para token vacío/malformado — no distingue el motivo
// (mismo principio anti-enumeración que login.php).
const MENSAJE_INVITACION_INVALIDA = 'Este enlace ya no es válido.';

if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    jsonResponse('error', MENSAJE_INVITACION_INVALIDA, [], 410);
}

if ($password === '' || $password !== $passwordConfirmacion) {
    jsonResponse('error', 'Las contraseñas no coinciden.', [], 422);
}

if (!passwordCumplePolitica($password)) {
    jsonResponse('error', 'La contraseña debe tener mínimo 14 caracteres, con mayúscula, minúscula, número y símbolo.', [], 422);
}

// CAPA 5 — Persistencia
try {
    $pdo = (new Database())->getConnection();

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT id, usuario_id, expira_en, usado FROM invitaciones WHERE token_hash = :token_hash LIMIT 1'
    );
    $stmt->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
    $stmt->execute();
    $invitacion = $stmt->fetch();

    $tokenValido = $invitacion !== false
        && (int) $invitacion['usado'] === 0
        && strtotime((string) $invitacion['expira_en']) > time();

    if (!$tokenValido) {
        jsonResponse('error', MENSAJE_INVITACION_INVALIDA, [], 410);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash, estatus = :estatus WHERE id = :id');
    $stmt->bindValue(':hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
    $stmt->bindValue(':estatus', 'activo', PDO::PARAM_STR);
    $stmt->bindValue(':id', $invitacion['usuario_id'], PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare('UPDATE invitaciones SET usado = 1 WHERE id = :id');
    $stmt->bindValue(':id', $invitacion['id'], PDO::PARAM_INT);
    $stmt->execute();

    $pdo->commit();

    registrarActividad($pdo, (int) $invitacion['usuario_id'], 'invitacion_confirmada');

    jsonResponse('success', 'Cuenta activada. Ya puedes iniciar sesión.');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] invitacion_confirmar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos confirmar la invitación. Intenta de nuevo.', [], 500);
}
