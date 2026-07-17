<?php

declare(strict_types=1);

// Cliente SMTP mínimo (sin dependencias/Composer) para el correo transaccional
// de invitación del Dashboard. Usa las credenciales SMTP_* ya declaradas en
// .env (mismo host validado por api/status_check.php).

function leerRespuestaSmtp($socket): string
{
    $respuesta = '';

    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;
        // Una línea final tiene un espacio después del código (ej. "250 OK"),
        // las líneas intermedias multilinea usan guion ("250-...").
        if (isset($linea[3]) && $linea[3] === ' ') {
            break;
        }
    }

    return $respuesta;
}

// Fase de pruebas (Productor Tzunum, 2026-07-17): TODO correo transaccional
// se intercepta y redirige a la cuenta de auditoría mientras el proyecto no
// esté en producción — permite validar plantillas y enlaces sin escribirle
// a destinatarios reales. Guardado como constante de código (no en .env) a
// petición explícita, pero blindado por APP_ENV: en 'production' esta
// constante se ignora por completo, nunca puede filtrarse por accidente.
const CORREO_AUDITORIA_STAGING = 'dacadomx@yahoo.com';

function enviarCorreoTransaccional(string $destinatarioOriginal, string $asunto, string $cuerpoHtml): bool
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';
    $destinatario = $destinatarioOriginal;

    if (!$esProduccion) {
        $destinatario = CORREO_AUDITORIA_STAGING;
        // Trazabilidad SOLO en el log interno — el asunto y el cuerpo que
        // recibe el productor en su bandeja quedan idénticos a lo que
        // recibiría el destinatario real en producción (auditoría visual
        // fiel), sin banners ni prefijos de depuración.
        error_log('[' . date('Y-m-d H:i:s') . "] email_helper.php: correo interceptado en staging — destinatario original: {$destinatarioOriginal}" . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    }

    $host = $env['SMTP_HOST'] ?? '';
    $port = (int) ($env['SMTP_PORT'] ?? 465);
    $usuario = $env['SMTP_USER'] ?? '';
    $password = $env['SMTP_PASS'] ?? '';

    if ($host === '' || $usuario === '' || $password === '') {
        error_log('[' . date('Y-m-d H:i:s') . '] email_helper.php: SMTP no configurado en .env' . PHP_EOL, 3, __DIR__ . '/../logs/error.log');

        return false;
    }

    $socket = @stream_socket_client("ssl://{$host}:{$port}", $codigoError, $mensajeError, 10);

    if ($socket === false) {
        error_log('[' . date('Y-m-d H:i:s') . "] email_helper.php: conexión SMTP fallida — {$mensajeError}" . PHP_EOL, 3, __DIR__ . '/../logs/error.log');

        return false;
    }

    try {
        leerRespuestaSmtp($socket); // 220 greeting

        fwrite($socket, "EHLO efecto-ajedrez.local\r\n");
        leerRespuestaSmtp($socket);

        fwrite($socket, "AUTH LOGIN\r\n");
        leerRespuestaSmtp($socket);

        fwrite($socket, base64_encode($usuario) . "\r\n");
        leerRespuestaSmtp($socket);

        fwrite($socket, base64_encode($password) . "\r\n");
        $authResp = leerRespuestaSmtp($socket);

        if (!str_starts_with($authResp, '235')) {
            error_log('[' . date('Y-m-d H:i:s') . '] email_helper.php: autenticación SMTP fallida' . PHP_EOL, 3, __DIR__ . '/../logs/error.log');

            return false;
        }

        fwrite($socket, "MAIL FROM:<{$usuario}>\r\n");
        leerRespuestaSmtp($socket);

        fwrite($socket, "RCPT TO:<{$destinatario}>\r\n");
        leerRespuestaSmtp($socket);

        fwrite($socket, "DATA\r\n");
        leerRespuestaSmtp($socket);

        $nombreRemitente = $env['MAIL_FROM_NAME'] ?? 'El Efecto Ajedrez';
        $cabeceras = "From: {$nombreRemitente} <{$usuario}>\r\n"
            . "To: <{$destinatario}>\r\n"
            . "Subject: {$asunto}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n";

        fwrite($socket, $cabeceras . "\r\n" . $cuerpoHtml . "\r\n.\r\n");
        $dataResp = leerRespuestaSmtp($socket);

        fwrite($socket, "QUIT\r\n");

        return str_starts_with($dataResp, '250');
    } finally {
        fclose($socket);
    }
}
