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
    initPasswordToggles();
    initPasswordStrengthMeters();
    initPoliticaSeguridadForm();
    initRecuperarPasswordForm();
    initRestablecerPasswordForm();
    initThemeToggle();
    initAuraWelcome();
    initPermisosModulosForm();
    initInlineEditor();
    initAccordionNav();
    initDashPanelSwitcher();
    initSesionCompartirForm();
    initMaterialSubirForm();
    initLeadCaptureModal();
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
        // El enlace real nunca vive en el HTML/JS del cliente — se resuelve
        // en el backend, que solo lo entrega dentro de la ventana de tiempo
        // autorizada (MODULO_03_CRM_EVENTOS_EN_VIVO §3.2).
        fetch('api/sesion_actual.php', { method: 'GET' })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (result.data && result.data.enlace) {
                    window.open(result.data.enlace, '_blank', 'noopener');
                } else {
                    alert('La sesión aún no está disponible. Intenta de nuevo en unos minutos.');
                }
            })
            .catch(function () {
                alert('No pudimos verificar la sesión. Intenta de nuevo.');
            });
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
        const recordarme = form.elements.recordarme ? form.elements.recordarme.checked : false;

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
            body: JSON.stringify({ email: email, password: password, recordarme: recordarme })
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
        const rol = form.elements.rol.value;
        const password = form.elements.password.value;

        submitBtn.disabled = true;
        setStatus(statusEl, 'Creando...', 'loading');

        fetch('api/usuarios_crear.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email, rol: rol, password: password })
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
        const rol = form.elements.rol.value;

        submitBtn.disabled = true;
        setStatus(statusEl, 'Enviando invitación...', 'loading');

        fetch('api/usuarios_invitar.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email, rol: rol })
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

/* ── CONTROLES DE CONTRASEÑA — Visibility Toggle ("ojito") ────────────────── */

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        const input = document.getElementById(btn.dataset.passwordToggle);
        if (!input) {
            return;
        }

        btn.addEventListener('click', function () {
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.setAttribute('aria-pressed', String(!visible));
            btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    });
}

/* ── MEDIDOR DE FUERZA DE CONTRASEÑA (0-100%) ─────────────────────────────── */

let politicaActivaCache = null;

function obtenerPoliticaActivaFrontend() {
    if (politicaActivaCache) {
        return Promise.resolve(politicaActivaCache);
    }

    return fetch('api/configuracion_seguridad.php', { method: 'GET' })
        .then(function (response) {
            return response.json();
        })
        .then(function (result) {
            politicaActivaCache = (result.data && result.data.definicion) || {
                longitud_minima: 8,
                requiere_mayuscula: false,
                requiere_minuscula: true,
                requiere_numero: true,
                requiere_simbolo: false
            };
            return politicaActivaCache;
        })
        .catch(function () {
            politicaActivaCache = {
                longitud_minima: 8,
                requiere_mayuscula: false,
                requiere_minuscula: true,
                requiere_numero: true,
                requiere_simbolo: false
            };
            return politicaActivaCache;
        });
}

function calcularFuerzaPassword(password, politica) {
    const checks = [
        password.length >= politica.longitud_minima,
        !politica.requiere_mayuscula || /[A-Z]/.test(password),
        !politica.requiere_minuscula || /[a-z]/.test(password),
        !politica.requiere_numero || /[0-9]/.test(password),
        !politica.requiere_simbolo || /[^a-zA-Z0-9]/.test(password)
    ];

    const cumplidos = checks.filter(Boolean).length;
    return Math.round((cumplidos / checks.length) * 100);
}

function initPasswordStrengthMeters() {
    const medidores = document.querySelectorAll('[data-password-strength-for]');
    if (medidores.length === 0) {
        return;
    }

    obtenerPoliticaActivaFrontend().then(function (politica) {
        medidores.forEach(function (medidor) {
            const input = document.getElementById(medidor.dataset.passwordStrengthFor);
            const fill = medidor.querySelector('[data-password-strength-fill]');
            const label = medidor.querySelector('[data-password-strength-label]');
            if (!input || !fill || !label) {
                return;
            }

            input.addEventListener('input', function () {
                const porcentaje = calcularFuerzaPassword(input.value, politica);
                fill.style.width = porcentaje + '%';

                let nivel = 'debil';
                let texto = 'Débil';
                if (porcentaje >= 100) {
                    nivel = 'fuerte';
                    texto = 'Cumple la política';
                } else if (porcentaje >= 60) {
                    nivel = 'media';
                    texto = 'Aceptable';
                }

                if (input.value === '') {
                    texto = '';
                }

                fill.setAttribute('data-level', nivel);
                label.textContent = texto;
            });
        });
    });
}

/* ── DASHBOARD — Política de Seguridad (super_admin, dashboard.php) ───────── */

function initPoliticaSeguridadForm() {
    const form = document.getElementById('politica-seguridad-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('politica-seguridad-status');
    const actualEl = document.getElementById('politica-activa-actual');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const perfilSeleccionado = form.querySelector('input[name="perfil"]:checked');
        const duracionSeleccionada = form.querySelector('input[name="duracion_recordarme_dias"]:checked');
        if (!perfilSeleccionado || !duracionSeleccionada) {
            setStatus(statusEl, 'Selecciona un perfil y una duración.', 'error');
            return;
        }

        setStatus(statusEl, 'Aplicando...', 'loading');

        fetch('api/configuracion_seguridad.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ perfil: perfilSeleccionado.value, duracion_recordarme_dias: parseInt(duracionSeleccionada.value, 10) })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message, 'success');
                    if (actualEl) {
                        actualEl.textContent = perfilSeleccionado.value;
                    }
                    politicaActivaCache = null; // fuerza relectura en el próximo medidor de fuerza
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos actualizar la política.', 'error');
                }
            })
            .catch(function () {
                setStatus(statusEl, 'Error de conexión. Intenta de nuevo.', 'error');
            });
    });
}

