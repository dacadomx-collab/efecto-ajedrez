<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const MINUTOS_ANTICIPACION = 15;
const DURACION_SESION_MINUTOS = 60;

// CAPA 3 — Método HTTP (lectura pública, sin auth — MODULO_03 §3.2)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

// CAPA 5 — Persistencia
try {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->query('SELECT fecha_hora, enlace FROM historial_sesiones ORDER BY fecha_hora DESC LIMIT 1');
    $sesion = $stmt->fetch();

    if ($sesion === false) {
        jsonResponse('success', 'Sin sesión programada.', ['enlace' => null]);
    }

    $inicio = strtotime((string) $sesion['fecha_hora']);
    $abreEn = $inicio - (MINUTOS_ANTICIPACION * 60);
    $cierraEn = $inicio + (DURACION_SESION_MINUTOS * 60);
    $ahora = time();

    $dentroDeVentana = $ahora >= $abreEn && $ahora <= $cierraEn;

    jsonResponse('success', 'Estado de la sesión.', [
        'enlace' => $dentroDeVentana ? $sesion['enlace'] : null,
    ]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] sesion_actual.php: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos verificar la sesión.', [], 500);
}
