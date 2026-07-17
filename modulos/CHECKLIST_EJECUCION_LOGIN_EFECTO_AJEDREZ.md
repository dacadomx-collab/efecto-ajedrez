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

### Fase 4 — Motor Dinámico de Políticas de Contraseña (2026-07-16, segunda pasada)
- [x] `database/create_table_configuracion_seguridad.sql` — tabla fila-única (`id=1`), migrada y sembrada con **`media`** (perfil por defecto de este proyecto, decisión del Arquitecto).
- [x] `api/auth_helpers.php` — `passwordCumplePolitica()` ahora recibe una `$definicion` calculada por `politicaSeguridadDefinicion($perfil)`; añadidas `obtenerPoliticaActiva()` y `mensajePoliticaPassword()`.
- [x] `api/configuracion_seguridad.php` — `GET` público (umbrales, sin auth) para calibrar el medidor de fuerza; `POST` exclusivo `super_admin` para cambiar el perfil activo.
- [x] `api/setup_genesis.php`, `api/usuarios_crear.php`, `api/invitacion_confirmar.php` — leen la política activa dinámicamente en vez del umbral fijo de 14 caracteres de la primera pasada.
- [x] Código de auto-desactivación del genesis cambiado de `410 Gone` a **`403 Forbidden`** (a petición explícita del Arquitecto — ver MODULO_01 §8.4).
- [x] Password Visibility Toggle ("ojito") en los 6 campos de contraseña del sistema (`login.php`, `setup-genesis.php` ×2, `invitacion.php` ×2, `dashboard.php`).
- [x] Medidor de fuerza 0-100% (`password-strength`) en los 3 formularios de creación/cambio de contraseña (setup-genesis, invitacion, dashboard Método A) — lee la política activa vía `GET api/configuracion_seguridad.php`.
- [x] Panel exclusivo de `super_admin` en `dashboard.php` (`#seguridad`) con las 3 tarjetas de perfil bajo patrón ARF-Grid (`.policy-card`).
- [x] Verificado con `php -l` (10 archivos) y `node --check` — sin errores. Probado por HTTP: `GET configuracion_seguridad.php` responde 200 con la definición correcta; `POST setup_genesis.php` con contraseña de 6 caracteres rechazada (422) confirmando que ahora exige el perfil "media" (8+) y no ya los 14 anteriores; `usuarios` sigue en 0 filas (ninguna prueba tocó la cuenta raíz real).

### Fase 5 — Cuenta raíz real (completada)
- [x] Cuenta `super_admin` creada por el Arquitecto vía `setup-genesis.php` el 2026-07-16 (David Cabrera, `estatus=activo`). Verificado por consulta directa a `usuarios` (sin ver la contraseña) y por `POST api/setup_genesis.php` → `403 Forbidden` confirmando la auto-desactivación.

### Fase 6 — Diagnóstico de conexión, Recovery, Remember Me y Rol en alta de usuarios (2026-07-16, tercera pasada — dirección "Productor Tzunum")
- [x] **Diagnóstico Triple Handshake** (`api/status_check.php`): Filesystem OK, DB remota OK (~840ms), SMTP OK. Credenciales de `.env` ya coincidían exactamente con las solicitadas — no requirió cambios en `.env` ni en `api/conexion.php`.
- [x] `database/alter_table_configuracion_seguridad_recordarme.sql` — añade `duracion_recordarme_dias` (60/120) a la fila única existente. Migrada y sembrada en **60 días (2 meses)**, orden explícita del Productor.
- [x] `database/create_table_recuperacion_password.sql` — tabla para el flujo "Olvidé mi contraseña". Migrada.
- [x] `api/auth_helpers.php` — `obtenerConfiguracionSeguridad()` (reemplaza la lectura aislada de política), `obtenerDuracionRecordarme()`, `clamparRolSegunActor()` (jerarquía: un actor nunca puede otorgar un rol de nivel superior al propio).
- [x] `api/recuperar_password.php` (solicitud, anti-enumeración estricta — mismo mensaje exista o no el correo) y `api/restablecer_password.php` (confirmación — revoca la sesión activa previa del usuario tras el reset).
- [x] `api/login.php` — acepta `recordarme`; si es `true`, el TTL del token/cookie usa `duracion_recordarme_dias` en vez de las 8h por defecto, sin relajar `HttpOnly`/`SameSite`/`Secure`/device binding.
- [x] `api/configuracion_seguridad.php` — GET/POST ahora incluyen `duracion_recordarme_dias` (solo acepta 60 o 120).
- [x] `api/usuarios_crear.php` e `api/usuarios_invitar.php` — aceptan `rol` explícito en el payload, recortado siempre por `clamparRolSegunActor()`.
- [x] Frontend: `login.php` (checkbox "Mantenerme registrado" + enlace "¿Olvidaste tu contraseña?"), `recuperar-password.php` (nuevo), `restablecer-password.php` (nuevo, con toggle + medidor de fuerza), `dashboard.php` (selector de rol en Métodos A/B, tarjetas de duración de "recordarme" en el panel de seguridad, ambas exclusivas de los roles correspondientes).
- [x] CSS: `.lead-form__checkbox-row`, `.auth-page__link-row`, `.dash-panel__subtitle` añadidos al bloque unificado (cero inline, cero `!important`).
- [x] Verificado: `php -l` (12 archivos) y `node --check` sin errores; `GET configuracion_seguridad.php` responde `duracion_recordarme_dias: 60`; `POST recuperar_password.php` con correo inexistente responde el mismo mensaje genérico (200); páginas nuevas responden `200`; `POST setup_genesis.php` tras la creación real de la cuenta raíz responde `403` de forma determinista.
- [x] MODULO_01_LOGIN_Y_ACCESO.md actualizado a **v5.0** (§3.5 Remember Me, §3.6 Password Reset, §7.5 duración configurable, §9.1/9.2 asignación de rol con jerarquía) — corregida además una inconsistencia heredada en §6 (la regla decía "nivel igual o superior" pero el ejemplo y la implementación real permiten nivel igual, ej. `admin` creando otro `admin`).

