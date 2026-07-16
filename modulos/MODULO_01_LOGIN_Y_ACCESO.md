# MODULO_01_LOGIN_Y_ACCESO — Ley Suprema de Autenticación

**Clasificación:** Módulo Genérico de Arquitectura y Diseño Técnico | **Versión:** 3.0 (Blindaje Perimetral + Fricción Cero)
**Alcance:** Documento agnóstico, reutilizable por cualquier proyecto de `{{HOLDING_NAME}}`. Ningún nombre de proyecto, cliente o dominio real debe aparecer aquí — sustituir siempre por `{{PROJECT_NAME}}`, `{{TABLE_PREFIX}}`, etc.
**Relación con v2.0:** Este documento es el **blueprint técnico ejecutable** (SQL → PHP → Frontend). La v2.0 (To-Do list + checklist de madurez enterprise) permanece como anexo de gobernanza al final de este archivo — úsala para auditar qué tan lejos del "ideal enterprise" está la implementación concreta que este v3.0 describe.

> ⚠️ **Mandamiento de Secuencia SQL→PHP:** ningún endpoint de este módulo se escribe en un proyecto consumidor sin que el Schema Maestro (Sección 1) exista primero, palabra por palabra, como script `.sql` versionado en `/database` de ese proyecto. Ningún nombre de columna se traduce libremente — el Codex del proyecto consumidor es la única fuente de verdad para el mapeo final `{{PLACEHOLDER}}` → nombre real.

---

## 1. 🗄️ SCHEMA MAESTRO DE AUTENTICACIÓN (SQL PRIMERO)

### 1.1 Tabla `{{TABLE_PREFIX}}usuarios`

