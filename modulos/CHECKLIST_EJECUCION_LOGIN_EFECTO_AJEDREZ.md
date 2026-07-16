# Checklist de Ejecución — MODULO_01_LOGIN_Y_ACCESO aplicado a El Efecto Ajedrez

**Relación:** Instancia concreta del blueprint genérico [`MODULO_01_LOGIN_Y_ACCESO.md`](MODULO_01_LOGIN_Y_ACCESO.md) para este proyecto. Este archivo **sí** usa nombres reales (rompe deliberadamente la Regla de Oro de Abstracción del módulo genérico, que debe permanecer agnóstico) — es el registro de auditoría de qué se construyó, dónde, y qué falta.

**Autorización:** Confirmada explícitamente por el Arquitecto el 2026-07-16 (creación de tablas nuevas — Mandamiento 9) para construir el Dashboard administrativo completo (schema + endpoints + frontend).

---

## ✅ Checklist de Desarrollo Operativo

### Fase 1 — Schema (SQL primero)
- [x] `database/create_table_usuarios.sql` — tabla `usuarios` (roles `super_admin`/`admin`, BCrypt cost=12, token opaco, device_hash, tarpitting).
- [x] `database/create_table_log_actividad.sql` — bitácora append-only con triggers `SIGNAL SQLSTATE '45000'`.
- [x] `database/create_table_invitaciones.sql` — soporte del Método B (invitación segura).
- [ ] **Migración ejecutada contra la BD remota** (`tourfindycom_ajedrez_db`) — **pendiente de confirmación explícita adicional** antes de correr `php database/run_migration.php <archivo>.sql`, porque este proyecto no tiene BD de staging: `DB_HOST` en `.env` apunta a la única base de datos, que también sirve producción. Ejecutar la migración es, en la práctica, un cambio de schema en producción.

### Fase 2 — Endpoints PHP (patrón de 6 capas)
- [x] `api/auth_helpers.php` — helpers compartidos (`jsonResponse`, `requireAuth`, `calcularDeviceHash`, `registrarActividad`, cookies).
- [x] `api/email_helper.php` — cliente SMTP mínimo (sin dependencias) para el correo transaccional de invitación, usando `SMTP_*` ya declaradas en `.env`.
- [x] `api/setup_genesis.php` — GET (estado) / POST (creación del primer `super_admin`, auto-desactivación vía 410).
- [x] `api/login.php` — anti-enumeración (hash dummy), tarpitting (5 intentos → bloqueo 15 min), token opaco 256 bits, device binding.
- [x] `api/logout.php` — revocación inmediata del token.
- [x] `api/usuarios_crear.php` — Método A (alta directa, activa de inmediato).
- [x] `api/usuarios_invitar.php` — Método B paso 1 (registro `pendiente` + token + intento de envío SMTP; en `APP_ENV=local` expone el enlace en la respuesta para pruebas).
- [x] `api/invitacion_confirmar.php` — Método B paso 3 (activación de cuenta, `hash_equals`, TTL 48h).
- [x] `node --check` / `php -l` ejecutados sobre todos los archivos JS/PHP nuevos — sin errores de sintaxis.

### Fase 3 — Frontend (Dashboard Mobile-First + páginas de acceso)
- [x] `login.php` — formulario de acceso, reutiliza `.lead-form` (cero duplicación de estilos).
- [x] `setup-genesis.php` — guard server-side + formulario de First-Run Provisioning con política de contraseña de 14+ caracteres.
- [x] `invitacion.php` — vista standalone de aceptación de invitación (token en querystring).
- [x] `dashboard.php` — shell protegido (guard server-side vía cookie + `device_hash`), menú hamburguesa nativo, panel de alta de usuarios (Métodos A y B).
- [x] `assets/css/main.css` — bloques `.auth-page`, `.dash-*` añadidos al final del archivo unificado (variables `--ajedrez-*`, cero inline, cero `!important`, ARF-Grid en `.dash-panel__grid`).
- [x] `assets/js/main.js` — `initDashNav`, `initLoginForm`, `initSetupGenesisForm`, `initInvitacionForm`, `initUsuarioCrearForm`, `initUsuarioInvitarForm`, `initDashLogout`, patrón `authState.halted` / `haltAuthFlow`.

### Fase 4 — Pendiente (no construido en esta pasada, requiere decisión adicional)
- [ ] Ejecutar la migración SQL contra la BD remota (ver nota de Fase 1).
- [ ] Verificación funcional end-to-end en el navegador (crear cuenta raíz → login → invitar usuario → confirmar invitación) — requiere que la migración ya esté aplicada.
- [ ] Rate limiting a nivel de IP agregado (hoy el tarpitting es por cuenta de usuario, vía `intentos_fallidos`/`bloqueado_hasta`; un rate-limit adicional por IP pura requeriría una tabla o store nuevo — no se construyó sin autorización explícita adicional).
- [ ] Notificación de dispositivo/IP nuevos al usuario (fuera de alcance — requiere plantilla de correo adicional, no solicitada).
- [ ] `APP_SECRET`/`JWT_SECRET` real en `.env` — el device hash usa `JWT_SECRET` si existe, o `DB_PASS` como respaldo; `.env` no se modificó (está en la lista de "nunca se modifica sin autorización" del `CLAUDE.md` del proyecto). Añadir `JWT_SECRET` a la `.env` real queda pendiente de que el Arquitecto lo autorice/genere.
