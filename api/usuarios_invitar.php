<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/conexion.php';

const TTL_INVITACION_HORAS = 48;

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware: solo admin/super_admin invitan usuarios
$actor = requireAuth($pdo, ['super_admin', 'admin']);

// Mapeo Dinámico de Permisos (MODULO_01_LOGIN_Y_ACCESO §6.1): super_admin
// siempre puede; admin depende de la matriz configurada por el super_admin.
if ($actor['rol'] !== 'super_admin' && !esModuloVisible($pdo, 'usuarios', (string) $actor['rol'])) {
    jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
}

// CAPA 4 — Payload
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$nombre = isset($payload['nombre']) ? trim(strip_tags((string) $payload['nombre'])) : '';
$email = isset($payload['email']) ? trim(strip_tags((string) $payload['email'])) : '';

if ($nombre === '' || mb_strlen($nombre) > 120) {
    jsonResponse('error', 'El nombre es requerido y debe tener máximo 120 caracteres.', [], 422);
}

if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse('error', 'El correo electrónico no es válido.', [], 422);
}

// Jerarquía (MODULO_01_LOGIN_Y_ACCESO §6): el rol solicitado se recorta al
// máximo que el actor puede otorgar — nunca se confía en el payload crudo.
$rolSolicitado = isset($payload['rol']) ? trim((string) $payload['rol']) : 'admin';
$rolNuevo = clamparRolSegunActor($rolSolicitado, (string) $actor['rol']);

// CAPA 5 — Persistencia
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, rol, estatus)
         VALUES (:nombre, :email, NULL, :rol, :estatus)'
    );
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':rol', $rolNuevo, PDO::PARAM_STR);
    $stmt->bindValue(':estatus', 'pendiente', PDO::PARAM_STR);
    $stmt->execute();

    $usuarioId = (int) $pdo->lastInsertId();

    $tokenClaro = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenClaro);
    $expiraEn = (new DateTimeImmutable())->modify('+' . TTL_INVITACION_HORAS . ' hours')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO invitaciones (usuario_id, token_hash, expira_en) VALUES (:usuario_id, :token_hash, :expira_en)'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':token_hash' => $tokenHash,
        ':expira_en' => $expiraEn,
    ]);

    $pdo->commit();

    $env = obtenerEnv();
    $enlaceInvitacion = rtrim($env['APP_URL'] ?? '', '/') . '/invitacion.php?token=' . $tokenClaro;

    $correoEnviado = enviarCorreoTransaccional(
        $email,
        'Invitación al Dashboard — El Efecto Ajedrez',
        '<p>Hola ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Has sido invitado(a) al Dashboard de El Efecto Ajedrez. Define tu contraseña en el siguiente enlace (válido por ' . TTL_INVITACION_HORAS . ' horas):</p>'
        . '<p><a href="' . htmlspecialchars($enlaceInvitacion, ENT_QUOTES, 'UTF-8') . '">Aceptar invitación</a></p>'
    );

    registrarActividad($pdo, (int) $actor['id'], 'usuario_invitado', 'Invitación enviada a: ' . $email . ($correoEnviado ? '' : ' (correo no confirmado)'));

    $respuesta = ['correo_enviado' => $correoEnviado];

    // El enlace en claro solo se expone en la respuesta cuando el entorno es
    // local — facilita pruebas en XAMPP sin depender del SMTP real. Nunca en
    // producción (el enlace debe viajar únicamente por el correo).
    if (($env['APP_ENV'] ?? '') === 'local') {
        $respuesta['enlace_invitacion_local'] = $enlaceInvitacion;
    }

    jsonResponse('success', 'Invitación registrada.', $respuesta, 201);
} catch (PDOException $e) {
    $pdo->rollBack();

    if ($e->getCode() === '23000') {
        jsonResponse('error', 'Ese correo ya está registrado.', [], 409);
    }

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] usuarios_invitar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos registrar la invitación. Intenta de nuevo.', [], 500);
}