/* ── RECUPERACIÓN DE CONTRASEÑA — Solicitud (recuperar-password.php) ──────── */

function initRecuperarPasswordForm() {
    const form = document.getElementById('recuperar-password-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('recuperar-password-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (authState.halted) {
            return;
        }

        const email = form.elements.email.value.trim();
        if (!email) {
            setStatus(statusEl, 'Ingresa tu correo electrónico.', 'error');
            return;
        }

        submitBtn.disabled = true;
        setStatus(statusEl, 'Enviando...', 'loading');

        fetch('api/recuperar_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                setStatus(statusEl, result.data.message, result.ok ? 'success' : 'error');
                if (result.ok) {
                    form.reset();
                }
            })
            .catch(function () {
                haltAuthFlow('No pudimos conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
}

/* ── RECUPERACIÓN DE CONTRASEÑA — Confirmación (restablecer-password.php) ─── */

function initRestablecerPasswordForm() {
    const form = document.getElementById('restablecer-password-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('restablecer-password-status');
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
        setStatus(statusEl, 'Restableciendo...', 'loading');

        fetch('api/restablecer_password.php', {
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
                    setStatus(statusEl, result.data.message || 'No pudimos restablecer tu contraseña.', 'error');
                    submitBtn.disabled = false;
                }
            })
            .catch(function () {
                haltAuthFlow('No pudimos conectar con el servidor. Verifica tu conexión e intenta de nuevo.');
            });
    });
}

/* ── TOGGLE DÍA/NOCHE — MODULO_01_LOGIN_Y_ACCESO §5.4 ──────────────────────── */

const TEMA_STORAGE_KEY = 'efecto_ajedrez_admin_theme';

function initThemeToggle() {
    const guardado = localStorage.getItem(TEMA_STORAGE_KEY);
    if (guardado) {
        document.body.setAttribute('data-theme', guardado);
    }

    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    toggle.textContent = document.body.getAttribute('data-theme') === 'light' ? '☀️' : '🌙';

    toggle.addEventListener('click', function () {
        const actual = document.body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        document.body.setAttribute('data-theme', actual);
        localStorage.setItem(TEMA_STORAGE_KEY, actual);
        toggle.textContent = actual === 'light' ? '☀️' : '🌙';
    });
}

/* ── NÚCLEO COGNITIVO DE BIENVENIDA (dashboard.php) ────────────────────────── */

function initAuraWelcome() {
    const bloque = document.querySelector('[data-aura-welcome]');
    if (!bloque) {
        return;
    }

    // Saludo contextual por hora local del dispositivo — puramente cosmético,
    // se calcula en el cliente (MODULO_01_LOGIN_Y_ACCESO §10.1).
    const saludoEl = document.getElementById('aura-saludo');
    if (saludoEl) {
        const hora = new Date().getHours();
        let saludo = 'Buenas noches';
        if (hora >= 5 && hora < 12) {
            saludo = 'Buenos días';
        } else if (hora >= 12 && hora < 19) {
            saludo = 'Buenas tardes';
        }
        saludoEl.textContent = saludoEl.textContent.replace(/^[^,]+/, saludo);
    }

    // Geolocalización — se solicita explícitamente, se degrada limpiamente si
    // se niega o no está disponible (MODULO_01_LOGIN_Y_ACCESO §10.2). Usa
    // Nominatim (OpenStreetMap) como proveedor gratuito sin API key; sin
    // integración de pago configurada, es el mejor esfuerzo disponible.
    const ubicacionEl = document.getElementById('aura-ubicacion');
    if (ubicacionEl && 'geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(function (posicion) {
            const lat = posicion.coords.latitude;
            const lon = posicion.coords.longitude;

            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lon)
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    const direccion = data.address || {};
                    const municipio = direccion.city || direccion.town || direccion.village || direccion.county || '';
                    const estado = direccion.state || '';
                    const pais = direccion.country || '';
                    const partes = [municipio, estado, pais].filter(Boolean);

                    if (partes.length > 0) {
                        ubicacionEl.textContent = '📍 ' + partes.join(', ');
                        ubicacionEl.hidden = false;
                    }
                })
                .catch(function () {
                    // Degradación silenciosa — el bloque de bienvenida sigue
                    // funcionando sin la línea de ubicación.
                });
        }, function () {
            // Permiso denegado o no disponible — no se muestra la línea.
        });
    }

    // Cápsula motivacional — persistida por día en el backend (banco
    // estático curado; este proyecto no tiene un proveedor de IA "AURA"
    // contratado — MODULO_01_LOGIN_Y_ACCESO §10.3).
    const fraseEl = document.getElementById('aura-frase');
    if (fraseEl) {
        fetch('api/bienvenida.php', { method: 'GET', credentials: 'same-origin' })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (result.status === 'success' && result.data && result.data.frase) {
                    fraseEl.textContent = '"' + result.data.frase + '"';
                } else {
                    fraseEl.textContent = '';
                }
            })
            .catch(function () {
                fraseEl.textContent = '';
            });
    }
}

