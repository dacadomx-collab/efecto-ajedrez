<?php

declare(strict_types=1);

require_once __DIR__ . '/api/conexion.php';

// Verificación server-side (además de la que ya hace api/setup_genesis.php
// en la mutación) para no mostrar el formulario si el sistema ya fue
// inicializado — defensa en profundidad, no reemplaza al endpoint.
$requiereProvisioning = true;

try {
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->query('SELECT COUNT(*) FROM usuarios');
    $requiereProvisioning = ((int) $stmt->fetchColumn()) === 0;
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] setup-genesis.php: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
}

if (!$requiereProvisioning) {
    http_response_code(403);
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Génesis | El Efecto Ajedrez</title>
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

                <?php if ($requiereProvisioning): ?>
                    <form id="setup-genesis-form" class="lead-form" novalidate>
                        <h1 class="auth-page__title">Configuración Génesis</h1>
                        <p class="auth-page__lead">Crea la cuenta raíz (<code>super_admin</code>) del Dashboard. Este formulario se desactiva automáticamente después de usarse.</p>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="setup-nombre">Nombre</label>
                            <input class="lead-form__input" type="text" id="setup-nombre" name="nombre" autocomplete="name" required>
                        </div>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="setup-email">Correo electrónico</label>
                            <input class="lead-form__input" type="email" id="setup-email" name="email" autocomplete="username" required>
                        </div>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="setup-password">Contraseña</label>
                            <div class="password-field">
                                <input class="lead-form__input" type="password" id="setup-password" name="password" autocomplete="new-password" required>
                                <button type="button" class="password-field__toggle" data-password-toggle="setup-password" aria-label="Mostrar contraseña" aria-pressed="false">👁</button>
                            </div>
                            <div class="password-strength" data-password-strength-for="setup-password">
                                <div class="password-strength__track">
                                    <div class="password-strength__fill" data-password-strength-fill></div>
                                </div>
                                <p class="password-strength__label" data-password-strength-label></p>
                            </div>
                        </div>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="setup-password-confirmacion">Confirmar contraseña</label>
                            <div class="password-field">
                                <input class="lead-form__input" type="password" id="setup-password-confirmacion" name="password_confirmacion" autocomplete="new-password" required>
                                <button type="button" class="password-field__toggle" data-password-toggle="setup-password-confirmacion" aria-label="Mostrar contraseña" aria-pressed="false">👁</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn--primary lead-form__submit">Crear cuenta raíz</button>
                        <p id="setup-genesis-status" class="lead-form__status" role="status" aria-live="polite"></p>
                    </form>
                <?php else: ?>
                    <div class="lead-form">
                        <h1 class="auth-page__title">Configuración ya completada</h1>
                        <p class="auth-page__lead">El sistema ya tiene una cuenta raíz configurada. <a href="login.php">Ir al acceso</a>.</p>
                    </div>
                <?php endif; ?>

                <div id="auth-error-container" class="auth-error-container" hidden role="alert">
                    <p id="auth-error-message"></p>
                    <button type="button" id="auth-retry-btn" class="btn btn--primary">Reintentar</button>
                </div>
            </div>
        </div>
    </main>

    <button type="button" id="btn-scroll-top" class="club-scroll-top" aria-label="Volver arriba">↑</button>

    <script src="assets/js/main.js" defer></script>
</body>
</html>
