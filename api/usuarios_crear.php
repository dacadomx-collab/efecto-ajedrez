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

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware: solo admin/super_admin dan de alta usuarios
$actor = requireAuth($pdo, ['super_admin', 'admin']);

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$nombre = isset($payload['nombre']) ? trim(strip_tags((string) $payload['nombre'])) : '';
$email = isset($payload['email']) ? trim(strip_tags((string) $payload['email'])) : '';
$password = isset($payload['password']) ? (string) $payload['password'] : '';

if ($nombre === '' || mb_strlen($nombre) > 120) {
    jsonResponse('error', 'El nombre es requerido y debe tener máximo 120 caracteres.', [], 422);
}

if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse('error', 'El correo electrónico no es válido.', [], 422);
}

if (!passwordCumplePolitica($password)) {
    jsonResponse('error', 'La contraseña debe tener mínimo 14 caracteres, con mayúscula, minúscula, número y símbolo.', [], 422);
}

// Jerarquía: un admin no puede crear un super_admin (solo el propio
// super_admin puede promover a ese nivel).
$rolNuevo = ($actor['rol'] === 'super_admin' && ($payload['rol'] ?? '') === 'super_admin')
    ? 'super_admin'
    : 'admin';

// CAPA 5 — Persistencia
try {
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, rol, estatus)
         VALUES (:nombre, :email, :password_hash, :rol, :estatus)'
    );
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
    $stmt->bindValue(':rol', $rolNuevo, PDO::PARAM_STR);
    $stmt->bindValue(':estatus', 'activo', PDO::PARAM_STR);
    $stmt->execute();

    registrarActividad($pdo, (int) $actor['id'], 'usuario_creado_directo', 'Nuevo usuario: ' . $email);

    jsonResponse('success', 'Usuario creado y activo de inmediato.', [], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonResponse('error', 'Ese correo ya está registrado.', [], 409);
    }

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] usuarios_crear.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos crear el usuario. Intenta de nuevo.', [], 500);
}
