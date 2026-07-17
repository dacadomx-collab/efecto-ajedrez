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

/**
 * Plantilla premium "Trinchera Nocturna" del correo de invitación —
 * MODULO_01_LOGIN_Y_ACCESO §9.4. Tabla HTML + estilos inline (obligatorio
 * para compatibilidad real con clientes de correo — Outlook/Yahoo ignoran
 * <style> externo y buena parte del CSS moderno), fondo oscuro absoluto y
 * acentos magenta simulando luz de neón de estudio.
 */
function construirPlantillaInvitacion(string $nombre, string $enlaceInvitacion, string $email = ''): string
{
    $env = obtenerEnv();
    // URL absoluta real (nunca relativa) — los clientes de correo no tienen
    // noción del dominio del sitio, cargan la imagen directamente desde
    // APP_URL del entorno activo (con fail-safe dinámico, ver obtenerAppUrl()).
    $appUrl = obtenerAppUrl();
    $logoUrl = $appUrl . '/assets/img/logo3-removebg-preview.png';
    $enlaceHome = $appUrl . '/index.php';
    $enlaceClub = $appUrl . '/club-lectura.php';

    // Canal de contacto/baja: se reutiliza el correo transaccional ya
    // configurado en .env (SMTP_USER) — nunca se inventa una dirección de
    // contacto que no exista realmente.
    $correoContacto = $env['SMTP_USER'] ?? 'hola@efecto-ajedrez.tourfindy.com';
    $enlaceBaja = 'mailto:' . $correoContacto . '?subject=' . rawurlencode('Baja de notificaciones — El Efecto Ajedrez');

    $nombreEscapado = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $enlaceInvitacionEscapado = htmlspecialchars($enlaceInvitacion, ENT_QUOTES, 'UTF-8');
    $emailEscapado = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    // Línea de trazabilidad visible dentro del propio HTML — el destinatario
    // real que el operador tecleó en el Dashboard queda reflejado en el
    // cuerpo del correo incluso cuando el transporte de red se desvía al
    // buzón de auditoría de staging (APP_ENV != production).
    $lineaDestinatario = $emailEscapado !== ''
        ? '<p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#8A93A3;font-family:Arial,Helvetica,sans-serif;">Esta invitación fue generada exclusivamente para: <strong style="color:#D6DCE5;">' . $emailEscapado . '</strong></p>'
        : '';

    return <<<HTML
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0A0C14;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:480px;background-color:#10141E;border:1px solid #E91E63;border-radius:14px;overflow:hidden;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding:38px 32px 20px;">
                            <img src="{$logoUrl}" alt="El Efecto Ajedrez: Mentores al Revés" width="170" style="display:block;margin:0 auto 20px;">
                            <p style="margin:0;font-family:Georgia,serif;font-style:italic;color:#E91E63;font-size:17px;line-height:1.6;">
                                "Expertos en inexpertos: aprendiendo la estrategia de la trinchera juntos."
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <hr style="border:none;border-top:1px solid #E91E63;opacity:0.35;margin:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 32px 8px;font-family:Arial,Helvetica,sans-serif;color:#FFFFFF;">
                            <h1 style="margin:0 0 18px;font-size:28px;font-weight:700;color:#FFFFFF;">Hola {$nombreEscapado},</h1>
                            {$lineaDestinatario}
                            <p style="margin:0 0 18px;font-size:19px;line-height:1.75;color:#D6DCE5;">
                                Has sido invitado(a) a formar parte del Dashboard de El Efecto Ajedrez —
                                un espacio construido con la certeza de que cada hogar es un refugio, y que
                                acompañar sin violencia es una estrategia que se aprende, un paso a la vez.
                            </p>
                            <p style="margin:0 0 28px;font-size:19px;line-height:1.75;color:#D6DCE5;">
                                Solo falta un paso para activar tu acceso: define tu contraseña desde el
                                siguiente enlace.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 32px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" bgcolor="#E91E63" style="border-radius:999px;">
                                        <a href="{$enlaceInvitacionEscapado}" target="_blank" style="display:inline-block;padding:18px 36px;font-family:Arial,Helvetica,sans-serif;font-size:19px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:999px;">
                                            Configurar Mi Contraseña y Acceder al Dashboard
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <hr style="border:none;border-top:1px solid #E91E63;opacity:0.2;margin:0;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 32px 8px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 12px;font-size:15px;letter-spacing:0.04em;text-transform:uppercase;color:#D6DCE5;">También puedes explorar</p>
                            <a href="{$enlaceHome}" target="_blank" style="color:#E91E63;text-decoration:underline;font-size:17px;margin:0 10px;">Portal del Videopodcast</a>
                            <a href="{$enlaceClub}" target="_blank" style="color:#E91E63;text-decoration:underline;font-size:17px;margin:0 10px;">Círculo de Lectura</a>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 32px 8px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0;font-size:16px;font-style:italic;color:#E91E63;">"Colibrí siempre colibrí"</p>
                            <p style="margin:8px 0 0;font-size:14px;color:#8A93A3;">&copy; 2026 El Efecto Ajedrez: Mentores al Revés — Paola Palomares.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px;">
                            <hr style="border:none;border-top:1px solid #4A5160;opacity:0.4;margin:0;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 32px 32px;font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 10px;font-size:13px;line-height:1.7;color:#8A93A3;">
                                El Efecto Ajedrez: Mentores al Revés<br>
                                Videopodcast de Crianza Positiva y Educación sin Violencia<br>
                                La Paz, Baja California Sur, México
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.7;color:#8A93A3;">
                                Recibiste este correo porque alguien de tu confianza te invitó al Dashboard.
                                <a href="{$enlaceBaja}" style="color:#8A93A3;text-decoration:underline;">Darme de baja de estas notificaciones</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    HTML;
}

function enviarCorreoTransaccional(string $destinatarioOriginal, string $asunto, string $cuerpoHtml): bool
{
    $env = obtenerEnv();
    // Destinatario final SIEMPRE dinámico — inyectado directamente desde el
    // parámetro real recibido por la función (Mandamiento 12: prohibido
    // redirigir a buzones fijos, de auditoría o heredados del andamiaje).
    $destinatario = $destinatarioOriginal;

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

        // Cabecera List-Unsubscribe (RFC 2369/8058) — requisito universal de
        // entregabilidad (Ley de Oro Anti-SPAM, MODULO_01_LOGIN_Y_ACCESO §2.4):
        // todo correo transaccional saliente del holding, sin excepción, la
        // incluye — no solo la plantilla premium de invitación. Reutiliza el
        // mismo buzón ya configurado en .env, nunca una dirección inventada.
        $enlaceBajaHeader = 'mailto:' . $usuario . '?subject=' . rawurlencode('Baja de notificaciones');

        $nombreRemitente = $env['MAIL_FROM_NAME'] ?? 'El Efecto Ajedrez';
        $cabeceras = "From: {$nombreRemitente} <{$usuario}>\r\n"
            . "To: <{$destinatario}>\r\n"
            . "Subject: {$asunto}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "List-Unsubscribe: <{$enlaceBajaHeader}>\r\n"
            . "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";

        fwrite($socket, $cabeceras . "\r\n" . $cuerpoHtml . "\r\n.\r\n");
        $dataResp = leerRespuestaSmtp($socket);

        fwrite($socket, "QUIT\r\n");

        return str_starts_with($dataResp, '250');
    } finally {
        fclose($socket);
    }
}
