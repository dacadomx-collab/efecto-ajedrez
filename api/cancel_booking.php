<?php

declare(strict_types=1);

// =============================================================================
// PEGASO EXPEDICIONES — cancel_booking.php
// Endpoint: POST /api/cancel_booking.php
// Lógica: Cancela una reserva, libera cupos en expedition_dates.
//         Usa transacción atómica para garantizar consistencia.
//
// Mandamiento #2  — Seguridad Nivel Militar (Prepared Statements, sin raw input)
// Mandamiento #14 — Requiere autenticación real (JWT/token) para ejecutar
// Reglas: try/catch en toda operación BD, log en error.log
// =============================================================================

require_once __DIR__ . '/../knowledge/conexion.php';

// ---------------------------------------------------------------------------
// CONFIGURACIÓN
// ---------------------------------------------------------------------------

define('LOG_PATH', __DIR__ . '/../logs/error.log');

// ---------------------------------------------------------------------------
// HELPERS INTERNOS
// ---------------------------------------------------------------------------

function jsonResponse(string $status, string $message, array $data = [], int $http_code = 200): never
{
    http_response_code($http_code);
    echo json_encode(
        ['status' => $status, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    exit;
}

function logError(string $context, \Throwable $e): void
{
    $timestamp = date('Y-m-d H:i:s');
    $msg = "[{$timestamp}] [cancel_booking] [{$context}] {$e->getMessage()} | File: {$e->getFile()}:{$e->getLine()}" . PHP_EOL;
    error_log($msg, 3, LOG_PATH);
}

// ---------------------------------------------------------------------------
// VALIDAR MÉTODO HTTP
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

// ---------------------------------------------------------------------------
// LEER Y VALIDAR PAYLOAD JSON
// ---------------------------------------------------------------------------

$raw_body = file_get_contents('php://input');

try {
    $payload = json_decode((string) $raw_body, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    jsonResponse('error', 'Payload JSON inválido.', [], 400);
}

// Campos requeridos
$booking_id          = isset($payload['booking_id'])          ? (int) $payload['booking_id']          : 0;
$cancellation_reason = isset($payload['cancellation_reason']) ? trim((string) $payload['cancellation_reason']) : '';

if ($booking_id <= 0) {
    jsonResponse('error', 'booking_id es requerido y debe ser un entero positivo.', [], 422);
}

// La razón es opcional pero se limita a 255 caracteres (coincide con la columna)
$cancellation_reason = mb_substr($cancellation_reason, 0, 255);

// ---------------------------------------------------------------------------
// AUTENTICACIÓN — Mandamiento #14
// TODO: Reemplazar con validación real de JWT cuando el módulo de auth esté listo.
// Por ahora se valida presencia del header Authorization como guardia mínima.
// ---------------------------------------------------------------------------

$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($auth_header)) {
    jsonResponse('error', 'No autorizado. Se requiere token de autenticación.', [], 401);
}

// ---------------------------------------------------------------------------
// CONEXIÓN A LA BASE DE DATOS
// ---------------------------------------------------------------------------

try {
    $database = new Database();
    $pdo      = $database->getConnection();
} catch (\Throwable $e) {
    logError('Database::getConnection', $e);
    jsonResponse('error', 'Error de conexión al servidor. Intente más tarde.', [], 500);
}

// ---------------------------------------------------------------------------
// LÓGICA DE CANCELACIÓN (TRANSACCIÓN ATÓMICA)
//
// Flujo:
//  1. Verificar que la reserva exista y sea cancelable (estado: intent o confirmed)
//  2. Obtener los cupos reservados (spots_reserved) y el expedition_date_id
//  3. Actualizar booking: status = 'cancelled', cancelled_at = NOW()
//  4. Liberar cupos: expedition_dates.available_spots += spots_reserved
//     (con CHECK para no exceder total_spots — defensa en profundidad)
//  5. Si expedition_dates.available_spots < total_spots y status = 'full',
//     reabrir fecha cambiando status a 'open'
//  6. COMMIT
// ---------------------------------------------------------------------------

try {
    $pdo->beginTransaction();

    // --- PASO 1 & 2: Leer la reserva con bloqueo de fila (SELECT FOR UPDATE) ---
    $stmt_select = $pdo->prepare("
        SELECT `id`, `status`, `spots_reserved`, `expedition_date_id`
        FROM   `bookings`
        WHERE  `id` = :booking_id
        LIMIT  1
        FOR UPDATE
    ");
    $stmt_select->execute([':booking_id' => $booking_id]);
    $booking = $stmt_select->fetch(\PDO::FETCH_ASSOC);

    if ($booking === false) {
        $pdo->rollBack();
        jsonResponse('error', 'Reserva no encontrada.', [], 404);
    }

    // Solo se puede cancelar si el estado es 'intent' o 'confirmed'
    $cancellable_statuses = ['intent', 'confirmed'];
    if (!in_array($booking['status'], $cancellable_statuses, true)) {
        $pdo->rollBack();
        jsonResponse(
            'error',
            "La reserva no puede cancelarse. Estado actual: '{$booking['status']}'.",
            [],
            409
        );
    }

    $spots_reserved      = (int) $booking['spots_reserved'];
    $expedition_date_id  = (int) $booking['expedition_date_id'];

    // --- PASO 3: Marcar la reserva como cancelada ---
    $stmt_cancel = $pdo->prepare("
        UPDATE `bookings`
        SET    `status`               = 'cancelled',
               `cancelled_at`        = NOW(),
               `cancellation_reason` = :reason,
               `updated_at`          = NOW()
        WHERE  `id`                  = :booking_id
    ");
    $stmt_cancel->execute([
        ':reason'     => $cancellation_reason ?: null,
        ':booking_id' => $booking_id,
    ]);

    // --- PASO 4: Liberar cupos en expedition_dates ---
    // LEAST() garantiza que available_spots nunca supere total_spots (integridad)
    $stmt_release = $pdo->prepare("
        UPDATE `expedition_dates`
        SET    `available_spots` = LEAST(`available_spots` + :spots, `total_spots`),
               `updated_at`     = NOW()
        WHERE  `id`             = :date_id
    ");
    $stmt_release->execute([
        ':spots'   => $spots_reserved,
        ':date_id' => $expedition_date_id,
    ]);

    // --- PASO 5: Si la fecha estaba 'full', reabrirla ---
    $stmt_reopen = $pdo->prepare("
        UPDATE `expedition_dates`
        SET    `status`      = 'open',
               `updated_at` = NOW()
        WHERE  `id`          = :date_id
          AND  `status`      = 'full'
          AND  `available_spots` > 0
    ");
    $stmt_reopen->execute([':date_id' => $expedition_date_id]);

    $pdo->commit();

    jsonResponse('success', 'Reserva cancelada correctamente. Los cupos han sido liberados.', [
        'booking_id'  => $booking_id,
        'new_status'  => 'cancelled',
        'spots_freed' => $spots_reserved,
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('cancelBooking::transaction', $e);
    jsonResponse('error', 'Error interno al procesar la cancelación. Intente más tarde.', [], 500);

} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('cancelBooking::unexpected', $e);
    jsonResponse('error', 'Error inesperado en el servidor.', [], 500);
}
