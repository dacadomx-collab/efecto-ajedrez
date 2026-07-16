document.addEventListener('DOMContentLoaded', function () {
    initLeadForm();
    initClubLectura();
    initScrollTop();
    initClubHostPhoto();
    initDashNav();
    initAuthErrorRetry();
    initSetupGenesisForm();
    initLoginForm();
    initInvitacionForm();
    initUsuarioCrearForm();
    initUsuarioInvitarForm();
    initDashLogout();
});

function initLeadForm() {
    var form = document.getElementById('lead-capture-form');
    if (!form) {
        return;
    }

    var statusEl = document.getElementById('lead-capture-status');
    var submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var nombre = form.elements.nombre.value.trim();
        var email = form.elements.email.value.trim();

        if (!nombre || !email) {
            setStatus(statusEl, 'Por favor completa nombre y correo.', 'error');
            return;
        }

        submitBtn.disabled = true;
        setStatus(statusEl, 'Enviando...', 'loading');

        fetch('api/captura_lead.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message, 'success');
                    form.reset();
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos procesar tu registro.', 'error');
                }
            })
            .catch(function () {
                setStatus(statusEl, 'Error de conexión. Intenta de nuevo en unos minutos.', 'error');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
}

function setStatus(statusEl, message, variant) {
    if (!statusEl) {
        return;
    }

    if (variant === 'loading') {
        statusEl.innerHTML = '<span class="lead-form__spinner" aria-hidden="true"></span> ' + message;
    } else {
        statusEl.textContent = message;
    }

    statusEl.className = 'lead-form__status' + (variant ? ' lead-form__status--' + variant : '');
}

/* ── CLUB DE LECTURA — Lógica de dos etapas (Espera / En Vivo) ────────────── */

// Cambiar a true para simular que faltan 10 minutos para la sesión y
// previsualizar de inmediato la ventana modal de bienvenida.
const CONFIG_TEST_MODE = false;

// Enlace real de Google Meet — nunca expuesto en un atributo href del HTML.
// Sustituir por el enlace definitivo que Pao confirme para el Club de Lectura.
const CLUB_MEET_URL = 'https://meet.google.com/xxx-xxxx-xxx';

const CLUB_SESSION_DAYS = [2, 4]; // 2 = martes, 4 = jueves (0 = domingo)
const CLUB_SESSION_HOUR = 20;
const CLUB_SESSION_MINUTE = 30;
const CLUB_SESSION_DURATION_MS = 60 * 60 * 1000; // 8:30 p.m. a 9:30 p.m.
const CLUB_ACCESS_LEAD_MS = 15 * 60 * 1000; // ventana de acceso: 15 min antes

let clubModalDismissedFor = null;

function initClubLectura() {
    const modal = document.getElementById('club-modal');
    const accessBlock = document.getElementById('club-access');
    if (!modal || !accessBlock) {
        return;
    }

    const enterBtn = document.getElementById('club-modal-enter');
    const closeEls = modal.querySelectorAll('[data-club-modal-close]');

    enterBtn.addEventListener('click', function () {
        window.open(CLUB_MEET_URL, '_blank', 'noopener');
    });

    closeEls.forEach(function (el) {
        el.addEventListener('click', function () {
            hideClubModal(modal);
            clubModalDismissedFor = getClubSessionStart().getTime();
        });
    });

    checkClubSessionWindow(modal, accessBlock);
    setInterval(function () {
        checkClubSessionWindow(modal, accessBlock);
    }, 30 * 1000);
}

function getClubSessionStart() {
    const now = new Date();

    if (CONFIG_TEST_MODE) {
        return new Date(now.getTime() + 10 * 60 * 1000);
    }

    for (let i = 0; i < 8; i++) {
        const candidate = new Date(now);
        candidate.setDate(now.getDate() + i);
        candidate.setHours(CLUB_SESSION_HOUR, CLUB_SESSION_MINUTE, 0, 0);

        if (CLUB_SESSION_DAYS.indexOf(candidate.getDay()) !== -1 && candidate.getTime() > now.getTime()) {
            return candidate;
        }
    }

    return null;
}

function checkClubSessionWindow(modal, accessBlock) {
    const sessionStart = getClubSessionStart();
    if (!sessionStart) {
        return;
    }

    const now = Date.now();
    const accessOpensAt = sessionStart.getTime() - CLUB_ACCESS_LEAD_MS;
    const sessionEndsAt = sessionStart.getTime() + CLUB_SESSION_DURATION_MS;
    const isLiveWindow = now >= accessOpensAt && now <= sessionEndsAt;

    accessBlock.classList.toggle('club-access--live', isLiveWindow);

    if (isLiveWindow && clubModalDismissedFor !== sessionStart.getTime()) {
        showClubModal(modal);
    } else if (!isLiveWindow) {
        hideClubModal(modal);
    }
}

function showClubModal(modal) {
    modal.classList.add('is-visible');
    modal.setAttribute('aria-hidden', 'false');
}

function hideClubModal(modal) {
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
}

/* ── BOTÓN FLOTANTE SCROLL-TO-TOP ──────────────────────────────────────────── */

function initScrollTop() {
    const btn = document.getElementById('btn-scroll-top');
    if (!btn) {
        return;
    }

    window.addEventListener('scroll', function () {
        btn.classList.toggle('is-active', window.scrollY > 400);
    });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

/* ── LIGHTBOX ATÓMICO — Foto de la anfitriona (club-lectura.html) ─────────── */

function initClubHostPhoto() {
    const photo = document.getElementById('club-host-photo');
    const backdrop = document.getElementById('club-photo-backdrop');
    if (!photo || !backdrop) {
        return;
    }

    function toggleMagnify() {
        const isMagnified = photo.classList.toggle('is-magnified');
        backdrop.classList.toggle('is-visible', isMagnified);
    }

    function closeMagnify() {
        photo.classList.remove('is-magnified');
        backdrop.classList.remove('is-visible');
    }

    photo.addEventListener('click', toggleMagnify);
    photo.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleMagnify();
        }
    });
    backdrop.addEventListener('click', closeMagnify);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMagnify();
        }
    });
}

