<?php

declare(strict_types=1);

require_once __DIR__ . '/cors.php';

function jsonResponse(string $status, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

$env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', $env['ALLOWED_ORIGINS'] ?? '')));
$isLocal = ($env['APP_ENV'] ?? '') === 'local';

if (!$isLocal && !in_array($origin, $allowedOrigins, true)) {
    jsonResponse('error', 'Diagnóstico no disponible para este origen.', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

// Capa 1 — Filesystem
$rutasCriticas = ['knowledge', 'database', 'assets', 'logs'];
$fsOk = true;
$fsDetalle = [];

foreach ($rutasCriticas as $carpeta) {
    $ruta = __DIR__ . '/../' . $carpeta;
    $existe = is_dir($ruta);
    $fsDetalle[$carpeta] = $existe ? 'presente' : 'ausente';
    $fsOk = $fsOk && $existe;
}

$logsEscribible = is_writable(__DIR__ . '/../logs');
$fsDetalle['logs_escribible'] = $logsEscribible ? 'si' : 'no';
$fsOk = $fsOk && $logsEscribible;

// Capa 2 — DB Handshake
$dbOk = false;
$dbLatenciaMs = null;

try {
    require_once __DIR__ . '/conexion.php';
    $inicio = microtime(true);
    $pdo = (new Database())->getConnection();
    $pdo->query('SELECT 1');
    $dbLatenciaMs = round((microtime(true) - $inicio) * 1000, 2);
    $dbOk = true;
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] status_check.php DB: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
}

// Capa 3 — SMTP Handshake
$smtpOk = false;
$smtpLatenciaMs = null;
$smtpHost = $env['SMTP_HOST'] ?? '';
$smtpPort = (int) ($env['SMTP_PORT'] ?? 0);

if ($smtpHost !== '' && $smtpPort > 0) {
    $inicio = microtime(true);
    $conexion = @stream_socket_client(
        "ssl://{$smtpHost}:{$smtpPort}",
        $codigoError,
        $mensajeError,
        5
    );

    if ($conexion !== false) {
        $smtpOk = true;
        $smtpLatenciaMs = round((microtime(true) - $inicio) * 1000, 2);
        fclose($conexion);
    }
}

$status = ($fsOk && $dbOk && $smtpOk) ? 'success' : 'error';

jsonResponse($status, 'Triple Handshake ejecutado.', [
    'filesystem' => ['ok' => $fsOk, 'detalle' => $fsDetalle],
    'database' => ['ok' => $dbOk, 'latencia_ms' => $dbLatenciaMs],
    'smtp' => ['ok' => $smtpOk, 'latencia_ms' => $smtpLatenciaMs, 'host' => $smtpHost, 'port' => $smtpPort],
], $status === 'success' ? 200 : 503);