### Fase 7 — Verificación End-to-End del Login (2026-07-17, cuarta pasada — dirección "Productor Tzunum")
- [x] **Bug real encontrado y corregido:** `session_regenerate_id(true)` en `api/login.php` lanzaba un *Warning* de PHP (el proyecto no usa `$_SESSION` nativo) que contaminaba el JSON de respuesta con HTML antes del `{`. Eliminado — el token opaco nuevo por login ya cumple esa función. Corregido también el texto de referencia en MODULO_01 §3.2.
- [x] Cookie de perfil visible añadida como `usuario_perfil` (JSON `{nombre,email,rol}`, `HttpOnly=false`, mismo TTL que `token_acceso`) — **se mantuvo el nombre ya registrado `token_acceso`** en vez de renombrarlo a "axon_token" pedido en esta pasada, para no violar el Mandamiento 10 (Sinónimos Prohibidos) ni reintroducir el namespace "AXON" que este proyecto descarta explícitamente (ver CLAUDE.md — plantilla AXON_GENESIS heredada, nunca reutilizada aquí).
- [x] Probado end-to-end por HTTP con un usuario de prueba temporal (creado y eliminado en esta misma sesión, cuenta raíz real nunca tocada): login exitoso con `recordarme=false` (cookie `Max-Age=28800`, 8h) y `recordarme=true` (cookie `Max-Age=5184000`, exactamente 60 días); `dashboard.php` acepta la cookie válida (200, antes redirigía); login con contraseña incorrecta responde `401` genérico; los 4 eventos (`login_exitoso` ×3, `login_fallido` ×1) quedaron en `log_actividad`; `logout.php` revoca ambas cookies y el token en BD.
- [x] Confirmado que `login.php` (frontend) sirve el checkbox "Mantenerme registrado" (`#login-recordarme`), el toggle de contraseña y el formulario con los IDs correctos que `main.js` espera.

