# 📒 02 — SYSTEM CODEX REGISTRY (LIBRO DE CONTROL UNIFICADO)
**Plantilla:** DCD LABS / AXON_DCD | **Versión:** 2.0 | **Tipo:** Genérica Multi-Proyecto

> **LEY DEL CODEX:** Este archivo es la única fuente de verdad del sistema. La IA Ejecutora registra aquí **de forma inmediata y autónoma** cada artefacto que crea (variable, tabla, columna, endpoint, componente). Un artefacto no registrado no existe oficialmente. Un hito no está cerrado si sus artefactos no aparecen aquí.

---

## 🗄️ SECCIÓN 1 — REGISTRO DE TABLAS DE BASE DE DATOS

> Instrucción: Añade una fila por cada tabla creada. Nunca elimines filas — usa el campo `Estado` para marcar tablas deprecadas.

| Nombre de Tabla | Módulo | Propósito Técnico | Columnas Clave | Fecha de Alta | Validador |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `[nombre_tabla]` | `[Módulo]` | `[Qué almacena y para qué]` | `id, created_at, [otras]` | `[YYYY-MM-DD]` | `[IA / Arquitecto]` |

### Convenciones Globales de Tablas
- `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `created_at`: `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
- `updated_at`: `TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- `deleted_at`: `TIMESTAMP NULL` (soft-delete — preferido sobre DELETE físico)
- Charset: `utf8mb4` | Collation: `utf8mb4_unicode_ci` | Motor: `InnoDB`

---

## 📡 SECCIÓN 2 — REGISTRO DE ENDPOINTS PHP

> Instrucción: Un endpoint = una fila. El nombre del archivo es el identificador canónico.

| Archivo (Endpoint) | Método HTTP | Módulo | Propósito | Auth Requerida | Fecha de Alta | Validador |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `api/[nombre].php` | `[GET/POST/PUT/DELETE]` | `[Módulo]` | `[Qué hace este endpoint]` | `[Sí (JWT) / No]` | `[YYYY-MM-DD]` | `[IA / Arquitecto]` |

### Estructura de Respuesta Estándar (Inamovible)
```json
{
  "status": "success | error",
  "message": "Descripción legible del resultado",
  "data": []
}
```

### Estructura de Payload Esperado (POST / PUT)
```json
{
  "[propiedad_snake_case]": "[tipo: string | int | bool | array]"
}
```

---

## 🔑 SECCIÓN 3 — REGISTRO DE VARIABLES DE ENTORNO (`core/.env`)

> Instrucción: Toda variable que se añada a `core/.env` debe registrarse aquí. Las contraseñas reales **nunca** se escriben en este archivo.

| Variable | Tipo | Módulo | Propósito | Valor en `.env.example` | Fecha de Alta |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `APP_ENV` | String | Global | Entorno activo | `"production"` | `[YYYY-MM-DD]` |
| `APP_URL` | String | Global | URL base del sistema | `"https://[URL_PRODUCCION]"` | `[YYYY-MM-DD]` |
| `DB_HOST` | String | Backend / DB | Host de base de datos | `"localhost"` | `[YYYY-MM-DD]` |
| `DB_NAME` | String | Backend / DB | Nombre de la base de datos | `"[NOMBRE_BASE_DE_DATOS]"` | `[YYYY-MM-DD]` |
| `DB_USER` | String | Backend / DB | Usuario de base de datos | `"[USUARIO_DB]"` | `[YYYY-MM-DD]` |
| `DB_PASS` | String | Backend / DB | Contraseña de base de datos | `"[CONTRASEÑA_DB_AQUI]"` | `[YYYY-MM-DD]` |
| `JWT_SECRET` | String | Auth | Clave de firma JWT HS256 | `"[SECRETO_JWT_256_BITS_AQUI]"` | `[YYYY-MM-DD]` |
| `ALLOWED_ORIGINS` | String | CORS | Lista blanca de orígenes separada por coma | `"http://localhost,https://[URL_PRODUCCION]"` | `[YYYY-MM-DD]` |
| `SMTP_HOST` | String | Email | Host SMTP | `"[smtp.dominio.com]"` | `[YYYY-MM-DD]` |
| `SMTP_USER` | String | Email | Correo remitente | `"[correo@dominio.com]"` | `[YYYY-MM-DD]` |
| `SMTP_PASS` | String | Email | Contraseña SMTP | `"[CONTRASEÑA_SMTP_AQUI]"` | `[YYYY-MM-DD]` |
| `SMTP_PORT` | Int | Email | Puerto SMTP | `465` | `[YYYY-MM-DD]` |
| `SMTP_SECURE` | String | Email | Protocolo de cifrado | `"ssl"` | `[YYYY-MM-DD]` |

---

## 📊 SECCIÓN 4 — MAPEO DE VARIABLES (FRONT ↔ BACK)

> Regla: `snake_case` en DB/PHP. `camelCase` en JS/React. Sin excepciones.

| Concepto de Negocio | DB / PHP (`snake_case`) | Frontend JS (`camelCase`) | Tipo de Dato |
| :--- | :--- | :--- | :--- |
| `[Concepto 1]` | `[variable_back]` | `[variableFront]` | `[String / Int / Bool / Timestamp / Array]` |
| `[Concepto 2]` | `[variable_back]` | `[variableFront]` | `[String / Int / Bool / Timestamp / Array]` |

### 📡 DICCIONARIO SFL — Synaptic Flow Ledger (Telemetría Interna)

> **Protocolo:** SFL | **Leyes de origen:** Mandamiento 19 + Mandamiento 21 — `01_LEY_Y_MANDAMIENTOS.md`  
> **Alcance:** Solo capa servidor (`APP_ENV=local` / `APP_ENV=staging`). **PROHIBIDO** en payloads de cliente y en `APP_ENV=production`.

| Concepto de Negocio | DB / PHP (`snake_case`) | Frontend JS (`camelCase`) | Tipo de Dato | Notas de Uso |
| :--- | :--- | :--- | :--- | :--- |
| Latencia de red del módulo | `network_latency_ms` | `networkLatencyMs` | `INT UNSIGNED` | Tiempo de ida y vuelta de red en milisegundos. Solo log interno. |
| Estatus de consulta PDO | `db_query_status` | `dbQueryStatus` | `ENUM('ok','error','timeout')` | Estado de la consulta a base de datos. Solo log interno. |
| Payload de entrada del módulo | `synaptic_input_payload` | `synapticInputPayload` | `JSON TEXT` | Copia serializada del payload recibido. Solo log interno. Nunca exponer al cliente. |
| Tokens de IA en tránsito | `tokens_in_flight` | `tokensInFlight` | `INT UNSIGNED` | Conteo de tokens despachados al modelo de IA activo. Solo log interno. |

**Términos Permitidos (SFL):** `network_latency_ms`, `db_query_status`, `synaptic_input_payload`, `tokens_in_flight`, `SFL`, `Synaptic Flow Ledger`  
**Términos Prohibidos (SFL):** Cualquier marca comercial externa, alias propietario o traducción libre de estas variables.

---

## 🧩 SECCIÓN 5 — REGISTRO DE COMPONENTES FRONTEND

> Instrucción: Un componente = una fila. Incluye tanto componentes React/Next.js como módulos JS vanilla.

| Componente / Módulo | Ruta de Archivo | Tipo | Estado | Props / Dependencias Principales |
| :--- | :--- | :--- | :--- | :--- |
| `[NombreComponente]` | `app/src/components/[NombreComponente].tsx` | `[UI / Logic / Page / Layout]` | `[Active / WIP / Deprecated]` | `[prop1: tipo, prop2: tipo]` |

---

## 🧠 SECCIÓN 6 — VOCABULARIO CONTROLADO (SEMÁNTICA DE NEGOCIO)

> Instrucción: La IA Ejecutora registra aquí cada nombre técnico que asigna. El Arquitecto propone en lenguaje natural; la IA decide el nombre técnico final.

| Concepto de Negocio (Natural) | Nombre Técnico Oficial | Capa | Notas de Uso |
| :--- | :--- | :--- | :--- |
| `[Descripción en lenguaje humano]` | `[nombre_tecnico_oficial]` | `[DB / API / Frontend]` | `[Restricciones o contexto de uso]` |

**Términos Permitidos:** `[termino_1]`, `[termino_2]`  
**Términos Prohibidos:** `[sinonimo_1]`, `[traduccion_libre]`

---

## ✅ SECCIÓN 7 — CHECKLIST DE INFRAESTRUCTURA INICIAL (PRE-DEPLOY)

> Este checklist se ejecuta **obligatoriamente** antes de todo despliegue a producción. No se omite ningún punto.

### Fundación de Seguridad
- [ ] `core/.env` creado con credenciales reales de producción.
- [ ] `core/.env` confirmado como **NO rastreado** por Git (`git status` limpio).
- [ ] `core/.env.example` con placeholders seguros — publicado en el repositorio.
- [ ] `.htaccess` activo en raíz — bloquea `core/`, `knowledge/`, `logs/`, `*.env`, `*.sql`, `*.md`.
- [ ] `.gitignore` protege `core/.env`, `logs/*.log`, `node_modules/`, `out/`, `vendor/`.

### Validación de Conexión (OBLIGATORIO)
- [ ] **`core/tools/test_connections.php` ejecutado y devuelve estado `200 OK`** con conexión PDO activa.
- [ ] El script confirma que `PDO::ATTR_EMULATE_PREPARES` está en `false`.
- [ ] El script confirma que las cabeceras CORS responden correctamente al origen de producción.

### Pipeline CI/CD
- [ ] `.github/workflows/deploy.yml` presente y configurado con `server-dir: ./`.
- [ ] GitHub Secrets configurados: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.
- [ ] El bloque `exclude` del pipeline omite: `core/.env`, `knowledge/`, `logs/`, `*.md`, `*.sql`, `CLAUDE.md`.
- [ ] Deploy de prueba ejecutado en rama `staging` antes del push a `main`.

### Codex y Documentación
- [ ] Todas las tablas creadas registradas en **Sección 1** de este archivo.
- [ ] Todos los endpoints creados registrados en **Sección 2** de este archivo.
- [ ] Todas las variables de entorno registradas en **Sección 3** de este archivo.
- [ ] `CLAUDE.md` actualizado con el estado actual del proyecto.
- [ ] Informe de Operación emitido al Arquitecto.

### Seguridad Final
- [ ] Ningún endpoint de mutación (POST/PUT/DELETE) sin validación JWT activa.
- [ ] Ninguna salida dinámica HTML sin `htmlspecialchars()`.
- [ ] Ningún `var_dump()`, `print_r()` o `error_log()` visible en producción.
- [ ] Cabeceras de seguridad HTTP validadas: `X-Frame-Options`, `X-Content-Type-Options`, `HSTS`.

---

## 📁 SECCIÓN 8 — REGISTRO DE ARCHIVOS DE INFRAESTRUCTURA

| Artefacto | Ruta | Estado | Notas |
| :--- | :--- | :--- | :--- |
| `.htaccess` | `.htaccess` | `[Pendiente / Activo]` | Blindaje Apache raíz |
| `.gitignore` | `.gitignore` | `[Pendiente / Activo]` | Protección del repositorio |
| `.env.example` | `core/.env.example` | `[Pendiente / Activo]` | Plantilla pública — sí en Git |
| `conexion.php` | `core/src/conexion.php` | `[Pendiente / Activo]` | PDO centralizado + CORS + logging |
| `jwt.php` | `core/src/jwt.php` | `[Pendiente / N/A]` | Utilidad JWT HS256 sin dependencias |
| `auth_middleware.php` | `core/src/auth_middleware.php` | `[Pendiente / N/A]` | Validación Bearer JWT + RBAC |
| `test_connections.php` | `core/tools/test_connections.php` | `[Pendiente / Activo]` | Validador pre-deploy — obligatorio |
| `deploy.yml` | `.github/workflows/deploy.yml` | `[Pendiente / Activo]` | CI/CD FTP a producción |
| `logs/` | `logs/` | `[Pendiente / Activo]` | Directorio de logs — `backend.log` |
