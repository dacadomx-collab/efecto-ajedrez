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

$esSuperAdmin = $usuarioActual['rol'] === 'super_admin';

$politicaActiva = 'media';
$duracionRecordarmeActiva = 60;
$verUsuarios = $esSuperAdmin || esModuloVisible($pdo, 'usuarios', (string) $usuarioActual['rol']);
$verSeguridad = $esSuperAdmin || esModuloVisible($pdo, 'seguridad', (string) $usuarioActual['rol']);
$verLanding = $esSuperAdmin || esModuloVisible($pdo, 'landing', (string) $usuarioActual['rol']);
$verInvitados = $esSuperAdmin || esModuloVisible($pdo, 'invitados', (string) $usuarioActual['rol']);

$todosLosUsuarios = [];
if ($esSuperAdmin) {
    try {
        $todosLosUsuarios = $pdo->query(
            'SELECT id, nombre, email, rol, estatus FROM usuarios ORDER BY nombre'
        )->fetchAll();
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] dashboard.php usuarios: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
    }
}
// El Registro de Ingreso ya no se pre-renderiza server-side — el widget de
// paginación/búsqueda/borrado masivo (Sección "Reestructuración Dashboard")
// lo carga vía api/registro_ingreso_listar.php.

$interesados = [];
$sesiones = [];
$totalUsuariosSistema = 0;
if ($verInvitados) {
    try {
        $interesados = $pdo->query(
            'SELECT nombre, email, edad, ciudad, estado, ip, ip_pais, ip_estado, ip_ciudad, created_at
             FROM registro_interesados ORDER BY created_at DESC'
        )->fetchAll();

        $sesiones = $pdo->query(
            'SELECT hs.id, hs.fecha_hora, hs.tema,
                    COUNT(hsa.id) AS notificados,
                    SUM(CASE WHEN hsa.checkin_en IS NOT NULL THEN 1 ELSE 0 END) AS asistieron
             FROM historial_sesiones hs
             LEFT JOIN historial_sesiones_asistentes hsa ON hsa.sesion_id = hs.id
             GROUP BY hs.id
             ORDER BY hs.fecha_hora DESC
             LIMIT 10'
        )->fetchAll();

        // Métrica de la tarjeta KPI "Total de usuarios" (Sección B) — un
        // conteo simple, no expone datos sensibles, seguro para cualquier
        // rol que ya tenga visibilidad del módulo "invitados".
        $totalUsuariosSistema = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] dashboard.php invitados: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
    }
}

// Sesión "activa" del Círculo de Lectura para las tarjetas KPI de la
// Sección B — la más reciente compartida (misma consulta ya ordenada DESC).
$sesionActiva = $sesiones[0] ?? null;

if ($esSuperAdmin || $verSeguridad) {
    try {
        $configSeguridad = obtenerConfiguracionSeguridad($pdo);
        $politicaActiva = $configSeguridad['politica_password'];
        $duracionRecordarmeActiva = $configSeguridad['duracion_recordarme_dias'];
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] dashboard.php politica: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
    }
}

// Matriz de permisos actual — solo se lee/renderiza para super_admin
// (panel de control exclusivo, MODULO_01_LOGIN_Y_ACCESO §6.1).
$matrizPermisos = ['usuarios' => true, 'seguridad' => false, 'landing' => true, 'invitados' => true];
if ($esSuperAdmin) {
    try {
        foreach (array_keys($matrizPermisos) as $moduloId) {
            $matrizPermisos[$moduloId] = esModuloVisible($pdo, $moduloId, 'admin');
        }
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] dashboard.php permisos: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
    }
}

