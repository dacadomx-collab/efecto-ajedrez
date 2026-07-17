# Checklist de Ejecución — MODULO_03_CRM_EVENTOS_EN_VIVO aplicado a El Efecto Ajedrez

**Relación:** Instancia concreta de [`MODULO_03_CRM_EVENTOS_EN_VIVO.md`](MODULO_03_CRM_EVENTOS_EN_VIVO.md). Compañero de [`CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md`](CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md) y [`CHECKLIST_EJECUCION_CMS_EFECTO_AJEDREZ.md`](CHECKLIST_EJECUCION_CMS_EFECTO_AJEDREZ.md).

**Autorización:** Dirección explícita del Productor Tzunum, 2026-07-17. `knowledge/` no fue tocado.

---

## ⚠️ Desviaciones y correcciones aplicadas sobre el mandato original

1. **Correo de auditoría no quedó como string suelto sin control:** `CORREO_AUDITORIA_STAGING` en `api/email_helper.php` está protegido por `APP_ENV !== 'production'` — en producción esta redirección se ignora automáticamente, nunca puede filtrarse por accidente. El correo original queda visible en el asunto/cuerpo (`[STAGING → originalmente para ...]`) para trazabilidad.
2. **Gap real corregido:** el formulario de captación (punto 4 del mandato) no pedía correo electrónico, pero el punto 5 exige notificación masiva por correo — imposible sin él. Se añadió el campo `email` a `registro_interesados` y al formulario público **antes** de migrar la tabla (no se necesitó una migración `ALTER` separada).
3. **"Tabla ARF-Grid" para el ledger:** se implementó como `<table>` HTML real envuelta en `overflow-x: auto`, no como grid de tarjetas — ARF-Grid es el patrón para bloques repetitivos tipo tarjeta (ya documentado en MODULO_01 §4.3/§5.3); forzarlo sobre datos tabulares con 8 columnas habría sido menos accesible y menos correcto semánticamente. Documentado explícitamente en MODULO_03 §5.
4. **Enlace de la sesión ya no vive hardcodeado en el JS del cliente:** se eliminó la constante `CLUB_MEET_URL` de `main.js` (una debilidad heredada — el "gate" de 15 minutos antes solo era visual, el enlace ya era legible en el código fuente). Ahora se resuelve en `api/sesion_actual.php`, servidor-autoritativo sobre la ventana de tiempo — mejora de seguridad real, no solo paridad funcional.

---

## ✅ Checklist de Desarrollo Operativo

### Fase 1 — Documentación
- [x] `modulos/MODULO_03_CRM_EVENTOS_EN_VIVO.md` — blueprint genérico nuevo.
- [x] `MODULO_01_LOGIN_Y_ACCESO.md` §5.3.1 — patrón de Navegación en Acordeón (Anti-Crowding Sidebar) documentado de forma agnóstica, enlazado desde el índice de módulos relacionados.

### Fase 2 — Schema
- [x] `database/create_table_registro_interesados.sql` (con `email` incluido desde el diseño) — migrada.
- [x] `database/create_table_historial_sesiones.sql` (+ `historial_sesiones_asistentes` con FKs `ON DELETE CASCADE`) — migrada.
- [x] `database/seed_permisos_modulos_invitados.sql` — nuevo módulo `invitados` en la matriz dinámica — migrada.

### Fase 3 — Backend (patrón de 6 capas)
- [x] `api/auth_helpers.php` — `resolverGeoIp()` (ip-api.com, sin credenciales, fallback `NULL` si falla — nunca bloquea el registro).
- [x] `api/registro_interesado.php` — público, captura lead + IP/geo silenciosa.
- [x] `api/sesiones_compartir.php` — vincula todos los interesados vigentes, genera tokens de Check-In únicos, envía notificación individual (nunca el enlace crudo).
- [x] `api/sesion_actual.php` — lectura pública, ventana de tiempo resuelta server-side.
- [x] `checkin.php` — Live Gate: valida token, marca asistencia de forma idempotente, redirige `302` al enlace real.
- [x] `api/email_helper.php` — redirección de staging a `dacadomx@yahoo.com`, gateada por `APP_ENV`.
- [x] `api/permisos_modulos.php` — `MODULOS_VALIDOS` extendido con `invitados`.

