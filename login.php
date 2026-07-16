<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Dashboard | El Efecto Ajedrez</title>
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

                <form id="login-form" class="lead-form" novalidate>
                    <h1 class="auth-page__title">Acceso al Dashboard</h1>
                    <div class="lead-form__field">
                        <label class="lead-form__label" for="login-email">Correo electrónico</label>
                        <input class="lead-form__input" type="email" id="login-email" name="email" autocomplete="username" required>
                    </div>
                    <div class="lead-form__field">
                        <label class="lead-form__label" for="login-password">Contraseña</label>
                        <input class="lead-form__input" type="password" id="login-password" name="password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn--primary lead-form__submit">Iniciar sesión</button>
                    <p id="login-status" class="lead-form__status" role="status" aria-live="polite"></p>
                </form>

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