/* ── DASHBOARD UNIVERSAL — Menú hamburguesa (dashboard.php) ───────────────── */

function initDashNav() {
    const shell = document.querySelector('[data-dash-shell]');
    const burger = document.getElementById('dash-burger');
    const nav = document.querySelector('[data-dash-nav]');
    if (!shell || !burger || !nav) {
        return;
    }

    function openNav() {
        shell.classList.add('dash-shell--nav-open');
        burger.setAttribute('aria-expanded', 'true');
        nav.setAttribute('aria-hidden', 'false');
    }

    function closeNav() {
        shell.classList.remove('dash-shell--nav-open');
        burger.setAttribute('aria-expanded', 'false');
        nav.setAttribute('aria-hidden', 'true');
    }

    burger.addEventListener('click', function () {
        shell.classList.contains('dash-shell--nav-open') ? closeNav() : openNav();
    });

    document.querySelectorAll('[data-dash-nav-close]').forEach(function (el) {
        el.addEventListener('click', closeNav);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeNav();
        }
    });
}

function initDashLogout() {
    const btn = document.getElementById('dash-logout-btn');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;

        fetch('api/logout.php', { method: 'POST', credentials: 'same-origin' })
            .finally(function () {
                window.location.href = 'login.php';
            });
    });
}

/* ── ESTADO IRREVERSIBLE ANTE FALLOS CRÍTICOS (login/setup/invitación) ────── */

const authState = {
    halted: false,
};

function haltAuthFlow(message) {
    authState.halted = true;

    const errorBox = document.getElementById('auth-error-container');
    const errorMsg = document.getElementById('auth-error-message');
    if (errorBox && errorMsg) {
        errorMsg.textContent = message;
        errorBox.hidden = false;
    }

    const retryBtn = document.getElementById('auth-retry-btn');
    if (retryBtn) {
        retryBtn.disabled = false;
    }
}

function initAuthErrorRetry() {
    const retryBtn = document.getElementById('auth-retry-btn');
    if (!retryBtn) {
        return;
    }

    retryBtn.addEventListener('click', function () {
        window.location.reload();
    });
}

/* ── LOOP DE PRIMER ARRANQUE — Configuración Génesis (setup-genesis.php) ──── */