/* ── DASHBOARD — Mapeo de Permisos por Módulo (super_admin) ───────────────── */

function initPermisosModulosForm() {
    const toggles = document.querySelectorAll('[data-permiso-toggle]');
    if (toggles.length === 0) {
        return;
    }

    const statusEl = document.getElementById('permisos-modulos-status');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const modulo = toggle.dataset.modulo;
            const habilitado = toggle.checked;

            setStatus(statusEl, 'Guardando...', 'loading');

            fetch('api/permisos_modulos.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo: modulo, rol: 'admin', habilitado: habilitado })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data.status === 'success') {
                        setStatus(statusEl, 'Permiso actualizado.', 'success');
                    } else {
                        toggle.checked = !habilitado; // revertir en caso de rechazo del backend
                        setStatus(statusEl, result.data.message || 'No pudimos actualizar el permiso.', 'error');
                    }
                })
                .catch(function () {
                    toggle.checked = !habilitado;
                    setStatus(statusEl, 'Error de conexión. Intenta de nuevo.', 'error');
                });
        });
    });
}

/* ── MOTOR DE EDICIÓN VISUAL EN CALIENTE — MODULO_02_CMS_EDICION_VISUAL ────── */

function initInlineEditor() {
    if (!document.body.hasAttribute('data-edit-mode')) {
        return;
    }

    const pagina = document.body.dataset.editPagina;
    const fileInput = document.getElementById('edit-image-input');

    document.querySelectorAll('[data-block-id]').forEach(function (bloque) {
        bloque.addEventListener('click', function () {
            if (bloque.dataset.blockType === 'texto') {
                activarEdicionTexto(pagina, bloque);
            } else if (bloque.dataset.blockType === 'imagen' && fileInput) {
                fileInput.dataset.targetBlockId = bloque.dataset.blockId;
                fileInput.click();
            }
        });
    });

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const archivo = fileInput.files[0];
            const bloqueId = fileInput.dataset.targetBlockId;
            if (!archivo || !bloqueId) {
                return;
            }

            const imgEl = document.querySelector('[data-block-id="' + bloqueId + '"]');
            const formData = new FormData();
            formData.append('pagina', pagina);
            formData.append('bloque_id', bloqueId);
            formData.append('imagen', archivo);

            fetch('api/layout_imagen_subir.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data.status === 'success' && imgEl) {
                        imgEl.src = result.data.data.imagen_path + '?t=' + Date.now();
                    } else {
                        alert(result.data.message || 'No pudimos actualizar la imagen.');
                    }
                })
                .catch(function () {
                    alert('Error de conexión. Intenta de nuevo.');
                })
                .finally(function () {
                    fileInput.value = '';
                });
        });
    }

    const cerrarBtn = document.getElementById('edit-cerrar-btn');
    if (cerrarBtn) {
        cerrarBtn.addEventListener('click', function () {
            window.location.href = 'dashboard.php';
        });
    }
}

