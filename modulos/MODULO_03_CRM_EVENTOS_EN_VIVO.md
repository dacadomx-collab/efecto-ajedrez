# MODULO_03_CRM_EVENTOS_EN_VIVO — Captación de Interesados y Orquestación de Sesiones en Vivo

**Clasificación:** Módulo Genérico de Arquitectura y Diseño Técnico | **Versión:** 1.0
**Alcance:** Documento agnóstico, reutilizable por cualquier proyecto de `{{HOLDING_NAME}}`. Ningún nombre de proyecto, cliente, dominio o comunidad real debe aparecer aquí — sustituir siempre por `{{PROJECT_NAME}}`, `{{MODULE_NAME}}`, `{{PAGINA_ID}}`, `{{TABLE_PREFIX}}`.
**Dependencia:** Requiere [`MODULO_01_LOGIN_Y_ACCESO.md`](MODULO_01_LOGIN_Y_ACCESO.md) (sesión, roles, Mapeo Dinámico de Permisos) y, si la página pública ya es editable, [`MODULO_02_CMS_EDICION_VISUAL.md`](MODULO_02_CMS_EDICION_VISUAL.md).

---

## 1. 🗄️ SCHEMA MAESTRO

```sql
CREATE TABLE `{{TABLE_PREFIX}}registro_interesados` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre`     VARCHAR(120) NOT NULL,
    `edad`       TINYINT UNSIGNED NULL,
    `ciudad`     VARCHAR(120) NOT NULL COMMENT 'Dato declarado por el visitante en el formulario',
    `estado`     VARCHAR(120) NOT NULL COMMENT 'Dato declarado por el visitante en el formulario',
    `ip`         VARCHAR(45)  NOT NULL COMMENT 'IPv4/IPv6 en claro — visible solo en el panel admin, nunca en respuestas públicas',
    `ip_pais`    VARCHAR(80)  NULL COMMENT 'Resuelto server-side, best-effort — NULL si el proveedor de geolocalización no respondió',
    `ip_estado`  VARCHAR(80)  NULL,
    `ip_ciudad`  VARCHAR(80)  NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{{TABLE_PREFIX}}historial_sesiones` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fecha_hora`  DATETIME     NOT NULL,
    `enlace`      VARCHAR(255) NOT NULL COMMENT 'URL de la videollamada — nunca se expone en el HTML público fuera de la ventana de acceso',
    `tema`        VARCHAR(200) NULL COMMENT 'Libro/plática abordada',
    `creado_por`  INT UNSIGNED NULL COMMENT 'usuarios.id (MODULO_01) de quien compartió la sesión',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{{TABLE_PREFIX}}historial_sesiones_asistentes` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sesion_id`      INT UNSIGNED NOT NULL,
    `interesado_id`  INT UNSIGNED NOT NULL,
    `token_checkin`  CHAR(64)     NOT NULL COMMENT 'Token opaco de un solo uso por (sesión, interesado) — nunca reutilizable entre sesiones',
    `notificado_en`  DATETIME     NULL,
    `checkin_en`     DATETIME     NULL COMMENT 'NULL = interesado, nunca asistió. NOT NULL = asistencia real confirmada',
    UNIQUE KEY `uq_asistente_token` (`token_checkin`),
    UNIQUE KEY `uq_sesion_interesado` (`sesion_id`, `interesado_id`),
    CONSTRAINT `fk_asistente_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `{{TABLE_PREFIX}}historial_sesiones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_asistente_interesado` FOREIGN KEY (`interesado_id`) REFERENCES `{{TABLE_PREFIX}}registro_interesados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notas de diseño:**
- `interés inicial` (fila en `registro_interesados`) y `asistencia real` (`checkin_en` no nulo en `historial_sesiones_asistentes`) son conceptos **deliberadamente separados** — la tasa de conversión interés→asistencia es la métrica que el `super_admin` audita (Sección 5).
- El enlace de la sesión **nunca** se persiste en la página pública en texto plano permanente — se resuelve en runtime (Sección 3) solo dentro de la ventana de tiempo autorizada.

---

## 2. 🧲 CAPTACIÓN PÚBLICA DE INTERESADOS

