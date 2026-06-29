<?php

declare(strict_types=1);

// 1. CORS (siempre primero)
require_once __DIR__ . '/cors.php';

function jsonResponse(string $status, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. MÉTODO HTTP — endpoint público de captación, sin Bearer JWT (Mandamiento 14
//    no aplica a formularios anónimos de lead-gen). La seguridad de mutación la
//    da el CORS de origen estricto + sanitización + Prepared Statements.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    jsonResponse('error', 'Content-Type debe ser application/json.', [], 415);
}

// 3. LEER Y VALIDAR PAYLOAD
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

// 4. CONEXIÓN A DB
require_once __DIR__ . '/conexion.php';

// 5. LÓGICA DE NEGOCIO (con try/catch + log)
try {
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO leads_captura (nombre, email, origen) VALUES (:nombre, :email, :origen)'
    );
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':origen' => 'home_hero',
    ]);

    jsonResponse('success', '¡Gota de agua aportada! Revisa tu correo para descargar tu Botiquín de Emergencia.', [], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonResponse('success', '¡Gota de agua aportada! Revisa tu correo para descargar tu Botiquín de Emergencia.', [], 200);
    }

    $logLine = '[' . date('Y-m-d H:i:s') . '] captura_lead.php: ' . $e->getMessage() . PHP_EOL;
    error_log($logLine, 3, __DIR__ . '/../logs/error.log');

    jsonResponse('error', 'No pudimos procesar tu registro. Intenta más tarde.', [], 500);
}