function activarEdicionTexto(pagina, bloque) {
    if (bloque.getAttribute('contenteditable') === 'true') {
        return; // ya está en edición — evita controles duplicados
    }

    const valorOriginal = bloque.textContent;
    bloque.setAttribute('contenteditable', 'true');
    bloque.focus();

    const controles = document.createElement('div');
    controles.className = 'edit-block-controls';
    controles.innerHTML =
        '<button type="button" class="edit-block-controls__btn edit-block-controls__guardar">Guardar</button>' +
        '<button type="button" class="edit-block-controls__btn edit-block-controls__cancelar">Cancelar</button>';

    bloque.insertAdjacentElement('afterend', controles);

    const cerrarEdicion = function () {
        bloque.removeAttribute('contenteditable');
        controles.remove();
    };

    controles.querySelector('.edit-block-controls__cancelar').addEventListener('click', function () {
        bloque.textContent = valorOriginal;
        cerrarEdicion();
    });

    controles.querySelector('.edit-block-controls__guardar').addEventListener('click', function () {
        const nuevoContenido = bloque.textContent;

        fetch('api/layout_bloque_guardar.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pagina: pagina, bloque_id: bloque.dataset.blockId, contenido: nuevoContenido })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    cerrarEdicion();
                } else {
                    alert(result.data.message || 'No pudimos guardar el bloque.');
                }
            })
            .catch(function () {
                alert('Error de conexión. Intenta de nuevo.');
            });
    });
}

/* ── NAVEGACIÓN EN ACORDEÓN (Anti-Crowding Sidebar, dashboard.php) ────────── */

function initAccordionNav() {
    document.querySelectorAll('[data-accordion-trigger]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const grupo = trigger.closest('[data-accordion-group]');
            const panel = grupo.querySelector('[data-accordion-panel]');
            const abrirlo = trigger.getAttribute('aria-expanded') !== 'true';

            document.querySelectorAll('[data-accordion-trigger]').forEach(function (otro) {
                otro.setAttribute('aria-expanded', 'false');
                otro.closest('[data-accordion-group]').querySelector('[data-accordion-panel]').hidden = true;
            });

            trigger.setAttribute('aria-expanded', String(abrirlo));
            panel.hidden = !abrirlo;
        });
    });
}

/* ── ANTI-SATURACIÓN — Conmutador de paneles del Dashboard ────────────────── */
// La pantalla de inicio solo muestra el bloque AURA de bienvenida; el resto
// del contenido operativo permanece oculto hasta que el usuario navega a un
// grupo/ítem específico del acordeón (MODULO_01_LOGIN_Y_ACCESO §5.3.1).

