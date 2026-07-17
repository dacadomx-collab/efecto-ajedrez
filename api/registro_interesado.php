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

// CAPA 4 — Payload + Sanitización
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$nombre = isset($payload['nombre']) ? trim(strip_tags((string) $payload['nombre'])) : '';
$email = isset($payload['email']) ? trim(strip_tags((string) $payload['email'])) : '';
$edad = isset($payload['edad']) ? (int) $payload['edad'] : 0;
$ciudad = isset($payload['ciudad']) ? trim(strip_tags((string) $payload['ciudad'])) : '';
$estado = isset($payload['estado']) ? trim(strip_tags((string) $payload['estado'])) : '';

if ($nombre === '' || mb_strlen($nombre) > 120) {
    jsonResponse('error', 'El nombre es requerido y debe tener máximo 120 caracteres.', [], 422);
}

if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse('error', 'El correo electrónico no es válido.', [], 422);
}

if ($edad < 1 || $edad > 120) {
    jsonResponse('error', 'La edad no es válida.', [], 422);
}

if ($ciudad === '' || mb_strlen($ciudad) > 120) {
    jsonResponse('error', 'La ciudad es requerida.', [], 422);
}

if ($estado === '' || mb_strlen($estado) > 120) {
    jsonResponse('error', 'El estado es requerido.', [], 422);
}

// IP tomada de REMOTE_ADDR — nunca de un header spoofable como
// X-Forwarded-For sin un proxy de confianza documentado (MODULO_03 §2).
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$geo = resolverGeoIp($ip);

// CAPA 5 — Persistencia (PDO sin emulación, binding explícito)
try {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare(
        'INSERT INTO registro_interesados (nombre, email, edad, ciudad, estado, ip, ip_pais, ip_estado, ip_ciudad)
         VALUES (:nombre, :email, :edad, :ciudad, :estado, :ip, :ip_pais, :ip_estado, :ip_ciudad)'
    );
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':edad' => $edad,
        ':ciudad' => $ciudad,
        ':estado' => $estado,
        ':ip' => $ip,
        ':ip_pais' => $geo['pais'],
        ':ip_estado' => $geo['estado'],
        ':ip_ciudad' => $geo['ciudad'],
    ]);

    jsonResponse('success', '¡Listo! Te avisaremos por correo cuando esté por comenzar la próxima sesión.', [], 201);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] registro_interesado.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos procesar tu registro. Intenta más tarde.', [], 500);
}
