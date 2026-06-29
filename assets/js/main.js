document.addEventListener('DOMContentLoaded', function () {
    initLeadForm();
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
