# FUENTE DE VERDAD CONSOLIDADA
## [NOMBRE_DEL_PROYECTO] | DCD LABS / VECTOR_CERO — Bóveda Madre de Andamiaje

> Este documento es el índice maestro de gobernanza. No describe un proyecto
> comercial activo: describe el estado de la plantilla genérica (machote) de
> la cual se clona todo desarrollo nuevo. Al clonar, reemplazar todos los
> marcadores `[NOMBRE_DEL_PROYECTO]`, `{{PROJECT_NAME}}`, `{{HOLDING_NAME}}`
> y completar `CLAUDE.md` con los datos reales del nuevo proyecto.

---

## 1. MODELO DE 4 CAPAS INMUTABLES

| Capa | Componentes | Estado |
| :--- | :--- | :--- |
| **LAYER_0 — Foundation Security** | `cors.php`, `jwt.php` (Access/Refresh + Device Binding), `auth_middleware.php`, `auth_login.php`, `auth_refresh.php`, `helpers/input_sanitizer.php`, `validators/validator.php`, `.htaccess` (HTTPS forzado, ServerSignature Off, cabeceras) | ✅ |
| **LAYER_1 — Foundation Data** | `conexion.php` (PDO `ATTR_EMULATE_PREPARES=false` + `ERRMODE_EXCEPTION`, host remoto forzado por Regla Cero), `helpers/response.php` (Response Contract `status/message/data`) | ✅ |
| **LAYER_2 — Foundation Observability** | `helpers/asfl_logger.php` (AXON Synaptic Flow Ledger, solo `APP_ENV=local`), `api/status_check.php` (Triple Handshake: FS / DB remota / SMTP 465) | ✅ |
| **LAYER_3 — Foundation UX / Protocolo Móvil 90+** | `index.html`, `assets/css/main.css` (ARF-Grid, `--container-max`), `assets/js/main.js`, `favicon.ico`, `assets/img/logo.svg`, `<picture>` + `loading="lazy"` + `defer` + `preload` | ✅ |
| Knowledge Base (`knowledge/00`–`07`) | ✅ Purgada de rastros de clientes anteriores (auditoría forense 2026-06-24) |
| Schema de Base de Datos | ⬜ Vacío — definir en `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md` al iniciar proyecto real (incluye tabla `users` que esperan `auth_login.php`/`auth_refresh.php`) |
| Scripts de arranque (`scripts/*`) | ✅ `bootstrap_project.sh`, `generate_env.php`, `generate_jwt_keys.php`, `install_permissions.php` — bloqueados en `.htaccess` y excluidos del deploy FTP |

## 2. REGLA CERO — AISLAMIENTO DE ENTORNOS

El desarrollo es local (`http://localhost/[NOMBRE_PROYECTO]`), pero la Base de
Datos **NUNCA** es local. `DB_HOST` en `.env.example` y el fallback en
`conexion.php` apuntan a `[HOST_BD_REMOTO_DEL_HOSTING]` — placeholder a
sustituir con el host remoto real del proveedor de hosting del proyecto
clonado. Jamás usar `localhost` o `127.0.0.1` como `DB_HOST`, ni en desarrollo,
y jamás reutilizar el host remoto de un proyecto anterior.

Esta carpeta nunca se despliega a producción como proyecto comercial.
Es el origen de `git clone` / copia para cada nuevo desarrollo de DCD LABS.

## 3. PENDIENTE DE AUTORIZACIÓN EXPLÍCITA (Mandamiento #9)

- Tabla `refresh_tokens_blacklist` para revocación real de sesiones (logout
  forzado / "cerrar sesión en todos los dispositivos"). La rotación actual de
  `auth_refresh.php` es stateless: no invalida el refresh anterior en servidor.
- Tabla `users` (id, email, password_hash, role) que consumen `auth_login.php`
  y `auth_refresh.php`. No se crea aquí — definir en el Codex al clonar.

## 4. CHECKLIST DE CLONACIÓN (al iniciar un proyecto nuevo)

