<?php

declare(strict_types=1);

// CAPA 1 — CORS (no aplica Origin estricto aquí: es una descarga de archivo
// vía navegación directa, no un fetch — se mantiene por consistencia del
// patrón, pero la autorización real la da el token de check-in, Capa 2).
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const DIRECTORIO_BASE = __DIR__ . '/../';

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';

if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    jsonResponse('error', 'Enlace de descarga no válido.', [], 410);
}

// CAPA 2 — Auth "middleware": no es una sesión de usuarios.rol, es la prueba
// de que este asistente confirmó su presencia real en la sesión en vivo
// (checkin_en IS NOT NULL) — el interés inicial (solo registrado) NO basta.
try {
    $pdo = (new Database())->getConnection();

    $stmt = $pdo->prepare(
        'SELECT hs.material_pdf_path
         FROM historial_sesiones_asistentes hsa
         INNER JOIN historial_sesiones hs ON hs.id = hsa.sesion_id
         WHERE hsa.token_checkin = :token AND hsa.checkin_en IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $fila = $stmt->fetch();

    if ($fila === false || $fila['material_pdf_path'] === null) {
        jsonResponse('error', 'No hay material disponible para descargar con este enlace.', [], 404);
    }

    $rutaAbsoluta = realpath(DIRECTORIO_BASE . $fila['material_pdf_path']);
    $directorioProtegido = realpath(DIRECTORIO_BASE . 'uploads/materiales-protegidos');

    // Verificación de path traversal: la ruta resuelta debe seguir dentro
    // del directorio protegido — nunca se confía en el valor de BD a ciegas.
    if ($rutaAbsoluta === false || $directorioProtegido === false || !str_starts_with($rutaAbsoluta, $directorioProtegido)) {
        jsonResponse('error', 'Material no disponible.', [], 404);
    }

    // CAPA 5 — Persistencia (lectura de archivo, no BD)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="material-circulo-lectura.pdf"');
    header('Content-Length: ' . filesize($rutaAbsoluta));
    header('X-Content-Type-Options: nosniff');
    readfile($rutaAbsoluta);
    exit;
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] material_descargar.php: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos procesar la descarga.', [], 500);
}
