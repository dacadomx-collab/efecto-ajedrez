<?php

declare(strict_types=1);

require_once __DIR__ . '/api/auth_helpers.php';
require_once __DIR__ . '/api/conexion.php';

// Sala de Check-In / Live Gate — MODULO_03_CRM_EVENTOS_EN_VIVO §4. Único
// destino de los enlaces enviados por correo; registra asistencia real de
// forma determinista (idempotente — un segundo clic no duplica el check-in)
// y ofrece el acceso a la sesión y, si existe, la descarga del material.

const MENSAJE_TOKEN_INVALIDO = 'Este enlace ya no es válido.';

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$valido = false;
$enlaceDestino = null;
$tema = null;
$hayMaterial = false;

if ($token !== '' && ctype_xdigit($token) && strlen($token) === 64) {
    try {
        $pdo = (new Database())->getConnection();

        $stmt = $pdo->prepare(
            'SELECT hsa.id, hsa.checkin_en, hs.enlace, hs.tema, hs.material_pdf_path
             FROM historial_sesiones_asistentes hsa
             INNER JOIN historial_sesiones hs ON hs.id = hsa.sesion_id
             WHERE hsa.token_checkin = :token LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $fila = $stmt->fetch();

        if ($fila !== false) {
            $valido = true;
            $enlaceDestino = $fila['enlace'];
            $tema = $fila['tema'];
            $hayMaterial = $fila['material_pdf_path'] !== null;

            // Idempotente: solo se escribe la primera vez — un segundo clic
            // en el mismo enlace no reescribe ni duplica la asistencia.
            if ($fila['checkin_en'] === null) {
                $stmt = $pdo->prepare('UPDATE historial_sesiones_asistentes SET checkin_en = NOW() WHERE id = :id');
                $stmt->execute([':id' => $fila['id']]);
            }
        }
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] checkin.php: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
    }
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $valido ? 'Bienvenido(a) a tu sesión' : 'Enlace no válido'; ?> | El Efecto Ajedrez</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&family=Open+Sans:wght@400;600&display=swap">

    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="auth-body">
    <button type="button" class="dash-topbar__theme-toggle auth-theme-toggle" data-theme-toggle aria-label="Cambiar entre modo claro y oscuro">🌙</button>
    <main class="auth-page">
        <div class="container">
            <div class="auth-page__wrap">
                <a href="index.php" class="auth-page__brand">
                    <img src="assets/img/logo.png" alt="El Efecto Ajedrez: Mentores al Revés" class="site-header__logo">
                    <span>El Efecto Ajedrez</span>
                </a>

                <?php if ($valido && $enlaceDestino !== null): ?>
                    <div class="lead-form checkin-gate">
                        <h1 class="auth-page__title">¡Ya estás dentro! 📚</h1>
                        <p class="auth-page__lead">
                            Tu asistencia quedó registrada.
                            <?php if ($tema): ?>Hoy platicamos sobre: <strong><?php echo htmlspecialchars($tema, ENT_QUOTES, 'UTF-8'); ?></strong>.<?php endif; ?>
                        </p>
                        <a href="<?php echo htmlspecialchars($enlaceDestino, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn--primary checkin-gate__btn">Ingresar a la sesión</a>
                        <?php if ($hayMaterial): ?>
                            <a href="api/material_descargar.php?token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn--primary checkin-gate__btn">Descargar material de la sesión</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="lead-form">
                        <h1 class="auth-page__title">Enlace no válido</h1>
                        <p class="auth-page__lead"><?php echo htmlspecialchars(MENSAJE_TOKEN_INVALIDO, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="auth-page__link-row"><a href="club-lectura.php">Ir al Círculo de Lectura</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