```sql
CREATE TABLE `{{TABLE_PREFIX}}usuarios` (
    `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre`            VARCHAR(120)        NOT NULL,
    `email`             VARCHAR(190)        NOT NULL,
    `password_hash`     CHAR(60)            NOT NULL COMMENT 'BCrypt cost=12 — password_hash(PASSWORD_BCRYPT, ["cost" => 12])',
    `rol`               ENUM('admin','editor','usuario') NOT NULL DEFAULT 'usuario',
    `estatus`           ENUM('activo','pendiente','suspendido') NOT NULL DEFAULT 'pendiente',
    `token_acceso`      CHAR(64)            NULL     COMMENT 'Hex de 256 bits (random_bytes(32)) — token opaco de sesión activa',
    `token_expira_en`   DATETIME            NULL,
    `device_hash`       CHAR(64)            NULL     COMMENT 'SHA-256(IP + User-Agent) del dispositivo vinculado (Device Binding)',
    `intentos_fallidos` SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    `bloqueado_hasta`   DATETIME            NULL     COMMENT 'Tarpitting — NULL si no hay bloqueo activo',
    `hito_cumplido`     TINYINT(1)          NOT NULL DEFAULT 0 COMMENT 'Fricción Cero Gate — 1 solo cuando el API confirma el primer hito/cliente cerrado',
    `creado_en`         DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_{{TABLE_PREFIX}}usuarios_email` (`email`),
    KEY `idx_{{TABLE_PREFIX}}usuarios_token` (`token_acceso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notas de diseño:**
- `password_hash` almacena **siempre** el resultado de `password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12])` — nunca MD5/SHA1, nunca texto plano.
- `token_acceso` es un token **opaco**, no un JWT auto-contenido, para permitir revocación inmediata en base de datos (ver Sección 3).
- `hito_cumplido` es el flag que consulta el "Fricción Cero Gate" del frontend (Sección 3.4) — nunca se infiere en el cliente, solo el API lo determina.
- Ninguna columna nueva se agrega a esta tabla en un proyecto consumidor sin pasar primero por el Mandamiento 9 (Inmutabilidad del Sistema) de ese proyecto.

### 1.2 Tabla `{{TABLE_PREFIX}}log_actividad` (Bitácora Append-Only)

```sql
CREATE TABLE `{{TABLE_PREFIX}}log_actividad` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id`     BIGINT UNSIGNED     NULL COMMENT 'NULL si el intento falló antes de resolver el usuario (anti-enumeración)',
    `evento`         VARCHAR(60)         NOT NULL COMMENT 'login_exitoso | login_fallido | logout | token_refresh | bloqueo_temporal',
    `ip_hash`        CHAR(64)            NOT NULL COMMENT 'SHA-256 de la IP — nunca IP en claro (privacidad)',
    `device_hash`    CHAR(64)            NOT NULL,
    `detalle`        VARCHAR(255)        NULL COMMENT 'Mensaje técnico genérico. JAMÁS credenciales ni tokens.',
    `creado_en`      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_{{TABLE_PREFIX}}log_usuario` (`usuario_id`),
    KEY `idx_{{TABLE_PREFIX}}log_evento_fecha` (`evento`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Triggers de inmutabilidad (append-only real, no solo por convención de aplicación):**

```sql
DELIMITER $$

CREATE TRIGGER `trg_{{TABLE_PREFIX}}log_no_update`
BEFORE UPDATE ON `{{TABLE_PREFIX}}log_actividad`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Bitácora append-only: UPDATE prohibido sobre log_actividad.';
END$$

CREATE TRIGGER `trg_{{TABLE_PREFIX}}log_no_delete`
BEFORE DELETE ON `{{TABLE_PREFIX}}log_actividad`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Bitácora append-only: DELETE prohibido sobre log_actividad.';
END$$

DELIMITER ;
```

**Notas de diseño:**
- Los triggers convierten el "append-only" en una garantía de motor de base de datos, no en una promesa de la capa PHP — ni siquiera una cuenta con privilegios elevados comprometida puede alterar el historial sin `DROP TRIGGER` explícito (evento auditable aparte).
- `ip_hash`/`device_hash` nunca se loguean en claro — se calculan igual que `device_hash` en `usuarios` (Sección 3.3), permitiendo correlación sin exponer PII directamente en la bitácora.
- La purga/archivado de bitácora antigua (retención) es una decisión de producto del proyecto consumidor — no se automatiza aquí sin autorización explícita.

---

## 2. 🛡️ PATRÓN CANÓNICO BLINDADO DE ENDPOINTS (6 CAPAS)

Aplica a `login.php`, `logout.php`, `token_refresh.php` y cualquier endpoint de acceso derivado. **Las 6 capas se ejecutan siempre en este orden — ninguna capa posterior se alcanza si una anterior falla.**

```
Request HTTP
   │
   ▼
┌─────────────────────────────────────────────────────────┐
│ CAPA 1 — CORS estricto                                   │
│  · Origen leído de ALLOWED_ORIGINS (.env), nunca "*"      │
│  · OPTIONS (preflight) → 204 inmediato, sin tocar capas   │
│    siguientes                                             │
│  · Origen no permitido → 403 y fin de ejecución            │
└─────────────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────────────┐
│ CAPA 2 — Middleware de autenticación / rol / estatus       │
│  · logout.php y token_refresh.php exigen sesión válida     │
│  · estatus = 'suspendido' → 403 inmediato (bloqueo          │
│    preventivo, sin importar validez del token)              │
│  · login.php es el único endpoint que NO exige sesión       │
│    previa (es el punto de entrada)                          │
└─────────────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────────────┐
│ CAPA 3 — Restricción explícita de método HTTP               │
│  · Toda mutación (login/logout/refresh) → POST exclusivo    │
│  · Cualquier otro verbo → 405 Method Not Allowed             │
│    (header Allow: POST incluido en la respuesta)             │
└─────────────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────────────┐
│ CAPA 4 — Lectura y sanitización estricta de payload         │
│  · Content-Type debe ser application/json (si no → 415)     │
│  · json_decode estricto, UTF-8, JSON_THROW_ON_ERROR          │
│  · Sanitización nativa (filter_var, trim, validación de      │
│    tipo/longitud por campo)                                  │
│  · Cualquier campo inválido/faltante → 422 inmediato con      │
│    detalle de qué campo falló (nunca detalle de credenciales) │
└─────────────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────────────┐
│ CAPA 5 — Interfaz segura de persistencia (PDO)               │
│  · PDO::ATTR_EMULATE_PREPARES = false (previene SQLi nativo) │
│  · PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION                │
│  · 100% prepared statements con binding explícito             │
│    ($stmt->bindValue(':email', $email, PDO::PARAM_STR))       │
│  · Cero interpolación de variables en la cadena SQL            │
└─────────────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────────────┐
│ CAPA 6 — Try/Catch global de infraestructura                 │
│  · Envuelve TODA la lógica de negocio de las capas 4-5        │
│  · catch (PDOException $e): log interno con SQLSTATE +         │
│    mensaje técnico (error_log, jamás al frontend)               │
│  · catch (Throwable $e): mismo tratamiento genérico             │
│  · Respuesta al cliente SIEMPRE en el Contrato de Respuesta:    │
│    { "status": "error", "message": "...", "data": null }         │
│    con HTTP 500 — nunca se expone traza, SQLSTATE o nombre       │
│    de tabla/columna al frontend                                   │
└─────────────────────────────────────────────────────────┘
   │
   ▼
Respuesta JSON { status, message, data }
```

### 2.1 Contrato de Respuesta (obligatorio en las 6 capas)

```json
{
  "status": "success | error",
  "message": "Texto genérico, seguro de mostrar al usuario final",
  "data": { }
}
```

### 2.2 Prevención de fuerza bruta y timing attacks (transversal a Capas 4-6)

- **Anti-enumeración:** si el email no existe, ejecutar igualmente `password_verify()` contra un hash BCrypt "dummy" precalculado — el tiempo de respuesta debe ser indistinguible entre "usuario no existe" y "contraseña incorrecta". Mensaje de error siempre: `"Credenciales inválidas."`.
- **Tarpitting progresivo:** cada `login_fallido` incrementa `intentos_fallidos`; al superar el umbral (`{{MAX_INTENTOS}}`, ej. 5), se escribe `bloqueado_hasta = NOW() + INTERVAL {{MINUTOS_BLOQUEO}} MINUTE` y se retorna 429 con mensaje genérico, sin revelar el umbral exacto.
- **Rate limiting multivectorial:** el límite se evalúa por `device_hash` **y** por `ip_hash` de forma independiente — un atacante rotando IP sigue frenado por el device hash, y viceversa.
- **Reset de contador:** `intentos_fallidos = 0` únicamente tras un `login_exitoso` verificado en Capa 5.

---

## 3. 🔑 PROTOCOLO DE TOKENS, SEGURIDAD DE SESIÓN Y DISPOSITIVOS

### 3.1 Elección de esquema: Token Opaco (recomendado) vs. JWT

| Criterio | Token Opaco Hex 256-bit (default) | JWT Enterprise |
| :--- | :--- | :--- |
| Revocación inmediata | ✅ Nativa (DELETE en `token_acceso`) | ⚠️ Requiere blocklist adicional |
| Superficie de ataque | Menor (no auto-contiene claims) | Mayor (`alg:none`, confusión de claves) |
| Complejidad de infraestructura | Baja — una columna + índice | Media — firma, rotación de claves |
| Uso recomendado | Backend monolítico con sesión centralizada (**default de este módulo**) | Arquitecturas con múltiples microservicios que no comparten DB de sesión |

**Generación del token opaco (obligatoria por default):**

```php
$token = bin2hex(random_bytes(32)); // 256 bits, criptográficamente seguro — nunca uniqid(), rand() ni md5(time())
```

### 3.2 Cookies de sesión

- `HttpOnly` — obligatorio siempre (bloquea acceso vía `document.cookie`, mitiga XSS de robo de sesión).
- `SameSite=Lax` — default (protege contra CSRF en navegación cruzada manteniendo usabilidad de enlaces entrantes). Usar `Strict` solo si el flujo de negocio no requiere entrada desde enlaces externos.
- `Secure` — obligatorio en producción (`{{APP_ENV}} === 'production'`); en local/XAMPP sobre HTTP puede omitirse solo en `{{APP_ENV}} === 'local'`.
- `session_regenerate_id(true)` en cada login exitoso — mitiga session fixation.

### 3.3 Device Binding (IP + User-Agent)

```php
$deviceHash = hash('sha256', $ipAddress . '|' . $userAgent . '|' . {{APP_SECRET}});
```

- Se calcula en cada request autenticado y se compara contra `usuarios.device_hash`.
- **Mismatch de device_hash con token válido** → tratar como posible secuestro de sesión: invalidar el token inmediatamente, registrar `evento = 'device_mismatch'` en la bitácora, forzar nuevo login.
- El hash incluye `{{APP_SECRET}}` (pimienta del proyecto consumidor, en `.env`) para que no sea reproducible fuera del sistema.
- Cambios legítimos de red (IP dinámica de ISP/VPN) son un trade-off conocido — el proyecto consumidor decide si notifica al usuario en vez de bloquear duro (ver v2.0, Fase 4/7).

### 3.4 Mandamiento de Blindaje Fiscal por Cartera (Fricción Cero Gate)

Regla de negocio genérica: **ningún formulario de datos sensibles o fiscales del perfil se desbloquea en el frontend sin confirmación determinística del backend.**

- El frontend renderiza el formulario sensible **siempre**, pero bajo un overlay `glassmorphism` (`backdrop-filter: blur(...)`, fondo semitransparente) con un candado visual y mensaje explicativo.
- El overlay se retira **exclusivamente** cuando una llamada al API (ej. `GET /api/estado_hito.php`) responde `{"status":"success","data":{"hito_cumplido": true}}`, reflejando `usuarios.hito_cumplido = 1` en DB.
- **Prohibido** desbloquear el overlay por estado local, flag de frontend, o "optimismo" tras una acción del usuario — siempre se re-consulta el API. Esto evita que un usuario manipule `localStorage`/DevTools para saltarse el gate.
- El endpoint que marca `hito_cumplido = 1` es responsabilidad del módulo de negocio consumidor (fuera de alcance de este módulo de login), pero **la lectura** del flag sí vive en el contrato de este módulo de autenticación.

---

## 4. 🎨 ARQUITECTURA VISUAL DE ACCESO (REGLA DE ORO Y ARF-GRID)

### 4.1 Variables de tema (declarar en `:root` de la hoja de estilos raíz del proyecto consumidor)

```css
:root {
    --auth-bg: {{COLOR_FONDO_OSCURO}};        /* Estudio nocturno — ej. #10141E */
    --auth-accent: {{COLOR_ACENTO_NEON}};     /* Acento vibrante — ej. #E91E63 */
    --auth-text: {{COLOR_TEXTO_PRINCIPAL}};   /* Contraste WCAG >= 4.5:1 sobre --auth-bg */
    --auth-text-muted: {{COLOR_TEXTO_SECUNDARIO}};
    --auth-radius: {{RADIO_BORDE_BASE}};
}
```

- Prohibido cualquier color hardcodeado fuera de estas variables dentro de los componentes de este módulo.
- El proyecto consumidor mapea estas variables a su propia paleta (`--ajedrez-*`, `--{{PROJECT_NAME}}-*`, etc.) sin renombrar la semántica (`bg`, `accent`, `text`, `text-muted`).

### 4.2 Mobile-First

- Contenedor principal del formulario de acceso: sin `width`/`height` fijos en `px`. Usar `%`, `clamp()`, `vh`/`vw` o las variables de diseño ya declaradas (`--container-max`, etc.) del proyecto consumidor.
- Inputs y botones con áreas táctiles ≥ 44×44px (accesibilidad móvil), definidas por `padding` relativo, no por `width`/`height` absolutos.

### 4.3 ARF-Grid (si existen módulos repetitivos: alternativas de login, badges de seguridad, selector de rol, etc.)

```css
.auth-arf-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1rem; /* o variable de espaciado del proyecto consumidor */
}

.auth-arf-grid__item {
    flex: 1 1 240px;     /* fluido — nunca ancho fijo en px */
    max-width: 320px;
    aspect-ratio: 1 / 1; /* o el ratio que defina el diseño */
    transition: transform 0.18s ease, box-shadow 0.18s ease; /* atómico, sin reflow */
}

.auth-arf-grid__item:hover {
    transform: translateY(-4px); /* transform/opacity/box-shadow únicamente */
}
```

- `justify-content: center` es obligatorio para que la última fila incompleta no quede desalineada.
- Los efectos `:hover` se limitan a `transform`, `opacity` y `box-shadow` sobre el `aspect-ratio` ya establecido — cualquier propiedad que dispare reflow (`width`, `height`, `margin`, `top/left` sin `position` ya fijado) queda prohibida en estados interactivos.

### 4.4 Regla de Oro de Estilos

- **Cero** `style="..."` inline en HTML/JS generado por este módulo.
- **Cero** `!important` — cualquier conflicto de especificidad se resuelve reordenando la cascada o aumentando especificidad de forma limpia (ej. anidando el selector), nunca forzando.
- Toda regla vive en la hoja de estilos centralizada del proyecto consumidor (no se crean archivos `.css` nuevos por módulo salvo autorización explícita).

### 4.5 Invariabilidad UI/UX ante fallos críticos (frontend)

Contrato de estado obligatorio para el script de login del proyecto consumidor:

```javascript
const authState = {
    halted: false, // true = irreversible para esta carga de página
};

function haltAuthFlow(message) {
    authState.halted = true; // se fija ANTES de cualquier otra operación de DOM

    // Pinta el error en un contenedor DEDICADO y persistente — nunca en un toast/snackbar efímero
    const errorBox = document.getElementById('auth-error-container');
    errorBox.textContent = message;
    errorBox.hidden = false;

    // El botón de reintento/retorno debe quedar operativo de forma infalible:
    // se re-habilita explícitamente, sin depender de que el resto del flujo funcione.
    const retryBtn = document.getElementById('auth-retry-btn');
    retryBtn.disabled = false;
}

// En cualquier función transaccional (submit, refresh, etc.):
function submitLogin(payload) {
    if (authState.halted) {
        return; // ninguna función transaccional se ejecuta una vez halted = true
    }
    // ... lógica normal ...
}
```

- Una vez `authState.halted = true`, **ninguna** función transaccional (submit, refresh silencioso, reintentos automáticos) puede ejecutarse de nuevo en esa carga de página — solo una recarga completa o una acción explícita de "Reintentar" gestionada por el propio `haltAuthFlow` puede limpiar el estado.
- El contenedor de error dedicado (`#auth-error-container`) es persistente en el DOM (no desaparece solo); se oculta únicamente cuando el usuario reintenta con éxito.
- El botón de retorno/reintento se habilita **antes** de cualquier `return` temprano de la función que detectó el fallo, garantizando que el usuario nunca quede con la UI congelada sin salida.

---

## 5. 📱 ARQUITECTURA DEL DASHBOARD UNIVERSAL (MOBILE-FIRST 90+)

### 5.1 Estructura semántica base

```html
<div class="dash-shell" data-dash-shell>
    <header class="dash-topbar">
        <button type="button" class="dash-topbar__burger" id="dash-burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="dash-nav">
            <span class="dash-topbar__burger-line"></span>
            <span class="dash-topbar__burger-line"></span>
            <span class="dash-topbar__burger-line"></span>
        </button>
        <span class="dash-topbar__brand">{{PROJECT_NAME}}</span>
    </header>

    <nav class="dash-nav" id="dash-nav" data-dash-nav aria-hidden="true">
        <ul class="dash-nav__list">
            <li><a href="#" class="dash-nav__link">{{NAV_ITEM_1}}</a></li>
            <li><a href="#" class="dash-nav__link">{{NAV_ITEM_2}}</a></li>
            <!-- ...tantos ítems como el proyecto consumidor requiera -->
        </ul>
    </nav>

    <div class="dash-nav-backdrop" data-dash-nav-close></div>

    <main class="dash-content">
        <!-- contenido de cada vista del dashboard -->
    </main>
</div>
```

### 5.2 Menú hamburguesa — lógica nativa (sin dependencias externas)

```javascript
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
```

### 5.3 Reglas visuales de adaptabilidad (obligatorias)

- **Contenedores fluidos:** `.dash-content`, `.dash-shell` y cualquier wrapper de nivel superior usan `width: 100%` + `max-width` en variable de diseño — nunca `px` fijo.
- **Control de desbordamiento:** todo bloque con contenido potencialmente ancho (tablas, bloques de código, imágenes) se envuelve en `overflow-x: auto` propio — el `<body>` nunca desarrolla scroll horizontal.
- **Transiciones atómicas del menú:** el panel `.dash-nav` se anima solo con `transform: translateX(...)` y `opacity` sobre un `position: fixed` ya establecido — nunca animando `width`/`left`/`margin` (evita reflow, mantiene 90+ en Lighthouse mobile).
- **Backdrop del menú:** `.dash-nav-backdrop` fijo, `opacity` 0→1 con `pointer-events` condicionado, igual que el patrón de lightbox/modal ya usado en este módulo (consistencia de patrones dentro del mismo sistema).
- **Áreas táctiles:** el botón hamburguesa y todo ítem de `.dash-nav__link` mantienen un área mínima de 44×44px vía `padding`, nunca vía `width`/`height` fijos.

---

## 6. 👑 MATRIZ DE ROLES Y JERARQUÍA EXTENSIBLE

| Rol | Nivel | Alcance |
| :--- | :---: | :--- |
| `super_admin` | 100 | Acceso total. Único rol que puede: provisionar/revocar `admin`, ver la bitácora completa sin filtros, ejecutar acciones irreversibles de sistema. Es el usuario creado por el First-Run Provisioning (Sección 7) — **solo puede existir uno por instancia**, salvo que el proyecto consumidor documente explícitamente lo contrario en su Codex. |
| `admin` | 80 | Gestor operativo. Puede dar de alta usuarios (Sección 8, Métodos A y B), gestionar `roles_variables`, pero **no** puede modificar ni revocar a un `super_admin`. |
| `{{ROL_VARIABLE_1}}` … `{{ROL_VARIABLE_N}}` | `{{NIVEL}}` (< 80) | Espacio extensible — el proyecto consumidor define aquí sus roles de negocio (`operador`, `soporte`, `moderador`, etc.) y su alcance de permisos, registrándolos en su propio Codex antes de usarlos en código (Soberanía de Nomenclatura). |

**Reglas de jerarquía:**
- La comparación de permisos siempre es **por nivel numérico**, nunca por cadena de texto (`if ($usuario->nivel_rol >= 80)`), para que agregar un rol variable no rompa condicionales existentes.
- Un rol nunca puede otorgar ni modificar un rol de nivel igual o superior al propio (`admin` no puede crear otro `admin` con más nivel del que él mismo tiene, ni auto-promoverse).
- El campo `rol` de la tabla `{{TABLE_PREFIX}}usuarios` (Sección 1.1) se extiende de `ENUM('admin','editor','usuario')` a `ENUM('super_admin','admin','{{ROL_VARIABLE_1}}', ...)` **solo** cuando el proyecto consumidor haya definido su lista real de roles variables en su Codex — este cambio de ENUM es, en sí mismo, una alteración de schema y requiere la misma autorización explícita que cualquier otra (Mandamiento 9).

---

## 7. 🚀 LOOP DE PRIMER ARRANQUE: CONFIGURACIÓN GÉNESIS (FIRST-RUN PROVISIONING)

### 7.1 Detección de estado "vacío"

```php
function sistemaRequiereProvisioning(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM {{TABLE_PREFIX}}usuarios');
    return (int) $stmt->fetchColumn() === 0;
}
```

- Esta verificación se ejecuta en el punto de entrada del sistema (bootstrap/router), **antes** de resolver cualquier otra ruta.
- Si `sistemaRequiereProvisioning() === true`, toda ruta que no sea la de inicialización redirige (302) a `{{RUTA_INICIALIZACION}}` (ej. `/setup-genesis.php`).
- Si es `false`, `{{RUTA_INICIALIZACION}}` responde 410 Gone de forma permanente (Sección 7.4) — nunca 404 (un 404 sugiere "quizá exista en otra parte"; un 410 comunica "existió, ya no").

### 7.2 Formulario público temporal — política de contraseña de nivel militar

Campos: `nombre`, `email`, `password`, `password_confirmacion`.

Política de contraseña (validada en Capa 4 — Sanitización, antes de llegar a Capa 5):
- Longitud mínima **14 caracteres** (no 8 — el estándar mínimo de este módulo para la cuenta raíz del sistema).
- Al menos 1 mayúscula, 1 minúscula, 1 dígito, 1 símbolo.
- Rechazo explícito si coincide con listas de contraseñas comprometidas conocidas (integración opcional con un servicio de *pwned passwords* — si el proyecto consumidor no tiene ese servicio disponible, se documenta como pendiente, no se omite en silencio).
- `password === password_confirmacion` se valida en el **backend**, nunca solo en el frontend.

### 7.3 Mutación de creación — doble función (provisioning + healthcheck)

```php
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO {{TABLE_PREFIX}}usuarios (nombre, email, password_hash, rol, estatus)
         VALUES (:nombre, :email, :password_hash, :rol, :estatus)'
    );
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
    $stmt->bindValue(':rol', 'super_admin', PDO::PARAM_STR);
    $stmt->bindValue(':estatus', 'activo', PDO::PARAM_STR);
    $stmt->execute();

    $pdo->commit();
    // Éxito = validador determinístico de que PDO y la integridad física de la BD
    // están operando al 100%. No se requiere un endpoint de healthcheck separado
    // para esta verificación puntual — la propia mutación lo demuestra.
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[GENESIS_PROVISIONING] ' . $e->getCode() . ' — ' . $e->getMessage());
    // Respuesta genérica de Capa 6 — nunca detalle de $e al frontend
}
```

### 7.4 Auto-deshabilitación de la ruta

- Inmediatamente después del `COMMIT` exitoso, cualquier request subsecuente a `{{RUTA_INICIALIZACION}}` debe evaluar `sistemaRequiereProvisioning()` de nuevo y responder **410 Gone** — la ruta no se "elimina" del código (no hay redeploy), se **autodesactiva por estado de datos**, que es la única fuente de verdad.
- No debe existir una variable de entorno o flag de config que reabra esta ruta — la única forma de volver a provisionar es un estado de BD verdaderamente vacío (ej. entorno de desarrollo reseteado), nunca una reactivación manual en producción.

---

## 8. 🔄 FLUJO DUAL DE INVITACIÓN Y ALTA DE USUARIOS

### 8.1 Método A — Creación Directa

- Endpoint (ej. `usuarios_crear.php`) exige rol `admin` o superior (Capa 2).
- Campos: `nombre`, `email`, `password` (el propio administrador la define o el sistema genera una temporal — decisión de producto del proyecto consumidor).
- `estatus = 'activo'` inmediato — sin paso intermedio.
- Caso de uso: administrador que da de alta a alguien presencialmente o por un canal ya verificado fuera de banda.

### 8.2 Método B — Invitación Segura por Plantilla

**Paso 1 — Alta en `pendiente` + token de invitación**

Requiere una tabla adicional (sujeta a autorización explícita antes de crearse en un proyecto consumidor real, igual que cualquier tabla nueva — Mandamiento 9):

```sql
CREATE TABLE `{{TABLE_PREFIX}}invitaciones` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id`      BIGINT UNSIGNED NOT NULL,
    `token_hash`      CHAR(64)        NOT NULL COMMENT 'SHA-256 del token — el token en claro solo viaja en el email, nunca se persiste',
    `expira_en`       DATETIME        NOT NULL,
    `usado`           TINYINT(1)      NOT NULL DEFAULT 0,
    `creado_en`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_{{TABLE_PREFIX}}invitaciones_token_hash` (`token_hash`),
    KEY `idx_{{TABLE_PREFIX}}invitaciones_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```php
$tokenClaro = bin2hex(random_bytes(32));
$tokenHash  = hash('sha256', $tokenClaro);
// INSERT usuarios (nombre, email, estatus='pendiente', password_hash=NULL)
// INSERT invitaciones (usuario_id, token_hash, expira_en = NOW() + INTERVAL {{TTL_INVITACION}} HOUR)
// El enlace firmado ({{RUTA_INVITACION}}?token=$tokenClaro) viaja SOLO por la
// plantilla de correo transaccional — nunca se muestra en pantalla ni se loguea.
```

**Paso 2 — Vista standalone de aceptación**

- Ruta pública temporal por invitación (ej. `/invitacion.php?token=...`), sin sesión previa.
- Valida: `hash('sha256', $tokenRecibido) === token_hash` **y** `expira_en > NOW()` **y** `usado = 0` — las tres condiciones, sin excepción.
- Formulario: `password`, `password_confirmacion` (misma política de nivel militar de la Sección 7.2).
- Comparación de tokens con `hash_equals()` (comparación segura contra timing attacks), nunca `===` directo sobre el hash.

**Paso 3 — Confirmación y activación**

```php
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE {{TABLE_PREFIX}}usuarios SET password_hash = :hash, estatus = :estatus WHERE id = :id'
    );
    $stmt->bindValue(':hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PDO::PARAM_STR);
    $stmt->bindValue(':estatus', 'activo', PDO::PARAM_STR);
    $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare('UPDATE {{TABLE_PREFIX}}invitaciones SET usado = 1 WHERE id = :id');
    $stmt->bindValue(':id', $invitacionId, PDO::PARAM_INT);
    $stmt->execute();

    $pdo->commit();
    // El usuario queda 'activo' y puede autenticarse de inmediato en el Login
    // principal — no requiere paso adicional ni segunda confirmación por correo.
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[INVITACION_CONFIRM] ' . $e->getCode() . ' — ' . $e->getMessage());
}
```

- Una invitación usada (`usado = 1`) o expirada responde siempre el mismo mensaje genérico ("Este enlace ya no es válido"), sin distinguir la causa — mismo principio anti-enumeración de la Sección 2.2.
- El envío de la plantilla de correo transaccional (SMTP, proveedor, diseño del email) es responsabilidad de infraestructura del proyecto consumidor — este módulo define el contrato del token y el flujo, no el transporte de correo.

---

## 9. ✅ VALIDACIÓN DE CONSISTENCIA DEL FLUJO

| # | Verificación | Cubierto en |
| :--- | :--- | :---: |
| 1 | Prevención de SQLi (prepared statements + `ATTR_EMULATE_PREPARES=false`) | §2 Capa 5 |
| 2 | Anti-enumeración de usuarios (tiempo de respuesta uniforme) | §2.2 |
| 3 | Anti-fuerza bruta (tarpitting + rate limiting multivectorial) | §2.2 |
| 4 | Revocación inmediata de sesión (token opaco vs. JWT) | §3.1 |
| 5 | Mitigación de secuestro de sesión (Device Binding) | §3.3 |
| 6 | Protección de cookies contra XSS/CSRF (`HttpOnly`, `SameSite`, `Secure`) | §3.2 |
| 7 | Bitácora inmutable a nivel de motor (triggers `SIGNAL SQLSTATE`) | §1.2 |
| 8 | Cero fugas de información técnica al frontend (Capa 6 + Contrato de Respuesta) | §2 Capa 6, §2.1 |
| 9 | UI sin reflow en estados interactivos (ARF-Grid + transform/opacity) | §4.3 |
| 10 | UI resiliente ante fallo crítico (estado irreversible + contenedor persistente) | §4.5 |
| 11 | Fricción Cero Gate no manipulable desde el cliente | §3.4 |

**Declaración de blindaje:** el flujo descrito no presenta lagunas lógicas conocidas entre capas — cada capa del patrón de 6 capas asume que la anterior ya validó su responsabilidad y no repite validaciones ya cubiertas, pero tampoco confía en el frontend para ninguna decisión de seguridad (el Fricción Cero Gate, el device binding y el tarpitting se resuelven siempre server-side).

---

## 📎 ANEXO — Checklist Operativo Heredado (v2.0)

> Se conserva como capa de gobernanza y madurez enterprise. Úsalo para auditar, en un proyecto consumidor concreto, qué tan cerca está de las Fases 1-7 y del checklist de cierre original. No sustituye las Secciones 1-5 de este documento, que son el blueprint técnico obligatorio.

### Fases de referencia (resumen)
1. **Estructuración y Capa de Datos** — múltiples métodos de auth, gestión de sesiones, Codex actualizado antes de codificar.
2. **Interfaz y Accesibilidad** — autocompletado nativo, indicador de carga in-button, a11y WCAG ≥ 4.5:1.
3. **Autenticación Adaptativa** — evaluación de riesgo, step-up auth condicional, verificación de contraseñas comprometidas.
4. **Sesiones y Continuidad** — device binding, autenticación continua, renovación silenciosa, invalidación remota.
5. **Seguridad Perimetral** — rate limiting multivectorial, tarpitting, anti-enumeración, mensajes de error estandarizados.
6. **Recuperación de Cuenta** — TTL corto en tokens de recuperación, invalidación de enlaces antiguos, notificaciones de seguridad.
7. **Telemetría y Auditoría** — registro de todo intento de acceso, nunca contraseñas en logs, metadata no intrusiva.

### Regla de aplicación
Cada ítem `[~]`/`[ ]` de este anexo que implique una tabla nueva o cambio de arquitectura de sesión **debe pasar primero por la Regla de Oro de Base de Datos y por confirmación explícita del Arquitecto** del proyecto consumidor — este módulo es una guía de nivel enterprise a la que aspirar, no una orden de ejecución automática completa.
