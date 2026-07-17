# Checklist de Ejecución — UX, Contraste e Internacionalización (séptima pasada)

**Relación:** Continuación de [`CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md`](CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md), [`CHECKLIST_EJECUCION_CMS_EFECTO_AJEDREZ.md`](CHECKLIST_EJECUCION_CMS_EFECTO_AJEDREZ.md) y [`CHECKLIST_EJECUCION_CRM_EFECTO_AJEDREZ.md`](CHECKLIST_EJECUCION_CRM_EFECTO_AJEDREZ.md). Dirección "Productor Tzunum", 2026-07-17.

---

## 🐛 Bug crítico real encontrado y corregido en esta misma pasada

Al hacer `ciudad`/`estado` de `registro_interesados` nullable (necesario porque el formulario ya no los solicita), el ledger de "Invitados Confirmados" en `dashboard.php` **crasheaba con un error fatal** (`htmlspecialchars(): Argument #1 must be of type string, null given`) en cuanto había un registro con geolocalización no resuelta (ej. pruebas en `localhost`). Corregido con `?? '—'` antes de escapar. Detectado por prueba real end-to-end, no por inspección de código — confirma la importancia de probar con datos reales tras cada cambio de esquema.

## ✅ Checklist de esta pasada

1. **Enlace roto** — ya resuelto en la pasada anterior vía puente `club-lectura.html` (meta-refresh + JS), verificado `200` de nuevo. **No se tocó el `.htaccess` raíz** pese a que el mandato lo sugería como primera opción — el puente ya cumple "cero 404" sin tocar un archivo protegido (`CLAUDE.md` Sección 10) sin autorización del Arquitecto real.
2. **Vocabulario:** "Alta Directiva" → "Usuarios", "Editar Canvas" → "Edición de página" (nav + título de panel + botón).
3. **Bug de contraste real diagnosticado y corregido:** `.dash-topbar` tenía `background-color: rgba(16,20,30,0.92)` fijo — el texto sí conmutaba de color con el tema, pero el fondo nunca cambiaba, causando texto invisible en modo claro. Corregido a `var(--ajedrez-bg-elevated)`. Añadido `color` explícito a `.aura-welcome__saludo` (antes dependía solo de herencia).
4. **Laboratorio de logos embebido** en `dashboard.php` (`#logos`, exclusivo `super_admin`) vía `<iframe>` a `api/logo_test.php` — mismo toggle Día/Noche, mismo fondo del Dashboard.
5. **Registro de Ingreso** — `log_actividad` extendida con `ip`/`ip_pais`/`ip_estado`/`ip_ciudad` (nullable), poblada **solo** en `login_exitoso` (nunca en fallidos, por diseño — evita amplificación de fuerza bruta contra el proveedor de geolocalización). Tabla nueva en el panel Usuarios, exclusiva `super_admin`.
6. **Botón "Enviar Correo de Prueba a Staging"** — nuevo endpoint `api/staging_test_invitacion.php` que despacha la plantilla real **sin tocar la tabla `usuarios`** (a diferencia de la pasada anterior, que creaba y luego borraba una cuenta de prueba). Probado: `correo_enviado` exitoso.
7. **Modal público recortado** a Nombre/Correo/Edad — Ciudad/Estado eliminados del formulario, ahora se derivan 100% de la IP server-side. Validación estricta verificada con 3 casos reales: nombre puramente numérico → rechazado; correo basura → rechazado; registro válido sin ciudad/estado → aceptado.
8. **Aislamiento de vistas del Dashboard** — ya implementado en la pasada anterior (`data-panel-target`, todas las secciones `hidden` por defecto); re-verificado tras el fix del bug crítico: las 6 secciones (`usuarios`, `landing`, `invitados`, `seguridad`, `permisos`, `logos`) confirmadas ocultas al cargar.
9. **i18n (3 banderas) y 2 botones exactos** en `club-lectura.php` — ya construidos en la pasada anterior, re-verificados presentes.
10. `php -l` / `node --check` sin errores en los 8 archivos tocados. Migraciones ejecutadas: `alter_table_log_actividad_add_geo.sql`, `alter_table_registro_interesados_ciudad_estado_nullable.sql`. BD limpia al cierre: 1 usuario real, 0 interesados de prueba (la bitácora de login, por diseño append-only, conserva las entradas de las pruebas — no es purgable ni por mí).
