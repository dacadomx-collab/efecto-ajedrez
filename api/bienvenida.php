<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware (bloque exclusivo del Dashboard autenticado)
$actor = requireAuth($pdo);

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

// CAPA 5 — Persistencia
try {
    $frase = obtenerFraseBienvenidaDelDia($pdo);

    jsonResponse('success', 'Cápsula de bienvenida.', [
        'frase' => $frase,
    ]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] bienvenida.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos cargar la bienvenida.', [], 500);
}
