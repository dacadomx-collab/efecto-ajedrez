<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

function sistemaRequiereProvisioning(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM usuarios');

    return (int) $stmt->fetchColumn() === 0;
}

$pdo = (new Database())->getConnection();

// CAPA 3 — Método HTTP (GET = estado, POST = mutación de creación)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        jsonResponse('success', 'Estado de inicialización.', [
            'requiere_provisioning' => sistemaRequiereProvisioning($pdo),
        ]);
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] setup_genesis.php GET: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
        jsonResponse('error', 'No pudimos verificar el estado del sistema.', [], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

try {
    // La ruta se autodesactiva por estado de datos — 403 determinista si ya
    // existe al menos un usuario (MODULO_01_LOGIN_Y_ACCESO §8.4).
    if (!sistemaRequiereProvisioning($pdo)) {
        jsonResponse('error', 'Esta ruta ya no está disponible.', [], 403);
    }
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] setup_genesis.php check: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos verificar el estado del sistema.', [], 500);
}

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$nombre = isset($payload['nombre']) ? trim(strip_tags((string) $payload['nombre'])) : '';
$email = isset($payload['email']) ? trim(strip_tags((string) $payload['email'])) : '';
$password = isset($payload['password']) ? (string) $payload['password'] : '';
$passwordConfirmacion = isset($payload['password_confirmacion']) ? (string) $payload['password_confirmacion'] : '';

if ($nombre === '' || mb_strlen($nombre) > 120) {
    jsonResponse('error', 'El nombre es requerido y debe tener máximo 120 caracteres.', [], 422);
}

if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse('error', 'El correo electrónico no es válido.', [], 422);
}

if ($password === '' || $password !== $passwordConfirmacion) {
    jsonResponse('error', 'Las contraseñas no coinciden.', [], 422);
}

$definicionPolitica = politicaSeguridadDefinicion(obtenerPoliticaActiva($pdo));

if (!passwordCumplePolitica($password, $definicionPolitica)) {
    jsonResponse('error', mensajePoliticaPassword($definicionPolitica), [], 422);
}

// CAPA 5 — Persistencia (PDO sin emulación, binding explícito)
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, rol, estatus)
         VALUES (:nombre, :email, :password_hash, :rol, :estatus)'
    );
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
    $stmt->bindValue(':rol', 'super_admin', PDO::PARAM_STR);
    $stmt->bindValue(':estatus', 'activo', PDO::PARAM_STR);
    $stmt->execute();

    $usuarioId = (int) $pdo->lastInsertId();
    $pdo->commit();

    registrarActividad($pdo, $usuarioId, 'genesis_provisioning', 'Primer super_admin creado.');

    jsonResponse('success', 'Cuenta raíz creada. Ya puedes iniciar sesión.', [], 201);
} catch (PDOException $e) {
    $pdo->rollBack();

    if ($e->getCode() === '23000') {
        jsonResponse('error', 'Ese correo ya está registrado.', [], 409);
    }

    // CAPA 6 — Try/Catch global: SQLSTATE + mensaje solo al log interno
    error_log('[' . date('Y-m-d H:i:s') . '] setup_genesis.php POST: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos completar la inicialización. Intenta de nuevo.', [], 500);
}
