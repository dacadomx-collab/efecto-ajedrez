document.addEventListener('DOMContentLoaded', function () {
    initLeadForm();
    initClubLectura();
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
