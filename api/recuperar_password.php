<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/conexion.php';

const TTL_RECUPERACION_HORAS = 1;

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

// Mensaje único, exista o no la cuenta — anti-enumeración estricta
// (MODULO_01_LOGIN_Y_ACCESO §3.6, mismo principio que §2.2).
const MENSAJE_RECUPERACION = 'Si el correo existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.';

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse('success', MENSAJE_RECUPERACION);
}

// CAPA 5 — Persistencia
try {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE email = :email AND estatus = :estatus LIMIT 1');
    $stmt->execute([':email' => $email, ':estatus' => 'activo']);
    $usuario = $stmt->fetch();

    if ($usuario !== false) {
        $pdo->beginTransaction();

        // Invalida cualquier token de recuperación previo no usado — evita
        // que enlaces viejos sigan siendo válidos en paralelo.
        $stmt = $pdo->prepare('UPDATE recuperacion_password SET usado = 1 WHERE usuario_id = :usuario_id AND usado = 0');
        $stmt->execute([':usuario_id' => $usuario['id']]);

        $tokenClaro = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenClaro);
        $expiraEn = (new DateTimeImmutable())->modify('+' . TTL_RECUPERACION_HORAS . ' hours')->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO recuperacion_password (usuario_id, token_hash, expira_en) VALUES (:usuario_id, :token_hash, :expira_en)'
        );
        $stmt->execute([
            ':usuario_id' => $usuario['id'],
            ':token_hash' => $tokenHash,
            ':expira_en' => $expiraEn,
        ]);

        $pdo->commit();

        $enlace = obtenerAppUrl() . '/restablecer-password.php?token=' . $tokenClaro;

        enviarCorreoTransaccional(
            $email,
            'Restablecer tu contraseña — El Efecto Ajedrez',
            '<p>Hola ' . htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Recibimos una solicitud para restablecer tu contraseña. Este enlace es válido por ' . TTL_RECUPERACION_HORAS . ' hora:</p>'
            . '<p><a href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '">Restablecer contraseña</a></p>'
            . '<p>Si no solicitaste esto, ignora este correo.</p>'
        );

        registrarActividad($pdo, (int) $usuario['id'], 'recuperacion_solicitada');
    }

    jsonResponse('success', MENSAJE_RECUPERACION);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] recuperar_password.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    // Anti-enumeración incluso ante error interno — nunca revelar el fallo real.
    jsonResponse('success', MENSAJE_RECUPERACION);
}
