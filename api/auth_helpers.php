<?php

declare(strict_types=1);

// Helpers compartidos por el ecosistema de autenticación del Dashboard
// (setup_genesis, login, logout, usuarios_crear, usuarios_invitar,
// invitacion_confirmar). Se centraliza aquí para evitar declarar la misma
// función en 6 endpoints distintos que se necesitan entre sí.

function jsonResponse(string $status, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerEnv(): array
{
    static $env = null;

    if ($env === null) {
        $env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW) ?: [];
    }

    return $env;
}

function calcularIpHash(): string
{
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
}

function calcularDeviceHash(): string
{
    $env = obtenerEnv();
    // JWT_SECRET actúa como pimienta de aplicación (ya documentado en
    // .env.example como string largo y aleatorio); si aún no se ha añadido a
    // la .env real, se usa DB_PASS como respaldo — nunca cadena vacía.
    $pepper = $env['JWT_SECRET'] ?? $env['DB_PASS'] ?? '';

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    return hash('sha256', $ip . '|' . $userAgent . '|' . $pepper);
}

function registrarActividad(PDO $pdo, ?int $usuarioId, string $evento, string $detalle = ''): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO log_actividad (usuario_id, evento, ip_hash, device_hash, detalle)
             VALUES (:usuario_id, :evento, :ip_hash, :device_hash, :detalle)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':evento' => $evento,
            ':ip_hash' => calcularIpHash(),
            ':device_hash' => calcularDeviceHash(),
            ':detalle' => $detalle,
        ]);
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] log_actividad: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    }
}

/**
 * Capa 2 — Middleware de autenticación y verificación de rol/estatus.
 * Termina la ejecución (401/403) si la sesión no es válida; retorna el
 * arreglo del usuario autenticado en caso de éxito.
 */
function requireAuth(PDO $pdo, array $rolesPermitidos = []): array
{
    $token = $_COOKIE['token_acceso'] ?? '';

    if ($token === '') {
        jsonResponse('error', 'No autenticado.', [], 401);
    }

    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, rol, estatus, token_expira_en, device_hash
         FROM usuarios WHERE token_acceso = :token LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $usuario = $stmt->fetch();

    if ($usuario === false || $usuario['token_expira_en'] === null || strtotime((string) $usuario['token_expira_en']) < time()) {
        jsonResponse('error', 'Sesión inválida o expirada.', [], 401);
    }

    if ($usuario['estatus'] === 'suspendido') {
        registrarActividad($pdo, (int) $usuario['id'], 'acceso_bloqueado', 'Cuenta suspendida.');
        jsonResponse('error', 'Cuenta suspendida.', [], 403);
    }

    if (!hash_equals((string) $usuario['device_hash'], calcularDeviceHash())) {
        registrarActividad($pdo, (int) $usuario['id'], 'device_mismatch', 'Token válido pero device_hash no coincide.');
        jsonResponse('error', 'Sesión inválida o expirada.', [], 401);
    }

    if ($rolesPermitidos !== [] && !in_array($usuario['rol'], $rolesPermitidos, true)) {
        jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
    }

    return $usuario;
}

/** Política de contraseña "nivel militar" — Sección 7.2 del MODULO_01. */
function passwordCumplePolitica(string $password): bool
{
    if (mb_strlen($password) < 14) {
        return false;
    }

    return preg_match('/[a-z]/', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
}

function setCookieToken(string $token, int $ttlSegundos): void
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';

    setcookie('token_acceso', $token, [
        'expires' => time() + $ttlSegundos,
        'path' => '/',
        'secure' => $esProduccion,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function borrarCookieToken(): void
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';

    setcookie('token_acceso', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $esProduccion,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