### Fase 4 — Frontend
- [x] `dashboard.php` — navegación en acordeón (2 grupos: "Gestión de Accesos", "Círculo de Lectura"; Seguridad/Permisos quedan como ítems planos); panel "Invitados Confirmados" (orquestador + ledger + historial de sesiones); tarjeta `invitados` en la matriz de permisos del `super_admin`.
- [x] `club-lectura.php` — botón "Quiero pertenecer" + modal glassmorphic (`nombre`, `email`, `edad`, `ciudad`, `estado`); lógica del enlace en vivo migrada a `api/sesion_actual.php`.
- [x] `api/logo_test.php` — filtrado a los 4 `logo*.png` reales (antes escaneaba cualquier imagen, incluida la foto de Pao); añadida zona de comparación en modo claro y el toggle Día/Noche interactivo (`initThemeToggle()` reutilizado).
- [x] `assets/css/main.css` — acordeón, tabla ledger, modal de leads, zona clara del laboratorio de logos.
- [x] `assets/js/main.js` — `initAccordionNav()`, `initSesionCompartirForm()`, `initLeadCaptureModal()`; `initClubLectura()` actualizado para resolver el enlace vía fetch en vez de la constante hardcodeada.
- [x] `favicon.ico` verificado presente en los 9 encabezados HTML del proyecto (incluidos los 3 archivos nuevos).

### Fase 5 — Verificación end-to-end (cuentas de prueba temporales, creadas y eliminadas en esta sesión)
- [x] `php -l` (10 archivos) y `node --check` — sin errores.
- [x] Registro público de interesado → `201`, IP `::1` (localhost) correctamente resuelta a geo `NULL` (degradación limpia, sin datos falsos).
- [x] `sesiones_compartir.php` → sesión creada, 1/1 interesados notificados (correo real enviado, interceptado por el redirect de staging).
- [x] `sesion_actual.php` fuera de la ventana de tiempo → `enlace: null` (confirmado con la sesión programada a futuro).
- [x] `checkin.php` con token real → `302` al enlace correcto; segundo clic al mismo token → mismo `302`, sin duplicar el registro de asistencia (verificado 1 sola fila con `checkin_en` no nulo en BD).
- [x] Dashboard con `super_admin`: acordeón, ledger e historial presentes y con datos reales.
- [x] Dashboard con `admin` sin permiso `invitados` habilitado: sección `#invitados` ausente del HTML (0 coincidencias).
- [x] Laboratorio de logos: los 4 archivos `logo*.png`, la zona clara y el toggle confirmados en el HTML servido.
- [x] Limpieza final: 1 solo usuario (cuenta raíz real), `registro_interesados`/`historial_sesiones` en 0 filas, matriz de permisos revertida a sus defaults.

### Fase 6 — Pre-comprobación de tablas y refinamientos (2026-07-17, quinta pasada — dirección "Productor Tzunum")
- [x] **Protocolo de pre-comprobación ejecutado primero, como se pidió:** las 10 tablas del ecosistema (incluidas `invitaciones` y `permisos_modulos`, mencionadas explícitamente como sospechosas del bug de delimitador) se verificaron consolidadas en la BD remota, con los 2 triggers de `log_actividad` intactos y las 4 filas de `permisos_modulos` sembradas correctamente. **No se encontró ninguna inconsistencia** — no fue necesario re-ejecutar ninguna migración.
- [x] Auditoría rápida confirmó que el acordeón, el toggle Día/Noche de `logo_test.php`, la redirección de correo en staging, el modal de captación con geolocalización y el ledger de "Invitados Confirmados" **ya estaban construidos y funcionando** desde el hito anterior — no se reconstruyó nada de eso, solo se verificó su presencia real en el código (no solo en el reporte previo).
- [x] **Selector de fecha manual añadido** a `sesiones_compartir.php` y al formulario del orquestador (`datetime-local`) — si el admin no la especifica, se conserva el cálculo automático como respaldo. Probado con fecha explícita `2026-07-18 20:30:00`, confirmada tal cual en la respuesta y en BD.
- [x] **Carga Protegida de Material (PDF Uploader) — construida de cero:**
  - `database/alter_table_historial_sesiones_add_material.sql` — migrada.
  - `uploads/materiales-protegidos/.htaccess` — `Require all denied`, directorio nuevo sin acceso directo por URL.
  - `api/material_subir.php` — MIME real (`application/pdf` únicamente), 20MB máx., renombrado criptográfico.
  - `api/material_descargar.php` — autorización por `token_checkin` + `checkin_en IS NOT NULL` (asistencia real, no solo interés inicial), verificación de *path traversal* vía `realpath()`.
  - `checkin.php` — dejó de ser una redirección `302` instantánea; ahora es una página de aterrizaje real con "Ingresar a la sesión" y (si hay material) "Descargar material de la sesión".
  - **Probado end-to-end con datos reales:** descarga rechazada (`404`) antes del check-in; tras pasar por `checkin.php`, la misma descarga responde `200` con el PDF real. Limpieza posterior confirmada (archivo físico eliminado, BD en 0 filas de sesiones/interesados, 1 solo usuario real).
