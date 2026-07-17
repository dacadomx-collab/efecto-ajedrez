<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const PAGINAS_VALIDAS = ['club-lectura'];
const TAMANO_MAXIMO_BYTES = 5 * 1024 * 1024; // 5MB
const DIRECTORIO_DESTINO = __DIR__ . '/../assets/img/landing-uploads/';
const RUTA_PUBLICA_BASE = 'assets/img/landing-uploads/';

const MIME_A_EXTENSION = [
    'image/webp' => 'webp',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
];

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware
$actor = requireAuth($pdo, ['super_admin', 'admin']);

if ($actor['rol'] !== 'super_admin' && !esModuloVisible($pdo, 'landing', (string) $actor['rol'])) {
    jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
}

// CAPA 4 — Payload (multipart/form-data — no JSON, es una subida de archivo)
$pagina = isset($_POST['pagina']) ? trim((string) $_POST['pagina']) : '';
$bloqueId = isset($_POST['bloque_id']) ? trim((string) $_POST['bloque_id']) : '';

if (!in_array($pagina, PAGINAS_VALIDAS, true)) {
    jsonResponse('error', 'Página inválida.', [], 422);
}

if ($bloqueId === '' || !preg_match('/^[a-z0-9_]{1,80}$/', $bloqueId)) {
    jsonResponse('error', 'Identificador de bloque inválido.', [], 422);
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse('error', 'No se recibió ninguna imagen válida.', [], 422);
}

$archivoTmp = $_FILES['imagen']['tmp_name'];

if (!is_uploaded_file($archivoTmp)) {
    jsonResponse('error', 'Archivo inválido.', [], 422);
}

if ($_FILES['imagen']['size'] > TAMANO_MAXIMO_BYTES) {
    jsonResponse('error', 'La imagen supera el máximo de 5MB.', [], 422);
}

// MIME-type REAL del contenido — nunca se confía en la extensión del
// archivo original ni en el Content-Type que declara el navegador.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $archivoTmp);
finfo_close($finfo);

if (!isset(MIME_A_EXTENSION[$mimeReal])) {
    jsonResponse('error', 'Formato de imagen no permitido. Usa WebP, PNG o JPG.', [], 422);
}

// Segunda verificación independiente — confirma que el archivo es
// realmente una imagen decodificable, no solo bytes con la firma MIME correcta.
if (@getimagesize($archivoTmp) === false) {
    jsonResponse('error', 'El archivo no es una imagen válida.', [], 422);
}

$extension = MIME_A_EXTENSION[$mimeReal];

// CAPA 5 — Persistencia (archivo + BD)
try {
    if (!is_dir(DIRECTORIO_DESTINO) && !mkdir(DIRECTORIO_DESTINO, 0755, true) && !is_dir(DIRECTORIO_DESTINO)) {
        throw new RuntimeException('No se pudo preparar el directorio de subida.');
    }

    // Renombrado criptográfico obligatorio — el nombre original del archivo
    // nunca se persiste ni se usa como parte de la ruta final (evita path
    // traversal y cualquier intento de disfrazar un script como imagen).
    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    $rutaDestino = DIRECTORIO_DESTINO . $nombreArchivo;
    $rutaPublica = RUTA_PUBLICA_BASE . $nombreArchivo;

    if (!move_uploaded_file($archivoTmp, $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO configuracion_layout (pagina, bloque_id, tipo, imagen_path, actualizado_por)
         VALUES (:pagina, :bloque_id, :tipo, :imagen_path, :actor_id)
         ON DUPLICATE KEY UPDATE imagen_path = :imagen_path_update, actualizado_por = :actor_id_update, tipo = :tipo_update'
    );
    $stmt->execute([
        ':pagina' => $pagina,
        ':bloque_id' => $bloqueId,
        ':tipo' => 'imagen',
        ':imagen_path' => $rutaPublica,
        ':actor_id' => $actor['id'],
        ':imagen_path_update' => $rutaPublica,
        ':actor_id_update' => $actor['id'],
        ':tipo_update' => 'imagen',
    ]);

    registrarActividad($pdo, (int) $actor['id'], 'layout_imagen_subida', "{$pagina}/{$bloqueId} -> {$rutaPublica}");

    jsonResponse('success', 'Imagen actualizada.', ['imagen_path' => $rutaPublica]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] layout_imagen_subir.php DB: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos actualizar la imagen. Intenta de nuevo.', [], 500);
} catch (RuntimeException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] layout_imagen_subir.php archivo: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos guardar el archivo. Intenta de nuevo.', [], 500);
}
