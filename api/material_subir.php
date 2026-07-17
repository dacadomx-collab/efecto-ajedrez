<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const TAMANO_MAXIMO_BYTES = 20 * 1024 * 1024; // 20MB
const DIRECTORIO_DESTINO = __DIR__ . '/../uploads/materiales-protegidos/';

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

// CAPA 4 — Payload (multipart/form-data)
$sesionId = isset($_POST['sesion_id']) ? (int) $_POST['sesion_id'] : 0;

if ($sesionId <= 0) {
    jsonResponse('error', 'Sesión inválida.', [], 422);
}

if (!isset($_FILES['material']) || $_FILES['material']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse('error', 'No se recibió ningún archivo válido.', [], 422);
}

$archivoTmp = $_FILES['material']['tmp_name'];

if (!is_uploaded_file($archivoTmp)) {
    jsonResponse('error', 'Archivo inválido.', [], 422);
}

if ($_FILES['material']['size'] > TAMANO_MAXIMO_BYTES) {
    jsonResponse('error', 'El archivo supera el máximo de 20MB.', [], 422);
}

// MIME-type REAL — nunca se confía en la extensión ni en el Content-Type
// declarado por el navegador. Solo PDF (protección de material literario).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $archivoTmp);
finfo_close($finfo);

if ($mimeReal !== 'application/pdf') {
    jsonResponse('error', 'Solo se permiten archivos PDF.', [], 422);
}

// CAPA 5 — Persistencia (archivo + BD)
try {
    $stmt = $pdo->prepare('SELECT id FROM historial_sesiones WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $sesionId]);

    if ($stmt->fetch() === false) {
        jsonResponse('error', 'La sesión no existe.', [], 404);
    }

    if (!is_dir(DIRECTORIO_DESTINO) && !mkdir(DIRECTORIO_DESTINO, 0755, true) && !is_dir(DIRECTORIO_DESTINO)) {
        throw new RuntimeException('No se pudo preparar el directorio de subida.');
    }

    // Renombrado criptográfico obligatorio — el nombre original nunca se
    // persiste ni se usa como parte de la ruta final.
    $nombreArchivo = bin2hex(random_bytes(16)) . '.pdf';
    $rutaDestino = DIRECTORIO_DESTINO . $nombreArchivo;
    $rutaRelativa = 'uploads/materiales-protegidos/' . $nombreArchivo;

    if (!move_uploaded_file($archivoTmp, $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }

    $stmt = $pdo->prepare('UPDATE historial_sesiones SET material_pdf_path = :ruta WHERE id = :id');
    $stmt->execute([':ruta' => $rutaRelativa, ':id' => $sesionId]);

    registrarActividad($pdo, (int) $actor['id'], 'material_subido', "sesion #{$sesionId}");

    jsonResponse('success', 'Material cargado. Solo podrán descargarlo los asistentes con check-in confirmado.');
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] material_subir.php DB: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos actualizar la sesión. Intenta de nuevo.', [], 500);
} catch (RuntimeException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] material_subir.php archivo: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos guardar el archivo. Intenta de nuevo.', [], 500);
}