function initSetupGenesisForm() {
    const form = document.getElementById('setup-genesis-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('setup-genesis-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (authState.halted) {
            return;
        }

        const nombre = form.elements.nombre.value.trim();
        const email = form.elements.email.value.trim();
        const password = form.elements.password.value;
        const passwordConfirmacion = form.elements.password_confirmacion.value;

        if (!nombre || !email || !password) {
            setStatus(statusEl, 'Completa todos los campos.', 'error');
            return;
        }

        submitBtn.disabled = true;
        setStatus(statusEl, 'Creando cuenta raíz...', 'loading');

        fetch('api/setup_genesis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email, password: password, password_confirmacion: passwordConfirmacion })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message + ' Redirigiendo...', 'success');
                    setTimeout(function () {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos completar la inicialización.', 'error');
                    submitBtn.disabled = false;
                }
            })
            .catch(function () {
                haltAuthFlow('No pudimos conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
            });
    });
}

/* ── LOGIN (login.php) ─────────────────────────────────────────────────────── */

function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('login-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (authState.halted) {
            return;
        }

        const email = form.elements.email.value.trim();
        const password = form.elements.password.value;

        if (!email || !password) {
            setStatus(statusEl, 'Completa correo y contraseña.', 'error');
            return;
        }

        submitBtn.disabled = true;
        setStatus(statusEl, 'Verificando...', 'loading');

        fetch('api/login.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, password: password })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message, 'success');
                    window.location.href = 'dashboard.php';
                } else {
                    setStatus(statusEl, result.data.message || 'Credenciales inválidas.', 'error');
                    submitBtn.disabled = false;
                }
            })
            .catch(function () {
                haltAuthFlow('No pudimos conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
            });
    });
}

/* ── INVITACIÓN — Aceptación (invitacion.php) ─────────────────────────────── */

function initInvitacionForm() {
    const form = document.getElementById('invitacion-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('invitacion-status');
    const submitBtn = form.querySelector('.lead-form__submit');
    const token = form.dataset.token;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (authState.halted) {
            return;
        }

        const password = form.elements.password.value;
        const passwordConfirmacion = form.elements.password_confirmacion.value;

        if (!password || password !== passwordConfirmacion) {
            setStatus(statusEl, 'Las contraseñas no coinciden.', 'error');
            return;
        }

        submitBtn.disabled = true;
        setStatus(statusEl, 'Activando tu cuenta...', 'loading');

        fetch('api/invitacion_confirmar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token, password: password, password_confirmacion: passwordConfirmacion })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message + ' Redirigiendo...', 'success');
                    setTimeout(function () {
                        window.location.href = 'login.php';
                    }, 1500);
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos activar tu cuenta.', 'error');
                    submitBtn.disabled = false;
                }
            })
            .catch(function () {
                haltAuthFlow('No pudimos conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
            });
    });
}

/* ── DASHBOARD — Alta de usuarios (dashboard.php) ─────────────────────────── */

function initUsuarioCrearForm() {
    const form = document.getElementById('usuario-crear-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('usuario-crear-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const nombre = form.elements.nombre.value.trim();
        const email = form.elements.email.value.trim();
        const password = form.elements.password.value;

        submitBtn.disabled = true;
        setStatus(statusEl, 'Creando...', 'loading');

        fetch('api/usuarios_crear.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email, password: password })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message, 'success');
                    form.reset();
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos crear el usuario.', 'error');
                }
            })
            .catch(function () {
                setStatus(statusEl, 'Error de conexión. Intenta de nuevo.', 'error');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
}

function initUsuarioInvitarForm() {
    const form = document.getElementById('usuario-invitar-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('usuario-invitar-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const nombre = form.elements.nombre.value.trim();
        const email = form.elements.email.value.trim();

        submitBtn.disabled = true;
        setStatus(statusEl, 'Enviando invitación...', 'loading');

        fetch('api/usuarios_invitar.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    let mensaje = result.data.message;
                    if (result.data.data && result.data.data.enlace_invitacion_local) {
                        mensaje += ' (local: ' + result.data.data.enlace_invitacion_local + ')';
                    }
                    setStatus(statusEl, mensaje, 'success');
                    form.reset();
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos enviar la invitación.', 'error');
                }
            })
            .catch(function () {
                setStatus(statusEl, 'Error de conexión. Intenta de nuevo.', 'error');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
}
