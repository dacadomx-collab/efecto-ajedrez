<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/conexion.php';

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware: exclusivo super_admin, es una acción de
// configuración/auditoría, no un flujo operativo de alta de usuarios.
requireAuth($pdo, ['super_admin']);

// Este endpoint NUNCA toca las tablas usuarios/invitaciones — es
// exclusivamente una previsualización de la plantilla HTML real, enviada al
// correo de auditoría (email_helper.php redirige automáticamente mientras
// APP_ENV !== 'production'). El token del enlace es solo para fines
// visuales — no se persiste, no resuelve una invitación real.
$env = obtenerEnv();
$tokenPreview = bin2hex(random_bytes(32));
$enlacePreview = rtrim($env['APP_URL'] ?? '', '/') . '/invitacion.php?token=' . $tokenPreview;

// Misma función de plantilla que usa api/usuarios_invitar.php — el
// productor debe auditar exactamente el mismo diseño que recibirá
// cualquier invitado real, sin ningún texto de vista previa visible.
$enviado = enviarCorreoTransaccional(
    'preview@efecto-ajedrez.local',
    'Invitación al Dashboard — El Efecto Ajedrez',
    construirPlantillaInvitacion('Paola Palomares', $enlacePreview)
);

if ($enviado) {
    jsonResponse('success', 'Correo de prueba despachado a staging.');
}

jsonResponse('error', 'No pudimos enviar el correo de prueba.', [], 500);