- Formulario modal (glassmorphism, coherente con la Regla de Oro de Estilos) que solicita: nombre, edad, ciudad, estado — campos declarados por el visitante, nunca inferidos.
- Endpoint público (ej. `registro_interesado.php`) bajo el patrón de 6 capas de MODULO_01 §2. Capa 4 sanitiza y valida cada campo; Capa 5 persiste.
- **Resolución de IP → geografía:** best-effort, server-side, con un proveedor sin credenciales si el proyecto consumidor no tiene uno contratado (`{{GEOLOCATION_PROVIDER}}` — documentar en el Codex si se usa uno de pago). Un fallo de resolución **nunca** bloquea el registro — los campos `ip_pais`/`ip_estado`/`ip_ciudad` simplemente quedan `NULL` (Mandamiento 4: no fabricar una respuesta falsa).
- `{{USER_IP}}` se obtiene de `$_SERVER['REMOTE_ADDR']` — nunca de un header `X-Forwarded-For` sin validar (spoofable por el cliente) salvo que el proyecto consumidor esté detrás de un proxy/CDN de confianza documentado explícitamente en su Codex.
- **Validación inflexible de unicidad de correo:** antes de insertar, la Capa 5 verifica (`SELECT ... WHERE email = :email LIMIT 1`) si el correo ya existe en `{{TABLE_PREFIX}}registro_interesados`. Si ya existe, el `INSERT` se omite silenciosamente — pero la respuesta HTTP al cliente es **idéntica** a la de un registro exitoso nuevo (mismo `status: success`, mismo mensaje). Esta es una decisión deliberada de anti-enumeración (mismo principio de MODULO_01 §2.2): revelar "este correo ya está registrado" le permitiría a un tercero usar el formulario público para confirmar si una dirección específica pertenece a alguien de la comunidad.
- **Confirmación de participación como gate client-side de UI pública:** al recibir `status: success`, el frontend marca localmente (ej. `localStorage`) que el visitante confirmó su interés — esta marca (no una sesión de `usuarios` del Dashboard, que no aplica a un visitante público) es la que habilita, más adelante, la revelación del botón/enlace de acceso a la Sala de Check-In (Sección 3.2) en la misma página pública. Es un gate de experiencia, no de seguridad real — la autorización real de acceso sigue viviendo enteramente en el backend (ventana de tiempo + token de Check-In).

---

## 3. 🎥 ORQUESTADOR DE SESIONES EN VIVO

### 3.1 Compartir sesión (acción del administrador) — Planeador Live unificado

- Endpoint de mutación (ej. `sesiones_compartir.php`), exige rol autorizado + Mapeo Dinámico de Permisos (módulo `{{MODULO_PERMISO_ID}}`, ej. `invitados`).
- **Una sola acción, un solo endpoint (MODULO_01 §5.3.3 — Sección A del Dashboard):** el operador no encadena "crear sesión" y luego "subir material" en dos formularios distintos — el widget del Planeador Live envía todo junto vía `multipart/form-data` en una sola petición: enlace (obligatorio, `FILTER_VALIDATE_URL`), tema (opcional), **fecha/hora programada** (opcional — si el administrador no la especifica, se calcula un valor de respaldo determinístico según la cadencia recurrente del proyecto consumidor; si la especifica, esa fecha manual siempre prevalece), **mensaje personalizado** (opcional, texto libre acotado en longitud, se inyecta escapado dentro del cuerpo del correo de notificación) y **material PDF** (opcional).
- Si se adjunta material, el mismo endpoint aplica la validación de archivo de la Sección 7 (MIME real, tamaño máximo, renombrado criptográfico) **dentro de la misma transacción** que crea la fila de `historial_sesiones` — si el archivo falla al guardarse, toda la operación se revierte (`ROLLBACK`), nunca queda una sesión creada sin su material o un archivo huérfano sin fila asociada.
- El endpoint standalone de solo-material (ej. `material_subir.php`) puede seguir existiendo en paralelo para el caso de agregar/reemplazar el PDF de una sesión ya compartida **sin** volver a notificar por correo a todos los interesados — son dos casos de uso distintos, no una duplicación accidental.
- Al ejecutarse: (1) crea la fila en `historial_sesiones` (y guarda el material si se adjuntó); (2) vincula a **todos** los interesados vigentes en `historial_sesiones_asistentes`, generando un `token_checkin` único por persona (`bin2hex(random_bytes(32))`); (3) dispara el correo transaccional individual con el enlace de Check-In (Sección 4) y el mensaje personalizado si se redactó uno — **nunca** el enlace crudo de la videollamada directamente en el correo, siempre el enlace de Check-In que a su vez resuelve y redirige.