- [x] `modulos/MODULO_03_CRM_EVENTOS_EN_VIVO.md` actualizado con la Sección 7 (Carga Protegida de Material) y ajustes en §3.1/§4 para la fecha manual y la Sala de Check-In como landing real (no redirección ciega).

### Fase 7 — Pendiente (no construido en esta pasada, requiere decisión adicional)
- [ ] Diseño visual final del modal de captación de leads (hoy reutiliza `.club-modal`/`.lead-form` — funcional, sin refinamiento adicional de copy/estilo específico para este formulario).
- [ ] Notificaciones de fallo de envío individual visibles en el Dashboard (hoy: el conteo `X/Y notificados` es la única señal; no hay detalle de qué destinatario falló).
- [ ] Borrado del PDF anterior al subir uno nuevo para la misma sesión (hoy: el `UPDATE` solo cambia la referencia en BD; el archivo físico previo queda huérfano en `uploads/materiales-protegidos/`).

### Fase 8 — UX, i18n y enlace roto (2026-07-17, sexta pasada — dirección "Productor Tzunum")
- [x] **Enlace roto resuelto:** `club-lectura.html` recreado como puente fail-safe (meta-refresh + `window.location.replace`) hacia `club-lectura.php` — cero fricción visible, `200` confirmado por HTTP. Documentado como patrón genérico en MODULO_02 §5.5.
- [x] **Horarios internacionales:** agregadas 3 filas con banderas (🇲🇽 CDMX 8:30pm, 🇵🇪 Perú 9:30pm, 🇦🇷 Argentina 11:30pm — calculadas por diferencia horaria real, sin DST en ninguno de los tres países) en la sección de Detalles.
- [x] **Limpieza de botones:** de 3 CTAs con textos distintos a exactamente 2, ambos con el texto literal "Quiero unirme al Club de Lectura" — uno debajo de "Detalles de nuestras reuniones", otro al cierre. Ambos abren el mismo modal de captación (antes solo uno lo hacía). Verificado por HTTP: exactamente 2 coincidencias de la clase+texto exacto.
- [x] **Dashboard completamente seccionado:** las 5 secciones operativas (`usuarios`, `landing`, `invitados`, `seguridad`, `permisos`) nacen con el atributo `hidden` — la pantalla de inicio solo muestra el bloque AURA. Nuevo conmutador `initDashPanelSwitcher()` (`data-panel-target`) revela paneles bajo demanda al navegar el acordeón, con `preventDefault()` sobre el salto de ancla nativo. Verificado por HTTP con cuenta de prueba: las 5 secciones confirmadas `hidden` en el HTML servido tras el login. Documentado como MODULO_01 §5.3.2.
- [x] **Plantilla de invitación despachada a Yahoo real:** se disparó una invitación de prueba a nombre de "Paola Palomares" (rol `admin`) — `correo_enviado: true`, confirmado en `log_actividad`. **Nota importante:** se usó un correo placeholder (`paola.palomares.preview@efecto-ajedrez.local`) porque no se cuenta con el correo real de Paola; el envío SMTP real fue interceptado automáticamente hacia `dacadomx@yahoo.com` por la regla de staging ya activa (Fase 6). La cuenta de prueba se eliminó después — **no se creó su cuenta definitiva**, que requiere su correo real cuando el Arquitecto lo confirme.
- [x] Punto 5 del mandato (automatización de sesiones con horario fijo Mar/Jue, PDF Uploader, filtro de descarga por Check-In, `historial_sesiones` inmutable) — **ya estaba completo desde la Fase 6**; se verificó que sigue funcionando sin tocarlo de nuevo.
- [x] `php -l` / `node --check` sin errores en todos los archivos tocados. BD limpia al cierre: 1 solo usuario real.