### Fase 8 — Dashboard de Administración: Navegación Global, RBAC Dinámico y Núcleo Cognitivo (2026-07-17, quinta pasada — dirección "Productor Tzunum")
- [x] **`knowledge/` NO fue tocado** — prohibición absoluta explícita en `CLAUDE.md` del proyecto, sin excepción por instrucción de sesión.
- [x] `database/create_table_permisos_modulos.sql` — Mapeo Dinámico de Permisos (MODULO_01 §6.1), migrada y sembrada (`usuarios`→admin habilitado, `seguridad`→admin deshabilitado por defecto, igual al comportamiento previo).
- [x] `database/create_table_frase_bienvenida_diaria.sql` — persistencia diaria de la cápsula motivacional (MODULO_01 §10.4), migrada.
- [x] **Decisión explícita de diseño:** no existe ningún proveedor de IA "AURA" contratado (sin endpoint ni API key en `.env`). En vez de fabricar esa integración (Mandamiento 4, Anti-Alucinación), la cápsula usa un banco curado de 8 frases con selección determinística por día del año, persistida con `PRIMARY KEY(fecha)` — mismo resultado funcional (frase nueva solo una vez al día), sin inventar un servicio inexistente. Documentado como decisión reversible: si se contrata un proveedor real, solo cambia `generarOSeleccionarFraseDelDia()`.
- [x] `api/auth_helpers.php` — `esModuloVisible()`, `obtenerFraseBienvenidaDelDia()`, `bancoFrasesBienvenida()`.
- [x] `api/permisos_modulos.php` (GET matriz efectiva del actor; POST exclusivo `super_admin`) e `api/bienvenida.php` (GET frase del día, autenticado).
- [x] `api/usuarios_crear.php`, `api/usuarios_invitar.php`, `api/configuracion_seguridad.php` — ahora respetan la matriz dinámica para `admin` (super_admin siempre puede, sin excepción — "Soberanía del Super Admin").
- [x] **Bug real encontrado y corregido:** `SQLSTATE[HY093] Invalid parameter number` en `configuracion_seguridad.php` y `permisos_modulos.php` — con `PDO::ATTR_EMULATE_PREPARES=false` (preparados nativos), MySQL no permite reutilizar el mismo placeholder con nombre en `VALUES` y `ON DUPLICATE KEY UPDATE` de la misma query. Corregido con placeholders `_update` separados en ambos archivos. Encontrado probando el `POST` real (antes solo se había probado el `GET`).
- [x] `dashboard.php` — bloque de bienvenida AURA (saludo, rol, ubicación, frase), toggle Día/Noche, botón scroll-to-top, secciones `usuarios`/`seguridad`/`permisos` ahora gateadas server-side por la matriz real (no solo por rol fijo), descripciones pedagógicas en ambos métodos de alta, panel exclusivo de `super_admin` para la matriz de permisos (ARF-Grid, `.policy-card`).
- [x] Toggle Día/Noche y scroll-to-top también añadidos a `login.php`, `setup-genesis.php`, `invitacion.php`, `recuperar-password.php`, `restablecer-password.php` (clase `auth-body` en `<body>`). El sitio público (`index.php`, `club-lectura.html`) se dejó **fuera de alcance a propósito** — su paleta oscura fija es identidad de marca ("Trinchera Nocturna"), no un tema conmutable; cambiarla sin pedirlo habría contradicho la Regla de Oro.
- [x] Verificado con `php -l` (11 archivos) y `node --check` — sin errores. Probado end-to-end por HTTP con 3 cuentas de prueba temporales (creadas y eliminadas en esta misma sesión): `admin` ve solo `#usuarios` (matriz default); `super_admin` ve las 3 secciones; `admin` no puede mutar `permisos_modulos` (403) ni `configuracion_seguridad` hasta que `super_admin` habilita el módulo vía la matriz — momento en que sí puede (200); frase del día persistida una sola vez (`SELECT` confirmó 1 fila). Estado final de BD verificado: 1 solo usuario (la cuenta raíz real), matriz revertida a sus defaults.

### Fase 9 — Pendiente (no construido en esta pasada, requiere decisión adicional)
- [ ] Verificación end-to-end del flujo completo de recuperación de contraseña vía correo real (requiere revisar bandeja de entrada) e invitación con asignación de rol.
- [ ] Conectar un proveedor de IA real para la cápsula motivacional si el proyecto llega a contratar uno (hoy: banco estático, ver Fase 8).
- [ ] Extender el toggle Día/Noche al sitio público si el Arquitecto decide que la marca sí debe soportarlo (hoy: fuera de alcance a propósito).
- [ ] Rate limiting a nivel de IP agregado (hoy el tarpitting es por cuenta de usuario; un rate-limit adicional por IP pura requeriría una tabla o store nuevo — no se construyó sin autorización explícita adicional).
- [ ] Notificación de dispositivo/IP nuevos al usuario (fuera de alcance — requiere plantilla de correo adicional, no solicitada).
- [ ] `APP_SECRET`/`JWT_SECRET` real en `.env` — el device hash usa `JWT_SECRET` si existe, o `DB_PASS` como respaldo; `.env` no se modificó (está en la lista de "nunca se modifica sin autorización" del `CLAUDE.md` del proyecto). Añadir `JWT_SECRET` a la `.env` real queda pendiente de que el Arquitecto lo autorice/genere.
