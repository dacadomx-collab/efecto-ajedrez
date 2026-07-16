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

try {
    $pdo = (new Database())->getConnection();

    // CAPA 2 — Auth middleware (logout exige sesión activa)
    $usuario = requireAuth($pdo);

    // CAPA 5 — Persistencia
    $stmt = $pdo->prepare('UPDATE usuarios SET token_acceso = NULL, token_expira_en = NULL WHERE id = :id');
    $stmt->execute([':id' => $usuario['id']]);

    borrarCookieToken();
    registrarActividad($pdo, (int) $usuario['id'], 'logout');

    jsonResponse('success', 'Sesión cerrada.');
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] logout.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos cerrar la sesión. Intenta de nuevo.', [], 500);
}
