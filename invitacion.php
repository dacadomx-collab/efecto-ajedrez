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
    <title>Aceptar Invitación | El Efecto Ajedrez</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&family=Open+Sans:wght@400;600&display=swap">

    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <main class="auth-page">
        <div class="container">
            <div class="auth-page__wrap">
                <a href="index.php" class="auth-page__brand">
                    <img src="assets/img/logo.png" alt="El Efecto Ajedrez: Mentores al Revés" class="site-header__logo">
                    <span>El Efecto Ajedrez</span>
                </a>

                <?php if ($tokenValidoFormato): ?>
                    <form id="invitacion-form" class="lead-form" novalidate data-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <h1 class="auth-page__title">Define tu contraseña</h1>
                        <p class="auth-page__lead">Última paso para activar tu acceso al Dashboard.</p>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="invitacion-password">Nueva contraseña</label>
                            <input class="lead-form__input" type="password" id="invitacion-password" name="password" autocomplete="new-password" minlength="14" required>
                        </div>
                        <div class="lead-form__field">
                            <label class="lead-form__label" for="invitacion-password-confirmacion">Confirmar contraseña</label>
                            <input class="lead-form__input" type="password" id="invitacion-password-confirmacion" name="password_confirmacion" autocomplete="new-password" minlength="14" required>
                        </div>
                        <p class="auth-page__hint">Mínimo 14 caracteres, con mayúscula, minúscula, número y símbolo.</p>
                        <button type="submit" class="btn btn--primary lead-form__submit">Activar mi cuenta</button>
                        <p id="invitacion-status" class="lead-form__status" role="status" aria-live="polite"></p>
                    </form>
                <?php else: ?>
                    <div class="lead-form">
                        <h1 class="auth-page__title">Enlace no válido</h1>
                        <p class="auth-page__lead">Este enlace de invitación no es válido. Solicita uno nuevo al administrador.</p>
                    </div>
                <?php endif; ?>

                <div id="auth-error-container" class="auth-error-container" hidden role="alert">
                    <p id="auth-error-message"></p>
                    <button type="button" id="auth-retry-btn" class="btn btn--primary">Reintentar</button>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/main.js" defer></script>
</body>
</html>
