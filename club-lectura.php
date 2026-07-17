<?php

declare(strict_types=1);

require_once __DIR__ . '/api/auth_helpers.php';
require_once __DIR__ . '/api/conexion.php';

const PAGINA_ID = 'club-lectura';

$pdo = (new Database())->getConnection();

// Overrides guardados por el Motor de Edición Visual (MODULO_02) — sin fila,
// la página sigue mostrando su contenido original hardcodeado (fallback).
$bloques = [];
try {
    $stmt = $pdo->prepare('SELECT bloque_id, tipo, contenido, imagen_path FROM configuracion_layout WHERE pagina = :pagina');
    $stmt->execute([':pagina' => PAGINA_ID]);
    foreach ($stmt->fetchAll() as $fila) {
        $bloques[$fila['bloque_id']] = $fila;
    }
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] club-lectura.php layout: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
}

function bloqueTexto(array $bloques, string $id, string $default): string
{
    $valor = $bloques[$id]['contenido'] ?? $default;

    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function bloqueImagenSrc(array $bloques, string $id, string $default): string
{
    $valor = $bloques[$id]['imagen_path'] ?? $default;

    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// Modo edición (MODULO_02_CMS_EDICION_VISUAL §2.1/§3.2) — activado
// server-side únicamente: cookie de sesión válida + rol autorizado +
// Mapeo Dinámico de Permisos. Nunca por un parámetro de URL sin verificar.
$modoEdicion = false;
$usuarioEditor = null;

if (isset($_GET['modo_edicion']) && $_GET['modo_edicion'] === '1') {
    $token = $_COOKIE['token_acceso'] ?? '';

    if ($token !== '') {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, rol, estatus, token_expira_en, device_hash FROM usuarios WHERE token_acceso = :token LIMIT 1'
            );
            $stmt->execute([':token' => $token]);
            $fila = $stmt->fetch();

            $sesionValida = $fila !== false
                && $fila['estatus'] === 'activo'
                && $fila['token_expira_en'] !== null
                && strtotime((string) $fila['token_expira_en']) > time()
                && hash_equals((string) $fila['device_hash'], calcularDeviceHash());

            $puedeEditar = $sesionValida && (
                $fila['rol'] === 'super_admin' || esModuloVisible($pdo, 'landing', (string) $fila['rol'])
            );

            if ($puedeEditar) {
                $modoEdicion = true;
                $usuarioEditor = $fila;
            }
        } catch (PDOException $e) {
            error_log('[' . date('Y-m-d H:i:s') . '] club-lectura.php modo_edicion: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/logs/error.log');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club de Lectura | El Efecto Ajedrez: Mentores al Revés</title>
    <meta name="description" content="Únete al Club de Lectura de Pao Palomares: martes y jueves 8:30 p.m. Un espacio para desconectar, crecer y compartir en comunidad.">
    <?php if (!$modoEdicion): ?>
    <link rel="canonical" href="https://efecto-ajedrez.tourfindy.com/club-lectura.php">
    <?php else: ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="El Efecto Ajedrez: Mentores al Revés">
    <meta property="og:title" content="Club de Lectura | El Efecto Ajedrez: Mentores al Revés">
    <meta property="og:description" content="Únete al Club de Lectura de Pao Palomares: martes y jueves 8:30 p.m. Un espacio para desconectar, crecer y compartir en comunidad.">
    <meta property="og:image" content="https://efecto-ajedrez.tourfindy.com/assets/img/logo.png">
    <meta property="og:locale" content="es_MX">

    <link rel="icon" href="favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&family=Open+Sans:wght@400;600&display=swap">

    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body<?php echo $modoEdicion ? ' data-edit-mode data-edit-pagina="' . PAGINA_ID . '"' : ''; ?>>

    <?php if ($modoEdicion): ?>
    <div class="edit-banner" role="alert">
        <p class="edit-banner__title">IMPORTANTE: PÁGINA PARA EDITAR</p>
        <p class="edit-banner__hint">Si deseas cambiar un texto o una imagen, simplemente haz clic sobre el elemento que quieres modificar.</p>
    </div>
    <?php endif; ?>

    <header class="site-header">
        <div class="container site-header__bar">
            <a href="index.php" class="site-header__brand">
                <img src="assets/img/logo.png" alt="El Efecto Ajedrez: Mentores al Revés" class="site-header__logo">
                <span class="site-header__brand-name">El Efecto Ajedrez</span>
            </a>
            <a href="index.php" class="site-header__back-btn">← Volver al Inicio</a>
        </div>
    </header>

    <main>
        <section class="hero" aria-labelledby="club-hero-title">
            <div class="container">
                <img src="assets/img/logo1.png" alt="El Efecto Ajedrez: Mentores al Revés" class="hero__wordmark">
                <span class="hero__eyebrow">Club de Lectura · Comunidad Tzunum</span>
                <h1 id="club-hero-title" class="hero__title" data-block-id="hero_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'hero_titulo', '📚 ¡Desconecta, Crece y Comparte! Únete a nuestro Club de Lectura ✨'); ?></h1>

                <div class="club-host-card">
                    <img src="<?php echo bloqueImagenSrc($bloques, 'host_foto', 'assets/img/PaoPalomares.jpeg'); ?>" alt="Paola Palomares — Anfitriona del Club de Lectura" class="club-host-card__photo" id="club-host-photo" tabindex="0" role="button" aria-label="Ampliar fotografía de Paola Palomares" data-block-id="host_foto" data-block-type="imagen">
                    <div class="club-host-card__info">
                        <span class="club-host-card__eyebrow">Anfitriona Estelar</span>
                        <p class="club-host-card__name">Paola Palomares</p>
                        <p class="club-host-card__role">Crianza Positiva · Educación sin Violencia</p>
                    </div>
                </div>

                <p class="hero__subtitle" data-block-id="hero_subtitulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'hero_subtitulo', 'Si tienes más de 35 años y buscas un espacio para relajarte después de un largo día, inspirarte y conocer a personas increíbles, ¡esta invitación es para ti! Estamos armando un nuevo Club de Lectura junto a Pao Palomares y queremos que formes parte de él. Más que un grupo tradicional, hemos creado este espacio con un propósito muy claro: disfrutar de una lectura alegre, fomentar el trabajo en equipo y apoyarnos mutuamente en nuestra mejora personal. Queremos que cada historia que leamos sea un motor para crecer juntos y cerrar el día con la mejor energía — moviendo nuestras piezas con estrategia, un paso a la vez, "Colibrí siempre colibrí".'); ?></p>
            </div>
        </section>

        <section id="detalles" class="section section--alt" aria-labelledby="detalles-title">
            <div class="container">
                <div class="section__heading">
                    <h2 id="detalles-title" class="section__title" data-block-id="detalles_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalles_titulo', '📌 Detalles de nuestras reuniones'); ?></h2>
                    <p class="section__lead" data-block-id="detalles_lead" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalles_lead', 'El acceso a la sesión en vivo se habilita automáticamente 15 minutos antes de comenzar.'); ?></p>
                </div>
                <div class="club-details">
                    <ul class="club-details__list">
                        <li class="club-details__item">
                            <span class="club-details__label">Días</span>
                            <span class="club-details__value" data-block-id="detalle_dias" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalle_dias', 'Todos los martes y jueves'); ?></span>
                        </li>
                        <li class="club-details__item">
                            <span class="club-details__label">🇲🇽 México (CDMX)</span>
                            <span class="club-details__value" data-block-id="detalle_horario_mx" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalle_horario_mx', '8:30 p.m. – 9:30 p.m.'); ?></span>
                        </li>
                        <li class="club-details__item">
                            <span class="club-details__label">🇵🇪 Perú</span>
                            <span class="club-details__value" data-block-id="detalle_horario_pe" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalle_horario_pe', '9:30 p.m. – 10:30 p.m.'); ?></span>
                        </li>
                        <li class="club-details__item">
                            <span class="club-details__label">🇦🇷 Argentina</span>
                            <span class="club-details__value" data-block-id="detalle_horario_ar" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalle_horario_ar', '11:30 p.m. – 12:30 a.m.'); ?></span>
                        </li>
                        <li class="club-details__item">
                            <span class="club-details__label">Enlace/Link</span>
                            <span class="club-details__value" data-block-id="detalle_enlace" data-block-type="texto"><?php echo bloqueTexto($bloques, 'detalle_enlace', 'Google Meet — el acceso aparece aquí 15 min antes'); ?></span>
                        </li>
                    </ul>
                    <div id="club-access" class="club-access">
                        <p class="club-access__badge" id="club-access-badge">
                            <span class="live-block__dot" aria-hidden="true"></span>
                            Próxima sesión en vivo
                        </p>
                    </div>
                </div>
                <div class="club-cta-wrap">
                    <button type="button" class="btn btn--primary btn-quiero-unirme">Quiero unirme al Club de Lectura</button>
                </div>
            </div>
        </section>

        <section id="beneficios" class="section" aria-labelledby="beneficios-title">
            <div class="container">
                <div class="section__heading">
                    <h2 id="beneficios-title" class="section__title" data-block-id="beneficios_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficios_titulo', '¿Qué puedes esperar de este grupo?'); ?></h2>
                </div>
                <div class="arf-grid club-benefits">
                    <article class="arf-grid__item club-benefit-card">
                        <span class="club-benefit-card__icon" aria-hidden="true">🌟</span>
                        <h3 class="club-benefit-card__title" data-block-id="beneficio_1_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficio_1_titulo', 'Un ambiente positivo'); ?></h3>
                        <p class="club-benefit-card__text" data-block-id="beneficio_1_texto" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficio_1_texto', 'Cero presiones, cien por ciento disfrute. Elegiremos libros optimistas, inspiradores y enriquecedores.'); ?></p>
                    </article>
                    <article class="arf-grid__item club-benefit-card">
                        <span class="club-benefit-card__icon" aria-hidden="true">🤝</span>
                        <h3 class="club-benefit-card__title" data-block-id="beneficio_2_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficio_2_titulo', 'Trabajo en equipo'); ?></h3>
                        <p class="club-benefit-card__text" data-block-id="beneficio_2_texto" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficio_2_texto', 'Aquí todas las opiniones cuentan. Aprenderemos de las perspectivas de los demás en un entorno de respeto y camaradería.'); ?></p>
                    </article>
                    <article class="arf-grid__item club-benefit-card">
                        <span class="club-benefit-card__icon" aria-hidden="true">🚀</span>
                        <h3 class="club-benefit-card__title" data-block-id="beneficio_3_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficio_3_titulo', 'Crecimiento personal'); ?></h3>
                        <p class="club-benefit-card__text" data-block-id="beneficio_3_texto" data-block-type="texto"><?php echo bloqueTexto($bloques, 'beneficio_3_texto', 'Cada lectura y cada charla estarán enfocadas en sumar valor a nuestras vidas y ayudarnos a ser nuestra mejor versión.'); ?></p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section section--alt" aria-labelledby="cierre-title">
            <div class="container">
                <div class="section__heading">
                    <h2 id="cierre-title" class="section__title" data-block-id="cierre_titulo" data-block-type="texto"><?php echo bloqueTexto($bloques, 'cierre_titulo', '¡Únete a la aventura!'); ?></h2>
                    <p class="section__lead" data-block-id="cierre_lead" data-block-type="texto"><?php echo bloqueTexto($bloques, 'cierre_lead', 'No necesitas ser un lector experto, solo tener ganas de compartir un buen rato, aprender y reír en equipo!'); ?></p>
                </div>
                <div class="club-cta-wrap">
                    <button type="button" class="btn btn--primary btn-quiero-unirme">Quiero unirme al Club de Lectura</button>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p class="site-footer__philosophy">"Colibrí siempre colibrí"</p>
            <p>&copy; 2026 El Efecto Ajedrez: Mentores al Revés — Paola Palomares.</p>
        </div>
    </footer>

    <div id="club-modal" class="club-modal" aria-hidden="true">
        <div class="club-modal__backdrop" data-club-modal-close></div>
        <div class="club-modal__panel" role="dialog" aria-modal="true" aria-labelledby="club-modal-title">
            <button type="button" class="club-modal__close" data-club-modal-close aria-label="Cerrar">&times;</button>
            <h2 id="club-modal-title" class="club-modal__title">¡Ya casi comenzamos! 📚</h2>
            <p class="club-modal__text">Bienvenido(a) al Club de Lectura. Este es tu espacio para desconectar, mover tus piezas con estrategia y crecer en comunidad junto a Pao. Cuando estés listo(a), entra a la sesión.</p>
            <button type="button" id="club-modal-enter" class="btn btn--primary club-modal__enter">Ingresar a la Sesión</button>
        </div>
    </div>

    <div id="lead-capture-modal" class="club-modal lead-capture-modal" aria-hidden="true">
        <div class="club-modal__backdrop" data-lead-modal-close></div>
        <div class="club-modal__panel" role="dialog" aria-modal="true" aria-label="Formulario de registro al Club de Lectura">
            <button type="button" class="club-modal__close" data-lead-modal-close aria-label="Cerrar">&times;</button>
            <form id="registro-interesado-form" class="lead-form" novalidate>
                <div class="lead-form__field">
                    <label class="lead-form__label" for="interesado-nombre">Nombre</label>
                    <input class="lead-form__input" type="text" id="interesado-nombre" name="nombre" autocomplete="name" pattern="^(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñ])[A-Za-zÁÉÍÓÚáéíóúÑñ\s'.-]{2,120}$" title="Escribe tu nombre real (solo letras)" required>
                </div>
                <div class="lead-form__field">
                    <label class="lead-form__label" for="interesado-email">Correo electrónico</label>
                    <input class="lead-form__input" type="email" id="interesado-email" name="email" autocomplete="email" pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$" required>
                </div>
                <div class="lead-form__field">
                    <label class="lead-form__label" for="interesado-edad">Edad</label>
                    <input class="lead-form__input" type="number" id="interesado-edad" name="edad" min="1" max="120" step="1" required>
                </div>
                <p class="lead-form__microcopy">Se parte de este gran grupo.</p>
                <button type="submit" class="btn btn--primary lead-form__submit">Enviar</button>
                <p id="registro-interesado-status" class="lead-form__status" role="status" aria-live="polite"></p>
            </form>
        </div>
    </div>

    <div class="club-photo-backdrop" id="club-photo-backdrop" data-photo-backdrop></div>

    <button type="button" id="btn-scroll-top" class="club-scroll-top" aria-label="Volver arriba">↑</button>

    <?php if ($modoEdicion): ?>
    <input type="file" id="edit-image-input" accept="image/webp,image/png,image/jpeg" hidden>
    <footer class="edit-footer">
        <a href="club-lectura.php" target="_blank" rel="noopener" class="btn btn--primary">Ver en vivo</a>
        <button type="button" id="edit-cerrar-btn" class="btn btn--primary edit-footer__cerrar">Cerrar</button>
    </footer>
    <?php endif; ?>

    <script src="assets/js/main.js" defer></script>
</body>
</html>
