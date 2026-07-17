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

// CAPA 4 — Payload + Sanitización + Validación estricta
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$nombre = isset($payload['nombre']) ? trim(strip_tags((string) $payload['nombre'])) : '';
$email = isset($payload['email']) ? trim(strip_tags((string) $payload['email'])) : '';
$edadCruda = $payload['edad'] ?? null;

// Nombre: mínimo 2 caracteres, al menos una letra real (rechaza "232323"),
// solo letras/espacios/guiones/apóstrofes (rechaza basura tipo "$$$", "###").
if ($nombre === '' || mb_strlen($nombre) > 120 || preg_match('/^[\p{L}\s\'.-]{2,120}$/u', $nombre) !== 1 || preg_match('/\p{L}/u', $nombre) !== 1) {
    jsonResponse('error', 'Ingresa tu nombre real (solo letras).', [], 422);
}

// Correo: filter_var + regex estricto adicional (rechaza "ASASDASD" y
// similares que no tengan forma real de correo).
if ($email === '' || mb_strlen($email) > 190
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $email) !== 1
) {
    jsonResponse('error', 'El correo electrónico no es válido.', [], 422);
}

// Edad: entero estricto — rechaza "30.5", "treinta", etc. (no basta con (int)).
if (!is_numeric($edadCruda) || (float) $edadCruda !== (float) (int) $edadCruda) {
    jsonResponse('error', 'La edad debe ser un número entero válido.', [], 422);
}

$edad = (int) $edadCruda;

if ($edad < 1 || $edad > 120) {
    jsonResponse('error', 'La edad no es válida.', [], 422);
}

// Municipio/Estado/País: SIEMPRE resueltos en el backend vía IP — el
// formulario público ya no los solicita (recorte de campos).
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$geo = resolverGeoIp($ip);

// CAPA 5 — Persistencia (PDO sin emulación, binding explícito)
try {
    $pdo = (new Database())->getConnection();

    // Validación inflexible de unicidad: una misma persona (mismo correo)
    // nunca puede duplicarse en el ledger de interesados. La verificación se
    // hace de forma silenciosa — la respuesta al cliente es idéntica exista
    // o no el duplicado, mismo principio anti-enumeración que MODULO_01
    // §2.2 (evita que un tercero use este endpoint para confirmar si un
    // correo específico ya está registrado).
    $stmt = $pdo->prepare('SELECT id FROM registro_interesados WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $yaRegistrado = $stmt->fetch() !== false;

    if (!$yaRegistrado) {
        $stmt = $pdo->prepare(
            'INSERT INTO registro_interesados (nombre, email, edad, ciudad, estado, ip, ip_pais, ip_estado, ip_ciudad)
             VALUES (:nombre, :email, :edad, :ciudad, :estado, :ip, :ip_pais, :ip_estado, :ip_ciudad)'
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':edad' => $edad,
            ':ciudad' => $geo['ciudad'],
            ':estado' => $geo['estado'],
            ':ip' => $ip,
            ':ip_pais' => $geo['pais'],
            ':ip_estado' => $geo['estado'],
            ':ip_ciudad' => $geo['ciudad'],
        ]);
    }

    jsonResponse('success', '¡Listo! Te avisaremos por correo cuando esté por comenzar la próxima sesión.', [], 201);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] registro_interesado.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos procesar tu registro. Intenta más tarde.', [], 500);
}