### 3.2 Resolución pública del enlace vigente

- Endpoint de solo lectura (ej. `sesion_actual.php`), sin autenticación — pero **sí** con ventana de tiempo server-side: responde el enlace únicamente si `NOW()` está dentro de `[fecha_hora - {{MINUTOS_ANTICIPACION}}, fecha_hora + {{DURACION_SESION_MINUTOS}}]` de la sesión más reciente. Fuera de esa ventana, responde `enlace: null` — el mismo criterio que ya gobierna la UI del "Fricción Cero Gate" de sesiones en vivo, ahora aplicado server-side (no solo confiado al reloj del cliente).
- **Doble gate en la UI pública:** el botón/enlace de acceso a la Sala de Check-In (Sección 4) se revela en el frontend público solo cuando se cumplen **dos** condiciones a la vez — (1) la ventana de tiempo (arriba) y (2) la marca local de confirmación de participación (Sección 2, el gate de `localStorage` que se activa al enviar el modal de registro). Sin confirmar interés previamente, el visitante ve un texto instructivo de qué hacer ("Regístrate para desbloquear tu acceso"), nunca el botón de acceso — evita que alguien que nunca mostró interés reciba igualmente la invitación a entrar.

### 3.3 Consolidación de Husos Horarios (Landing Pública)

- Cuando el proyecto consumidor atiende a una audiencia repartida en varios husos horarios, **todos** los horarios regionales se agrupan dentro de un único componente/cuadro visual (ej. `{{TIMETABLE_PANEL}}`) — nunca dispersos en tarjetas separadas por región, que fragmentan la atención y ocupan espacio vertical innecesario en mobile.
- Cada fila del cuadro combina: bandera nativa del país (emoji, sin dependencias de iconografía externa), nombre de la región y horario local — todos como bloques editables independientes si la página consume el Motor de Edición Visual (MODULO_02), para que cada horario pueda actualizarse sin tocar código.
- El copy instructivo sobre la ventana de acceso ("El acceso a la sesión en vivo se habilita automáticamente {{MINUTOS_ANTICIPACION}} minutos antes de comenzar") se destaca como texto introductorio de la sección, inmediatamente antes del cuadro unificado — nunca enterrado al final o diluido entre otros textos.

---

## 4. 🚪 SALA DE CHECK-IN (LIVE GATE)

- Ruta pública dedicada (ej. `checkin.php?token=...`) — el único destino de los enlaces enviados por correo (Sección 3.1).
- Valida el `token_checkin` contra `historial_sesiones_asistentes` (`hash_equals` sobre el token si se compara manualmente; si se busca por `WHERE token_checkin = :token` con índice único, la comparación ya es exacta a nivel de motor).
- Si es válido y `checkin_en IS NULL`: lo marca con `NOW()` (primera y única vez que cuenta como asistencia — un segundo clic no debe re-registrar ni duplicar métricas) y redirige (`302`) al `enlace` real de `historial_sesiones`.
- Si el token no existe o ya fue usado hace mucho: mensaje genérico, nunca un error técnico — mismo principio anti-enumeración de MODULO_01 §2.2.
- **No es una redirección instantánea:** la Sala de Check-In renderiza una página propia con las acciones disponibles ("Ingresar a la sesión", y "Descargar material" si existe — Sección 7) — una redirección `302` inmediata nunca le daría al asistente la oportunidad de ver o usar la segunda acción.

---

## 5. 📊 LEDGER Y AUDITORÍA (DASHBOARD)