function initDashPanelSwitcher() {
    const disparadores = document.querySelectorAll('[data-panel-target]');
    if (disparadores.length === 0) {
        return;
    }

    const paneles = document.querySelectorAll('.dash-panel');

    function mostrarPaneles(destino) {
        const idsVisibles = destino === '' ? [] : destino.split(',');
        paneles.forEach(function (panel) {
            panel.hidden = !idsVisibles.includes(panel.id);
        });
    }

    disparadores.forEach(function (el) {
        el.addEventListener('click', function (event) {
            const href = el.getAttribute('href');
            if (el.tagName === 'A' && href && href.startsWith('#')) {
                event.preventDefault();
            }

            mostrarPaneles(el.dataset.panelTarget);

            // Sub-ítems (ej. "Alta Directiva") llevan a un ancla específica
            // dentro del panel recién mostrado; el disparador de grupo sin
            // ancla real solo desplaza al inicio del contenido.
            const objetivo = href && href.length > 1 ? document.querySelector(href) : document.querySelector('.dash-content');
            if (objetivo) {
                objetivo.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

/* ── ORQUESTADOR DE SESIONES EN VIVO (dashboard.php, Invitados Confirmados) ─ */

function initSesionCompartirForm() {
    const form = document.getElementById('sesion-compartir-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('sesion-compartir-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const enlace = form.elements.enlace.value.trim();
        const tema = form.elements.tema.value.trim();
        const fechaHora = form.elements.fecha_hora.value.trim();

        submitBtn.disabled = true;
        setStatus(statusEl, 'Compartiendo y notificando...', 'loading');

        fetch('api/sesiones_compartir.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enlace: enlace, tema: tema, fecha_hora: fechaHora })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.status === 'success') {
                    setStatus(statusEl, result.data.message + ' Actualizando...', 'success');
                    setTimeout(function () {
                        window.location.reload();
                    }, 1200);
                } else {
                    setStatus(statusEl, result.data.message || 'No pudimos compartir la sesión.', 'error');
                    submitBtn.disabled = false;
                }
            })
            .catch(function () {
                setStatus(statusEl, 'Error de conexión. Intenta de nuevo.', 'error');
                submitBtn.disabled = false;
            });
    });
}

/* ── CARGA PROTEGIDA DE MATERIAL LITERARIO (dashboard.php, PDF Uploader) ──── */

function initMaterialSubirForm() {
    const form = document.getElementById('material-subir-form');
    if (!form) {
        return;
    }

    const statusEl = document.getElementById('material-subir-status');
    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const sesionId = form.elements.sesion_id.value;
        const archivo = form.elements.material.files[0];

        if (!sesionId || !archivo) {
            setStatus(statusEl, 'Selecciona una sesión y un archivo PDF.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('sesion_id', sesionId);
        formData.append('material', archivo);

        submitBtn.disabled = true;
        setStatus(statusEl, 'Subiendo...', 'loading');

        fetch('api/material_subir.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
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
                    setStatus(statusEl, result.data.message || 'No pudimos subir el material.', 'error');
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

/* ── CAPTACIÓN DE LEADS — "Quiero pertenecer" (club-lectura.php) ──────────── */

function initLeadCaptureModal() {
    const modal = document.getElementById('lead-capture-modal');
    const abrirBtns = document.querySelectorAll('.btn-quiero-unirme');
    if (!modal || abrirBtns.length === 0) {
        return;
    }

    const form = document.getElementById('registro-interesado-form');
    const statusEl = document.getElementById('registro-interesado-status');
    const closeEls = modal.querySelectorAll('[data-lead-modal-close]');

    abrirBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    closeEls.forEach(function (el) {
        el.addEventListener('click', function () {
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
        });
    });

    if (!form) {
        return;
    }

    const submitBtn = form.querySelector('.lead-form__submit');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const nombre = form.elements.nombre.value.trim();
        const email = form.elements.email.value.trim();
        const edad = form.elements.edad.value.trim();
        const ciudad = form.elements.ciudad.value.trim();
        const estado = form.elements.estado.value.trim();

        submitBtn.disabled = true;
        setStatus(statusEl, 'Enviando...', 'loading');

        fetch('api/registro_interesado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre: nombre, email: email, edad: edad, ciudad: ciudad, estado: estado })
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
