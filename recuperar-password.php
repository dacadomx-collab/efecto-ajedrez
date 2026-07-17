<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | El Efecto Ajedrez</title>
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

                <form id="recuperar-password-form" class="lead-form" novalidate>
                    <h1 class="auth-page__title">Recuperar contraseña</h1>
                    <p class="auth-page__lead">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>
                    <div class="lead-form__field">
                        <label class="lead-form__label" for="recuperar-email">Correo electrónico</label>
                        <input class="lead-form__input" type="email" id="recuperar-email" name="email" autocomplete="username" required>
                    </div>
                    <button type="submit" class="btn btn--primary lead-form__submit">Enviar enlace</button>
                    <p id="recuperar-password-status" class="lead-form__status" role="status" aria-live="polite"></p>
                </form>

                <p class="auth-page__link-row"><a href="login.php">Volver al acceso</a></p>

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