// Roles que el usuario actual puede otorgar al dar de alta a alguien más
// (MODULO_01_LOGIN_Y_ACCESO §6) — el backend vuelve a recortar esto de forma
// independiente en api/usuarios_crear.php y api/usuarios_invitar.php.
$rolesAsignables = $esSuperAdmin ? ['admin', 'super_admin'] : ['admin'];
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
            <a href="dashboard.php" class="dash-topbar__brand">
                <img src="assets/img/logo3-removebg-preview.png" alt="El Efecto Ajedrez: Mentores al Revés" class="dash-topbar__logo">
            </a>
            <button type="button" id="theme-toggle-btn" class="dash-topbar__theme-toggle" data-theme-toggle aria-label="Cambiar entre modo claro y oscuro">🌙</button>
            <button type="button" id="dash-logout-btn" class="dash-topbar__logout">Salir</button>
        </header>

        <nav class="dash-nav" id="dash-nav" data-dash-nav aria-hidden="true">
            <p class="dash-nav__user"><?php echo htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($usuarioActual['rol'], ENT_QUOTES, 'UTF-8'); ?></p>
            <ul class="dash-nav__list">
                <li><a href="dashboard.php" class="dash-nav__link" data-panel-target="">Inicio</a></li>
            </ul>

            <?php if ($verUsuarios): ?>
            <div class="dash-accordion" data-accordion-group>
                <button type="button" class="dash-accordion__trigger" data-accordion-trigger data-panel-target="usuarios" aria-expanded="false">Gestión de Accesos</button>
                <ul class="dash-accordion__panel" data-accordion-panel hidden>
                    <li><a href="#metodo-directo" class="dash-nav__link" data-panel-target="usuarios">Usuarios</a></li>
                    <li><a href="#metodo-invitacion" class="dash-nav__link" data-panel-target="usuarios">Invitación Segura</a></li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($verLanding || $verInvitados): ?>
            <div class="dash-accordion" data-accordion-group>
                <button type="button" class="dash-accordion__trigger" data-accordion-trigger data-panel-target="landing,invitados" aria-expanded="false">Círculo de Lectura</button>
                <ul class="dash-accordion__panel" data-accordion-panel hidden>
                    <?php if ($verLanding): ?><li><a href="#landing" class="dash-nav__link" data-panel-target="landing,invitados">Edición de página</a></li><?php endif; ?>
                    <?php if ($verInvitados): ?><li><a href="#invitados" class="dash-nav__link" data-panel-target="landing,invitados">Invitados Confirmados</a></li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <ul class="dash-nav__list">
                <?php if ($verSeguridad): ?><li><a href="#seguridad" class="dash-nav__link" data-panel-target="seguridad">Seguridad</a></li><?php endif; ?>
                <?php if ($esSuperAdmin): ?><li><a href="#permisos" class="dash-nav__link" data-panel-target="permisos">Permisos</a></li><?php endif; ?>
                <li><a href="index.php" class="dash-nav__link">Ver sitio público</a></li>
            </ul>
        </nav>

        <div class="dash-nav-backdrop" data-dash-nav-close></div>

        <main class="dash-content">
            <div class="container">
                <section class="aura-welcome" id="aura-welcome" data-aura-welcome>
                    <p class="aura-welcome__saludo" id="aura-saludo">Bienvenido(a), <?php echo htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="aura-welcome__rol">Rol: <?php echo htmlspecialchars($usuarioActual['rol'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="aura-welcome__ubicacion" id="aura-ubicacion" hidden></p>
                    <p class="aura-welcome__frase" id="aura-frase">Cargando tu cápsula del día…</p>
                </section>

                <?php if ($verUsuarios): ?>
                <section id="usuarios" class="dash-panel" hidden>
                    <h2 class="dash-panel__title">Alta de usuarios</h2>
                    <p class="dash-panel__intro">Aquí puedes dar de acceso al Dashboard a las personas que colaboran contigo. Elige el método según lo que te resulte más cómodo.</p>

                    <div class="dash-panel__grid">
                        <form id="usuario-crear-form" class="lead-form" novalidate>
                            <h3 id="metodo-directo" class="auth-page__title">Método A — Creación Directa</h3>
                            <p class="dash-panel__hint">Usa esto cuando ya conoces a la persona y quieres darle acceso de inmediato — tú defines su contraseña inicial.</p>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-nombre">Nombre</label>
                                <input class="lead-form__input" type="text" id="crear-nombre" name="nombre" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-email">Correo electrónico</label>
                                <input class="lead-form__input" type="email" id="crear-email" name="email" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-rol">Rol</label>
                                <select class="lead-form__input" id="crear-rol" name="rol">
                                    <?php foreach ($rolesAsignables as $rolOpcion): ?>
                                        <option value="<?php echo htmlspecialchars($rolOpcion, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rolOpcion, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="crear-password">Contraseña</label>
                                <div class="password-field">
                                    <input class="lead-form__input" type="password" id="crear-password" name="password" autocomplete="new-password" required>
                                    <button type="button" class="password-field__toggle" data-password-toggle="crear-password" aria-label="Mostrar contraseña" aria-pressed="false">👁</button>
                                </div>
                                <div class="password-strength" data-password-strength-for="crear-password">
                                    <div class="password-strength__track">
                                        <div class="password-strength__fill" data-password-strength-fill></div>
                                    </div>
                                    <p class="password-strength__label" data-password-strength-label></p>
                                </div>
                            </div>
                            <button type="submit" class="btn btn--primary lead-form__submit">Crear usuario activo</button>
                            <p id="usuario-crear-status" class="lead-form__status" role="status" aria-live="polite"></p>
                        </form>

                        <form id="usuario-invitar-form" class="lead-form" novalidate>
                            <h3 id="metodo-invitacion" class="auth-page__title">Método B — Invitación Segura</h3>
                            <p class="dash-panel__hint">Usa esto cuando prefieras que la persona elija su propia contraseña de forma segura — le llega un correo con un enlace único.</p>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="invitar-nombre">Nombre</label>
                                <input class="lead-form__input" type="text" id="invitar-nombre" name="nombre" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="invitar-email">Correo electrónico</label>
                                <input class="lead-form__input" type="email" id="invitar-email" name="email" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="invitar-rol">Rol</label>
                                <select class="lead-form__input" id="invitar-rol" name="rol">
                                    <?php foreach ($rolesAsignables as $rolOpcion): ?>
                                        <option value="<?php echo htmlspecialchars($rolOpcion, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rolOpcion, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn--primary lead-form__submit">Enviar invitación</button>
                            <p id="usuario-invitar-status" class="lead-form__status" role="status" aria-live="polite"></p>
                        </form>
                    </div>

                    <?php if ($esSuperAdmin): ?>
                    <h3 class="auth-page__title dash-panel__subtitle">Controlador Central de Usuarios</h3>
                    <p class="dash-panel__hint">Resetear contraseña, suspender o eliminar cualquier cuenta del sistema.</p>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estatus</th><th>Acciones</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($todosLosUsuarios)): ?>
                                    <tr><td colspan="5">No hay usuarios registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($todosLosUsuarios as $u): ?>
                                    <tr data-usuario-row data-usuario-id="<?php echo (int) $u['id']; ?>">
                                        <td><?php echo htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="dash-badge dash-badge--<?php echo htmlspecialchars($u['estatus'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($u['estatus'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td class="dash-table__actions">
                                            <button type="button" class="dash-table__action-btn" data-accion-usuario="resetear" data-usuario-id="<?php echo (int) $u['id']; ?>">Resetear contraseña</button>
                                            <button type="button" class="dash-table__action-btn" data-accion-usuario="suspender" data-usuario-id="<?php echo (int) $u['id']; ?>">Suspender</button>
                                            <button type="button" class="dash-table__action-btn dash-table__action-btn--peligro" data-accion-usuario="eliminar" data-usuario-id="<?php echo (int) $u['id']; ?>">Eliminar</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p id="usuarios-control-status" class="lead-form__status" role="status" aria-live="polite"></p>

                    <h3 class="auth-page__title dash-panel__subtitle">Staging de Correo</h3>
                    <p class="dash-panel__hint">Envía la plantilla real de invitación al correo de auditoría, sin crear ningún usuario.</p>
                    <button type="button" id="btn-staging-test-invitacion" class="btn btn--primary">Enviar Correo de Prueba a Staging</button>
                    <p id="staging-test-invitacion-status" class="lead-form__status" role="status" aria-live="polite"></p>

                    <h3 class="auth-page__title dash-panel__subtitle">Registro de Ingreso</h3>
                    <p class="dash-panel__hint">Auditoría de accesos exitosos al Dashboard — quién, cuándo, desde dónde. Muestra 15 registros a la vez.</p>
                    <div class="dash-ledger-toolbar">
                        <input type="search" id="registro-ingreso-buscar" class="lead-form__input dash-ledger-toolbar__search" placeholder="Buscar por usuario o ubicación...">
                        <div class="dash-ledger-toolbar__bulk">
                            <button type="button" class="dash-table__action-btn" data-purgar-ingreso="10">Borrar 10 antiguos</button>
                            <button type="button" class="dash-table__action-btn" data-purgar-ingreso="15">Borrar 15 antiguos</button>
                            <button type="button" class="dash-table__action-btn dash-table__action-btn--peligro" data-purgar-ingreso="todos">Borrar todos</button>
                            <button type="button" class="dash-table__action-btn dash-table__action-btn--peligro" id="registro-ingreso-borrar-seleccion" disabled>Borrar seleccionados</button>
                        </div>
                    </div>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="registro-ingreso-seleccionar-todos" aria-label="Seleccionar todos los registros visibles"></th>
                                    <th>Usuario</th><th>Correo</th><th>IP</th><th>Ubicación</th><th>Fecha y hora</th>
                                </tr>
                            </thead>
                            <tbody id="registro-ingreso-tbody">
                                <tr><td colspan="6">Cargando…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="dash-pagination" id="registro-ingreso-paginacion"></div>
                    <p id="registro-ingreso-status" class="lead-form__status" role="status" aria-live="polite"></p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <?php if ($verLanding): ?>
                <section id="landing" class="dash-panel" hidden>
                    <h2 class="dash-panel__title">Edición de página</h2>
                    <p class="dash-panel__intro">Edita los textos y la fotografía de la página pública tal como la ve tu audiencia — haz clic directo sobre cada elemento para cambiarlo.</p>
                    <a href="club-lectura.php?modo_edicion=1" class="btn btn--primary">Editar página</a>
                </section>
                <?php endif; ?>

                <?php if ($verInvitados): ?>
                <section id="invitados" class="dash-panel" hidden>
                    <h2 class="dash-panel__title">Invitados Confirmados</h2>

                    <!-- SECCIÓN A — Planeador Live: la acción más usada del día a día,
                         siempre lo primero que ve el operador al entrar a este panel. -->
                    <div class="dash-widget dash-widget--planeador">
                        <h3 class="dash-widget__title">🚀 Planeador de la Próxima Sesión</h3>
                        <p class="dash-panel__hint">Un solo lugar para preparar la próxima sesión: enlace, fecha, material y mensaje — se notifica por correo a todos los interesados registrados al presionar "Compartir".</p>
                        <form id="planeador-live-form" class="lead-form" novalidate>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="sesion-enlace">Enlace de Google Meet / Zoom</label>
                                <input class="lead-form__input" type="url" id="sesion-enlace" name="enlace" placeholder="https://meet.google.com/xxx-xxxx-xxx" required>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="sesion-fecha">Fecha y hora programada</label>
                                <input class="lead-form__input" type="datetime-local" id="sesion-fecha" name="fecha_hora">
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="sesion-tema">Tema del libro / Mensaje rápido (opcional)</label>
                                <input class="lead-form__input" type="text" id="sesion-tema" name="tema" placeholder="Título del libro o tema de la sesión">
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="sesion-mensaje">Mensaje personalizado para el correo (opcional)</label>
                                <textarea class="lead-form__input" id="sesion-mensaje" name="mensaje" rows="3" maxlength="500" placeholder="Unas palabras para tus lectores antes de la sesión..."></textarea>
                            </div>
                            <div class="lead-form__field">
                                <label class="lead-form__label" for="sesion-archivo">Libro del club (PDF, opcional, máx. 20MB)</label>
                                <input class="lead-form__input" type="file" id="sesion-archivo" name="material" accept="application/pdf">
                            </div>
                            <button type="submit" class="btn btn--primary lead-form__submit">Compartir</button>
                            <p id="planeador-live-status" class="lead-form__status" role="status" aria-live="polite"></p>
                        </form>
                    </div>

                    <!-- SECCIÓN B — Métricas activas en tarjetas (KPIs de un vistazo). -->
                    <h3 class="auth-page__title dash-panel__subtitle">De un vistazo</h3>
                    <div class="dash-panel__grid dash-kpi-grid">
                        <div class="arf-grid__item dash-kpi-card">
                            <span class="dash-kpi-card__label">Fecha programada</span>
                            <span class="dash-kpi-card__value"><?php echo $sesionActiva !== null ? htmlspecialchars((string) $sesionActiva['fecha_hora'], ENT_QUOTES, 'UTF-8') : 'Sin sesión activa'; ?></span>
                        </div>
                        <div class="arf-grid__item dash-kpi-card">
                            <span class="dash-kpi-card__label">Libro activo</span>
                            <span class="dash-kpi-card__value"><?php echo $sesionActiva !== null ? htmlspecialchars((string) ($sesionActiva['tema'] ?? 'Sin tema'), ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                        </div>
                        <div class="arf-grid__item dash-kpi-card">
                            <span class="dash-kpi-card__label">Participantes confirmados</span>
                            <span class="dash-kpi-card__value"><?php echo count($interesados); ?></span>
                        </div>
                        <div class="arf-grid__item dash-kpi-card">
                            <span class="dash-kpi-card__label">Total de usuarios del sistema</span>
                            <span class="dash-kpi-card__value"><?php echo $totalUsuariosSistema; ?></span>
                        </div>
                    </div>

                    <!-- SECCIÓN C — Historial de sesiones (ledger compacto, al final). -->
                    <h3 class="auth-page__title dash-panel__subtitle">Historial de sesiones</h3>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr><th>Fecha</th><th>Tema</th><th>Notificados</th><th>Asistieron</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sesiones)): ?>
                                    <tr><td colspan="4">Aún no se ha compartido ninguna sesión.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($sesiones as $sesion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $sesion['fecha_hora'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($sesion['tema'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $sesion['notificados']; ?></td>
                                        <td><?php echo (int) $sesion['asistieron']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="auth-page__title dash-panel__subtitle">Interesados registrados (<?php echo count($interesados); ?>)</h3>
                    <p class="dash-panel__hint">IP y ubicación se conservan en la base de datos para auditoría interna — no se muestran aquí para aligerar la tabla en pantallas pequeñas.</p>
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr><th>Nombre</th><th>Correo</th><th>Edad</th><th>Ciudad</th><th>Estado</th><th>Registrado</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($interesados)): ?>
                                    <tr><td colspan="6">Aún no hay interesados registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($interesados as $persona): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($persona['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($persona['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $persona['edad'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($persona['ciudad'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($persona['estado'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $persona['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($verSeguridad): ?>
                <section id="seguridad" class="dash-panel" hidden>
                    <h2 class="dash-panel__title">Política de Seguridad de Contraseñas</h2>
                    <p class="section__lead dash-content__rol">Perfil activo: <strong id="politica-activa-actual"><?php echo htmlspecialchars($politicaActiva, ENT_QUOTES, 'UTF-8'); ?></strong></p>

                    <form id="politica-seguridad-form" novalidate>
                        <div class="dash-panel__grid">
                            <label class="arf-grid__item policy-card">
                                <input type="radio" name="perfil" value="simple" class="policy-card__radio" <?php echo $politicaActiva === 'simple' ? 'checked' : ''; ?>>
                                <span class="policy-card__title">1. Sencilla</span>
                                <span class="policy-card__text">Mínimo 6 caracteres, cualquier tipo.</span>
                            </label>
                            <label class="arf-grid__item policy-card">
                                <input type="radio" name="perfil" value="media" class="policy-card__radio" <?php echo $politicaActiva === 'media' ? 'checked' : ''; ?>>
                                <span class="policy-card__title">2. Mediana</span>
                                <span class="policy-card__text">Mínimo 8 caracteres, letras y números.</span>
                            </label>
                            <label class="arf-grid__item policy-card">
                                <input type="radio" name="perfil" value="fuerte" class="policy-card__radio" <?php echo $politicaActiva === 'fuerte' ? 'checked' : ''; ?>>
                                <span class="policy-card__title">3. Fuerte</span>
                                <span class="policy-card__text">Mínimo 14 caracteres, mayúscula, minúscula, número y símbolo.</span>
                            </label>
                        </div>

                        <h3 class="auth-page__title dash-panel__subtitle">Duración de "Mantenerme registrado"</h3>
                        <div class="dash-panel__grid">
                            <label class="arf-grid__item policy-card">
                                <input type="radio" name="duracion_recordarme_dias" value="60" class="policy-card__radio" <?php echo $duracionRecordarmeActiva === 60 ? 'checked' : ''; ?>>
                                <span class="policy-card__title">60 días</span>
                                <span class="policy-card__text">2 meses.</span>
                            </label>
                            <label class="arf-grid__item policy-card">
                                <input type="radio" name="duracion_recordarme_dias" value="120" class="policy-card__radio" <?php echo $duracionRecordarmeActiva === 120 ? 'checked' : ''; ?>>
                                <span class="policy-card__title">120 días</span>
                                <span class="policy-card__text">4 meses.</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn--primary">Aplicar política</button>
                        <p id="politica-seguridad-status" class="lead-form__status" role="status" aria-live="polite"></p>
                    </form>
                </section>
                <?php endif; ?>

                <?php if ($esSuperAdmin): ?>
                <section id="permisos" class="dash-panel" hidden>
                    <h2 class="dash-panel__title">Mapeo de Permisos por Módulo</h2>
                    <p class="dash-panel__intro">Como super_admin siempre ves todo el sistema. Aquí decides qué módulos también puede ver el rol "admin".</p>

                    <div class="dash-panel__grid">
                        <label class="arf-grid__item policy-card" data-permiso-card>
                            <input type="checkbox" class="policy-card__radio" data-permiso-toggle data-modulo="usuarios" <?php echo $matrizPermisos['usuarios'] ? 'checked' : ''; ?>>
                            <span class="policy-card__title">Usuarios</span>
                            <span class="policy-card__text">Alta de usuarios (Métodos A y B) visible para "admin".</span>
                        </label>
                        <label class="arf-grid__item policy-card" data-permiso-card>
                            <input type="checkbox" class="policy-card__radio" data-permiso-toggle data-modulo="seguridad" <?php echo $matrizPermisos['seguridad'] ? 'checked' : ''; ?>>
                            <span class="policy-card__title">Seguridad</span>
                            <span class="policy-card__text">Política de contraseñas y "recordarme" visible para "admin".</span>
                        </label>
                        <label class="arf-grid__item policy-card" data-permiso-card>
                            <input type="checkbox" class="policy-card__radio" data-permiso-toggle data-modulo="landing" <?php echo $matrizPermisos['landing'] ? 'checked' : ''; ?>>
                            <span class="policy-card__title">Landing Page</span>
                            <span class="policy-card__text">Edición visual del Círculo de Lectura visible para "admin".</span>
                        </label>
                        <label class="arf-grid__item policy-card" data-permiso-card>
                            <input type="checkbox" class="policy-card__radio" data-permiso-toggle data-modulo="invitados" <?php echo $matrizPermisos['invitados'] ? 'checked' : ''; ?>>
                            <span class="policy-card__title">Invitados</span>
                            <span class="policy-card__text">Panel de Invitados Confirmados visible para "admin".</span>
                        </label>
                    </div>
                    <p id="permisos-modulos-status" class="lead-form__status" role="status" aria-live="polite"></p>
                </section>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <button type="button" id="btn-scroll-top" class="club-scroll-top" aria-label="Volver arriba">↑</button>

    <script src="assets/js/main.js" defer></script>
</body>
</html>
