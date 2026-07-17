<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/conexion.php';

const CLUB_SESSION_DAYS = [2, 4]; // 2 = martes, 4 = jueves
const CLUB_SESSION_HOUR = 20;
const CLUB_SESSION_MINUTE = 30;

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware
$actor = requireAuth($pdo, ['super_admin', 'admin']);

if ($actor['rol'] !== 'super_admin' && !esModuloVisible($pdo, 'invitados', (string) $actor['rol'])) {
    jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
}

// CAPA 4 — Payload + Sanitización
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

$enlace = isset($payload['enlace']) ? trim((string) $payload['enlace']) : '';
$tema = isset($payload['tema']) ? trim(strip_tags((string) $payload['tema'])) : '';
$fechaHoraCruda = isset($payload['fecha_hora']) ? trim((string) $payload['fecha_hora']) : '';

if ($enlace === '' || filter_var($enlace, FILTER_VALIDATE_URL) === false || mb_strlen($enlace) > 255) {
    jsonResponse('error', 'El enlace de la sesión no es válido.', [], 422);
}

if (mb_strlen($tema) > 200) {
    jsonResponse('error', 'El tema no puede superar 200 caracteres.', [], 422);
}

// Próxima fecha de sesión (mismo criterio que el countdown público del
// Club de Lectura: martes/jueves 8:30 p.m.) — usado solo como respaldo si
// el administrador no especifica una fecha/hora manual.
function calcularProximaSesion(): string
{
    $ahora = new DateTimeImmutable();

    for ($i = 0; $i < 8; $i++) {
        $candidato = $ahora->modify("+{$i} days")->setTime(CLUB_SESSION_HOUR, CLUB_SESSION_MINUTE, 0);

        if (in_array((int) $candidato->format('N') % 7, CLUB_SESSION_DAYS, true) && $candidato > $ahora) {
            return $candidato->format('Y-m-d H:i:s');
        }
    }

    return $ahora->modify('+2 days')->setTime(CLUB_SESSION_HOUR, CLUB_SESSION_MINUTE, 0)->format('Y-m-d H:i:s');
}

$fechaSesion = null;
if ($fechaHoraCruda !== '') {
    $fechaValidada = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $fechaHoraCruda) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fechaHoraCruda);

    if ($fechaValidada === false) {
        jsonResponse('error', 'La fecha/hora de la sesión no es válida.', [], 422);
    }

    $fechaSesion = $fechaValidada->format('Y-m-d H:i:s');
}

// CAPA 5 — Persistencia
try {
    $pdo->beginTransaction();

    $fechaSesion = $fechaSesion ?? calcularProximaSesion();

    $stmt = $pdo->prepare(
        'INSERT INTO historial_sesiones (fecha_hora, enlace, tema, creado_por) VALUES (:fecha, :enlace, :tema, :actor_id)'
    );
    $stmt->execute([
        ':fecha' => $fechaSesion,
        ':enlace' => $enlace,
        ':tema' => $tema !== '' ? $tema : null,
        ':actor_id' => $actor['id'],
    ]);
    $sesionId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT id, nombre, email FROM registro_interesados');
    $stmt->execute();
    $interesados = $stmt->fetchAll();

    $env = obtenerEnv();
    $correosEnviados = 0;

    foreach ($interesados as $interesado) {
        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare(
            'INSERT INTO historial_sesiones_asistentes (sesion_id, interesado_id, token_checkin, notificado_en)
             VALUES (:sesion_id, :interesado_id, :token, NOW())'
        );
        $stmt->execute([
            ':sesion_id' => $sesionId,
            ':interesado_id' => $interesado['id'],
            ':token' => $token,
        ]);

        // El correo lleva el enlace de Check-In, NUNCA el enlace crudo de la
        // videollamada directamente (MODULO_03_CRM_EVENTOS_EN_VIVO §3.1).
        $enlaceCheckin = rtrim($env['APP_URL'] ?? '', '/') . '/checkin.php?token=' . $token;

        $enviado = enviarCorreoTransaccional(
            $interesado['email'],
            'Tu sesión del Círculo de Lectura está por comenzar',
            '<p>Hola ' . htmlspecialchars($interesado['nombre'], ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>La próxima sesión del Círculo de Lectura' . ($tema !== '' ? ' — "' . htmlspecialchars($tema, ENT_QUOTES, 'UTF-8') . '"' : '') . ' está por comenzar.</p>'
            . '<p><a href="' . htmlspecialchars($enlaceCheckin, ENT_QUOTES, 'UTF-8') . '">Ingresar a la sesión</a></p>'
        );

        if ($enviado) {
            $correosEnviados++;
        }
    }

    $pdo->commit();

    registrarActividad($pdo, (int) $actor['id'], 'sesion_compartida', "sesion #{$sesionId}, {$correosEnviados}/" . count($interesados) . ' notificados');

    jsonResponse('success', 'Sesión compartida y notificada a ' . $correosEnviados . ' de ' . count($interesados) . ' interesados.', [
        'sesion_id' => $sesionId,
        'fecha_hora' => $fechaSesion,
    ], 201);
} catch (PDOException $e) {
    $pdo->rollBack();

    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] sesiones_compartir.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos compartir la sesión. Intenta de nuevo.', [], 500);
}
