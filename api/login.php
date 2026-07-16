<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';

const MAX_INTENTOS_FALLIDOS = 5;
const MINUTOS_BLOQUEO = 15;
const TTL_TOKEN_SEGUNDOS = 8 * 60 * 60; // 8 horas

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

$email = isset($payload['email']) ? trim(strip_tags((string) $payload['email'])) : '';
$password = isset($payload['password']) ? (string) $payload['password'] : '';

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
    jsonResponse('error', 'Credenciales inválidas.', [], 422);
}

// CAPA 5 — Persistencia (PDO sin emulación, binding explícito)
require_once __DIR__ . '/conexion.php';

// Hash "dummy" precalculado (BCrypt cost=12) — se usa cuando el email no
// existe para que el tiempo de respuesta no delate si la cuenta es real.
const HASH_DUMMY = '$2y$12$Cw1p8s0bYFqf1u1p6b7bJOQe4o8x0m1n2q3r4s5t6u7v8w9x0y1z2';

try {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, password_hash, rol, estatus, intentos_fallidos, bloqueado_hasta
         FROM usuarios WHERE email = :email LIMIT 1'
    );
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $usuario = $stmt->fetch();

    $hashParaVerificar = $usuario['password_hash'] ?? HASH_DUMMY;
    $passwordValida = password_verify($password, (string) $hashParaVerificar);

    if ($usuario === false || !$passwordValida) {
        if ($usuario !== false) {
            $intentos = (int) $usuario['intentos_fallidos'] + 1;
            $bloqueadoHasta = $intentos >= MAX_INTENTOS_FALLIDOS
                ? (new DateTimeImmutable())->modify('+' . MINUTOS_BLOQUEO . ' minutes')->format('Y-m-d H:i:s')
                : null;

            $stmt = $pdo->prepare(
                'UPDATE usuarios SET intentos_fallidos = :intentos, bloqueado_hasta = :bloqueado_hasta WHERE id = :id'
            );
            $stmt->execute([
                ':intentos' => $intentos,
                ':bloqueado_hasta' => $bloqueadoHasta,
                ':id' => $usuario['id'],
            ]);

            registrarActividad($pdo, (int) $usuario['id'], 'login_fallido', 'Contraseña incorrecta.');
        } else {
            registrarActividad($pdo, null, 'login_fallido', 'Email no encontrado.');
        }

        jsonResponse('error', 'Credenciales inválidas.', [], 401);
    }

    if ($usuario['estatus'] === 'suspendido') {
        registrarActividad($pdo, (int) $usuario['id'], 'acceso_bloqueado', 'Cuenta suspendida.');
        jsonResponse('error', 'Cuenta suspendida. Contacta a un administrador.', [], 403);
    }

    if ($usuario['estatus'] === 'pendiente') {
        jsonResponse('error', 'Tu cuenta aún no ha sido activada. Revisa tu invitación por correo.', [], 403);
    }

    if ($usuario['bloqueado_hasta'] !== null && strtotime((string) $usuario['bloqueado_hasta']) > time()) {
        jsonResponse('error', 'Demasiados intentos fallidos. Intenta de nuevo más tarde.', [], 429);
    }

    // Éxito — token opaco de 256 bits + device binding
    $token = bin2hex(random_bytes(32));
    $tokenExpiraEn = (new DateTimeImmutable())->modify('+' . TTL_TOKEN_SEGUNDOS . ' seconds')->format('Y-m-d H:i:s');
    $deviceHash = calcularDeviceHash();

    $stmt = $pdo->prepare(
        'UPDATE usuarios
         SET token_acceso = :token, token_expira_en = :expira, device_hash = :device_hash,
             intentos_fallidos = 0, bloqueado_hasta = NULL
         WHERE id = :id'
    );
    $stmt->execute([
        ':token' => $token,
        ':expira' => $tokenExpiraEn,
        ':device_hash' => $deviceHash,
        ':id' => $usuario['id'],
    ]);

    session_regenerate_id(true);
    setCookieToken($token, TTL_TOKEN_SEGUNDOS);

    registrarActividad($pdo, (int) $usuario['id'], 'login_exitoso');

    jsonResponse('success', 'Bienvenido(a) de nuevo.', [
        'usuario' => [
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rol' => $usuario['rol'],
        ],
    ]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global: SQLSTATE + mensaje solo al log interno
    error_log('[' . date('Y-m-d H:i:s') . '] login.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos procesar tu acceso. Intenta más tarde.', [], 500);
}