- Tabla de interesados: HTML semántico `<table>` (no ARF-Grid — ARF-Grid es para bloques repetitivos tipo tarjeta; un ledger tabular con múltiples columnas es más correcto y accesible como tabla real), envuelta en un contenedor con `overflow-x: auto` propio (mobile-first, MODULO_01 §5.3) para que el `<body>` nunca desarrolle scroll horizontal.
- Métrica de asistencia por sesión: `asistieron = COUNT(checkin_en IS NOT NULL)` / `notificados = COUNT(*)` sobre `historial_sesiones_asistentes` agrupado por `sesion_id` — expuesta al `super_admin` como tasa de conversión interés → asistencia real.
- **Economía visual — columnas de auditoría interna nunca en el viewport principal:** `ip`/`ip_pais`/`ip_estado`/`ip_ciudad` se siguen consultando y persistiendo en `{{TABLE_PREFIX}}registro_interesados` para auditoría interna (Sección 1), pero **no** se renderizan como columnas del ledger visible del panel — en pantallas móviles, columnas repetitivas de baja consulta cotidiana saturan la tabla y fuerzan scroll horizontal. Si el proyecto consumidor necesita auditar esos campos, se hace vía consulta directa a BD, no vía la tabla del Dashboard.
- Las tarjetas KPI resumidas (MODULO_01 §5.3.3, Sección B del Dashboard) resuelven la necesidad de "estado de un vistazo" que antes obligaba a abrir la tabla completa — el ledger tabular queda reservado para consulta detallada ocasional (Sección C).
- **Ledgers que crecen sin límite** (ej. Registro de Ingreso de MODULO_01 §9.3, o cualquier bitácora histórica de este módulo si el proyecto consumidor la expone en el Dashboard) siguen el patrón de paginación server-side + buscador + borrado selectivo/masivo documentado en MODULO_01 §9.6 — nunca un `SELECT *` sin `LIMIT` renderizado completo en una sola carga de página.

---

## 7. 📄 CARGA PROTEGIDA DE MATERIAL (PDF UPLOADER)

- `{{TABLE_PREFIX}}historial_sesiones` gana una columna `material_pdf_path` (`VARCHAR(255) NULL`) — un archivo por sesión, opcional.
- Endpoint de subida (ej. `material_subir.php`), exige rol autorizado + Mapeo Dinámico de Permisos, mismo patrón de validación de archivo que MODULO_02 §4.2: MIME real vía `finfo_file()` (aquí, whitelist única `application/pdf`), tamaño máximo (`{{TAMANO_MAXIMO_MB}}`), renombrado criptográfico, directorio de destino con su propio `.htaccess` que **deniega todo acceso directo** (`Require all denied`) — el archivo físico nunca es alcanzable por URL directa.
- Endpoint de descarga (ej. `material_descargar.php?token=...`) — la autorización **no** es una sesión de `usuarios` del Dashboard, es la prueba de asistencia real: exige `token_checkin` válido **y** `checkin_en IS NOT NULL` en `historial_sesiones_asistentes`. El interés inicial (solo `registro_interesados`) **nunca** es suficiente por sí solo — un lead que nunca asistió no puede descargar el material aunque haya recibido la invitación.
- Verificación adicional de *path traversal*: la ruta resuelta (`realpath()`) del archivo a servir debe permanecer dentro del directorio protegido — el valor de `material_pdf_path` en BD nunca se concatena y sirve a ciegas.
- El archivo se transmite (`readfile()`/equivalente) con `Content-Disposition: attachment` y un nombre de archivo genérico — nunca se revela el nombre original que subió el administrador.

---

## 8. ✅ VALIDACIÓN DE CONSISTENCIA

| # | Verificación | Cubierto en |
| :--- | :--- | :---: |
| 1 | Fallo de geolocalización nunca bloquea el registro del interesado | §2 |
| 2 | IP tomada de `REMOTE_ADDR`, nunca de un header spoofable sin proxy de confianza documentado | §2 |
| 3 | Enlace de videollamada nunca en el correo directo — siempre vía Check-In | §3.1 |
| 4 | Ventana de acceso resuelta server-side, no solo confiada al reloj del cliente | §3.2 |
| 5 | Check-in es idempotente — un segundo clic no duplica ni sobrescribe la asistencia ya registrada | §4 |
| 6 | Interés inicial y asistencia real son métricas separadas y auditable su brecha | §1, §5 |
| 7 | Descarga de material exige asistencia real confirmada, no solo interés inicial | §7 |
| 8 | Directorio de material sin acceso directo por URL — único camino es el endpoint autorizado | §7 |
| 9 | Un mismo correo nunca se duplica en `registro_interesados`; la respuesta es idéntica exista o no el duplicado | §2 |
| 10 | El botón de acceso a la Sala de Check-In exige ventana de tiempo **y** confirmación previa de participación | §3.2 |
| 11 | Ledgers de auditoría con crecimiento sin límite usan paginación server-side + búsqueda + borrado selectivo/masivo (MODULO_01 §9.6), nunca `SELECT *` sin `LIMIT` | §5 |
