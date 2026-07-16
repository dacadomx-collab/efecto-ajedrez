<?php

declare(strict_types=1);

require_once __DIR__ . '/api/auth_helpers.php';
require_once __DIR__ . '/api/conexion.php';

// Guard server-side — evita renderizar el shell del Dashboard si la sesión
// no es válida (defensa en profundidad; los endpoints /api/* re-validan
// todo de nuevo vía requireAuth() en cada mutación).
$usuarioActual = null;
$token = $_COOKIE['token_acceso'] ?? '';

if ($token !== '') {
    try {
        $pdo = (new Database())->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, email, rol, estatus, token_expira_en, device_hash FROM usuarios WHERE token_acceso = :token LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $fila = $stmt->fetch();

        if ($fila !== false
            && $fila['estatus'] === 'activo'
            && $fila['token_expira_en'] !== null
            && strtotime((string) $fila['token_expira_en']) > time()
            && hash_equals((string) $fila['device_hash'], calcularDeviceHash())
        ) {
            $usuarioActual = $fila;
        }
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] dashboard.php: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
    }
}

if ($usuarioActual === null) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | El Efecto Ajedrez</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&family=Open+Sans:wght@400;600&display=swap">

    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="dash-body">
    <div class="dash-shell" data-dash-shell>
        <header class="dash-topbar">
            <button type="button" class="dash-topbar__burger" id="dash-burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="dash-nav">
                <span class="dash-topbar__burger-line"></span>
                <span class="dash-topbar__burger-line"></span>
                <span class="dash-topbar__burger-line"></span>
            </button>
            <a href="dashboard.php" class="dash-topbar__brand">El Efecto Ajedrez</a>
            <button type="button" id="dash-logout-btn" class="dash-topbar__logout">Salir</button>
        </header>

        <nav class="dash-nav" id="dash-nav" data-dash-nav aria-hidden="true">
            <p class="dash-nav__user"><?php echo htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($usuarioActual['rol'], ENT_QUOTES, 'UTF-8'); ?></p>
            <ul class="dash-nav__list">
                <li><a href="dashboard.php" class="dash-nav__link">Inicio</a></li>
                <li><a href="#usuarios" class="dash-nav__link">Usuarios</a></li>
                <li><a href="index.php" class="dash-nav__link">Ver sitio público</a></li>
            </ul>
        </nav>

        <div class="dash-nav-backdrop" data-dash-nav-close></div>

        <main class="dash-content">
            <div class="container">
                <h1 class="section__title">Bienvenido(a), <?php echo htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="section__lead dash-content__rol">Rol: <?php echo htmlspecialchars($usuarioActual['rol'], ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if ($usuarioActual['rol'] === 'super_admin' || $usuarioActual['rol'] === 'admin'): ?>
                <section id="usuarios" class="dash-panel">
                    <h2 class="dash-panel__title">Alta de usuarios</h2>

                    <div class="dash-panel__grid">
                        <form id="usuario-crear-form" class="lead-form" novalidate>
                            <h3 class="auth-page__title">Método A — Creación Directa</h3>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-nombre">Nombre</label>
                                <input class="lead-form__input" type="text" id="crear-nombre" name="nombre" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-email">Correo electrónico</label>
                                <input class="lead-form__input" type="email" id="crear-email" name="email" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-password">Contraseña</label>
                                <input class="lead-form__input" type="password" id="crear-password" name="password" minlength="14" required>
                            </div>
                            <button type="submit" class="btn btn--primary lead-form__submit">Crear usuario activo</button>
                            <p id="usuario-crear-status" class="lead-form__status" role="status" aria-live="polite"></p>
                        </form>

                        <form id="usuario-invitar-form" class="lead-form" novalidate>
                            <h3 class="auth-page__title">Método B — Invitación Segura</h3>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="invitar-nombre">Nombre</label>
                                <input class="lead-form__input" type="text" id="invitar-nombre" name="nombre" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="invitar-email">Correo electrónico</label>
                                <input class="lead-form__input" type="email" id="invitar-email" name="email" required>
                            </div>
                            <button type="submit" class="btn btn--primary lead-form__submit">Enviar invitación</button>
                            <p id="usuario-invitar-status" class="lead-form__status" role="status" aria-live="polite"></p>
                        </form>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js" defer></script>
</body>
</html>