1. Copiar la carpeta completa a `C:\xampp\htdocs\[NUEVO_PROYECTO]\`.
2. Completar `CLAUDE.md` §1 (Identidad del Proyecto) con datos reales.
3. Crear `.env` real a partir de `.env.example` (nunca commitear).
4. Inicializar repositorio Git y configurar GitHub Secrets para `deploy.yml`.
5. Definir el schema real en `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md`.
6. Ejecutar `api/status_check.php` para validar el Triple Handshake en el
   nuevo entorno antes de escribir el primer endpoint de negocio.
7. Pasar el scanner perimetral AXON DCD antes de cualquier salida a producción
   (Mandamiento #18).

## 5. REFERENCIAS

- Manual operativo del agente: [`CLAUDE.md`](CLAUDE.md)
- Mandamientos y protocolos: [`knowledge/01_LEY_Y_PROTOCOLOS_DE_VUELO.md`](knowledge/01_LEY_Y_PROTOCOLOS_DE_VUELO.md)
- Codex y schema maestro: [`knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md`](knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md)
- Contratos de API: [`knowledge/03_CONTRATOS_API_Y_RUTAS.md`](knowledge/03_CONTRATOS_API_Y_RUTAS.md)

## 6. BLUEPRINTS GENÉRICOS REUTILIZABLES (`modulos/`)

Además de los 4 capas inmutables (Sección 1), el holding acumula blueprints de arquitectura por dominio funcional, derivados de proyectos reales pero purgados a `{{PROJECT_NAME}}` genérico — listos para consultar o clonar en el siguiente proyecto sin repetir el diseño desde cero:

- [`modulos/MODULO_01_LOGIN_Y_ACCESO.md`](modulos/MODULO_01_LOGIN_Y_ACCESO.md) — autenticación por token opaco, RBAC dinámico, Dashboard mobile-first (acordeón + conmutador de paneles + jerarquía de widgets Acción→Estado→Historial), contrato de respuesta limpio, entregabilidad anti-SPAM universal, ledgers de auditoría paginados.
- [`modulos/MODULO_02_CMS_EDICION_VISUAL.md`](modulos/MODULO_02_CMS_EDICION_VISUAL.md) — motor de edición visual inline sobre páginas públicas.
- [`modulos/MODULO_03_CRM_EVENTOS_EN_VIVO.md`](modulos/MODULO_03_CRM_EVENTOS_EN_VIVO.md) — captación pública de interesados con unicidad de correo, orquestación de sesiones en vivo (planeador unificado), Sala de Check-In, husos horarios consolidados en un solo componente.

Estos documentos son **agnósticos** (Ley Suprema de este archivo) — al clonar, sustituir sus marcadores igual que los de este índice maestro.

## 7. LIBERACIÓN DE `knowledge/` PARA MANTENIMIENTO VIVO (2026-07-16)

La restricción original que bloqueaba por completo la lectura/edición de la carpeta `knowledge/` quedó acotada exclusivamente al archivo humano confidencial `knowledge/info.txt` — los pilares `00`-`07` vuelven a ser mantenibles por el agente como Documentación Viva (Mandamiento 17), que es su propósito original. El bloqueo HTTP vía `.htaccess` (nadie accede a `knowledge/` desde el navegador) y la exclusión de Git (`.gitignore`) se mantienen intactos — esto es exclusivamente sobre permisos de lectura/edición del agente en el filesystem local.

## 8. ESTADO VERIFICADO — HITO DE ACCESOS Y EVENTOS EN VIVO (2026-07-16)

Sincronización confirmada entre `modulos/` (blueprints agnósticos) y `knowledge/` (pilares canónicos del proyecto real) para el hito de Login/Dashboard/RBAC y CRM de Eventos en Vivo:

| Pieza | Verificado en código real | Registrado en `knowledge/` |
| :--- | :---: | :---: |
| Registro de Ingreso — exclusivo `super_admin` (UI oculta + backend `403`) | ✅ | ✅ `03_CONTRATOS_API_Y_RUTAS.md` |
| `api/registro_ingreso_listar.php` / `api/registro_ingreso_eliminar.php` | ✅ | ✅ `03_CONTRATOS_API_Y_RUTAS.md` |
| `api/sesiones_compartir.php` — Planeador Live unificado (`multipart/form-data`) | ✅ | ✅ `02_CODEX_Y_SCHEMA_MAESTRO.md`, `03_CONTRATOS_API_Y_RUTAS.md` |
| `api/registro_interesado.php` — unicidad de correo silenciosa | ✅ | ✅ `03_CONTRATOS_API_Y_RUTAS.md` |
| `api/email_helper.php` — dinamización nombre+correo, escala tipográfica, footer sin placeholder | ✅ | ✅ `02_CODEX_Y_SCHEMA_MAESTRO.md`, `03_CONTRATOS_API_Y_RUTAS.md` |
| Dashboard — jerarquía Planeador Live → KPIs → Historial | ✅ | ✅ `07_UI_MODULOS_Y_PANTALLAS.md` |
| `formatearFechaMazatlan()` — formato "Jul 16, 2026, 08:31 pm" (mes-día-año, 2 dígitos, am/pm minúsculas) | ✅ | ✅ `02_CODEX_Y_SCHEMA_MAESTRO.md` |
| Footer anti-SPAM — "La Paz, Baja California Sur, México" confirmado por el Arquitecto | ✅ | ✅ `02_CODEX_Y_SCHEMA_MAESTRO.md` |
| Backfill histórico completo (schema/contratos previos a la liberación de `knowledge/`) | — | ⬜ Pendiente, tarea dedicada aparte (ver nota de alcance en `02_CODEX_Y_SCHEMA_MAESTRO.md` y `03_CONTRATOS_API_Y_RUTAS.md`) |
