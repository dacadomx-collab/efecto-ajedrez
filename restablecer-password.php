<?php

declare(strict_types=1);

$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$tokenValidoFormato = $token !== '' && ctype_xdigit($token) && strlen($token) === 64;
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña | El Efecto Ajedrez</title>
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

                <?php if ($tokenValidoFormato): ?>
                    <form id="restablecer-password-form" class="lead-form" novalidate data-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <h1 class="auth-page__title">Restablecer contraseña</h1>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="restablecer-password">Nueva contraseña</label>
                            <div class="password-field">
                                <input class="lead-form__input" type="password" id="restablecer-password" name="password" autocomplete="new-password" required>
                                <button type="button" class="password-field__toggle" data-password-toggle="restablecer-password" aria-label="Mostrar contraseña" aria-pressed="false">👁</button>
                            </div>
                            <div class="password-strength" data-password-strength-for="restablecer-password">
                                <div class="password-strength__track">
                                    <div class="password-strength__fill" data-password-strength-fill></div>
                                </div>
                                <p class="password-strength__label" data-password-strength-label></p>
                            </div>
                        </div>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="restablecer-password-confirmacion">Confirmar contraseña</label>
                            <div class="password-field">
                                <input class="lead-form__input" type="password" id="restablecer-password-confirmacion" name="password_confirmacion" autocomplete="new-password" required>
                                <button type="button" class="password-field__toggle" data-password-toggle="restablecer-password-confirmacion" aria-label="Mostrar contraseña" aria-pressed="false">👁</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn--primary lead-form__submit">Restablecer contraseña</button>
                        <p id="restablecer-password-status" class="lead-form__status" role="status" aria-live="polite"></p>
                    </form>
                <?php else: ?>
                    <div class="lead-form">
                        <h1 class="auth-page__title">Enlace no válido</h1>
                        <p class="auth-page__lead">Este enlace de recuperación no es válido. Solicita uno nuevo.</p>
                        <p class="auth-page__link-row"><a href="recuperar-password.php">Solicitar nuevo enlace</a></p>
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
