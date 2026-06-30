# CLAUDE.md — Manual Operativo del Agente IA
## El Efecto Ajedrez: Mentores al Revés | DCD LABS / VECTOR_CERO
**Versión:** 1.1 (Génesis Élite v3) | **Fecha:** 2026-06-29 | **Arquitecto:** Paola Palomares

---

## 1. IDENTIDAD DEL PROYECTO

**Proyecto:** El Efecto Ajedrez: Mentores al Revés
**Cliente / Dueño:** Paola Palomares — Videopodcast de Crianza Positiva y Educación sin Violencia
**Objetivo:** Plataforma del videopodcast que traduce crianza positiva, ajedrez como metáfora pedagógica y educación sin violencia a una experiencia digital cálida, mobile-first y accesible para madres y padres en LatAm.
**Filosofía de operación:** "Colibrí siempre colibrí" — aportar una gota de agua a la vez para apagar el fuego de la violencia familiar. Playlist de anclaje emocional: "Tzunum 🌺 siempre Tzunum".
**Dominio de producción:** `https://[dominio].com` *(pendiente de definir con el Arquitecto)*
**Entorno local:** `C:\xampp\htdocs\El_Efecto_ajedrez_PODCAST\`
**Repositorio:** GitHub → rama `main` → auto-deploy vía GitHub Actions FTP

### Stack Tecnológico
- **Frontend:** HTML + CSS + JS nativo (mobile-first, sin frameworks pesados salvo autorización)
- **Backend:** PHP 8+ con `declare(strict_types=1)` obligatorio en todo archivo nuevo
- **Base de Datos:** MySQL/MariaDB remota (`chir205.websitehostserver.net`) vía PDO centralizado (`api/conexion.php`)
- **Servidor:** Apache/XAMPP local + hosting Tourfindy (producción)
- **Correo:** SMTP transaccional (`efecto-ajedrez.tourfindy.com:465`) — credenciales solo en `.env`
- **IA (si aplica):** N/A por ahora — definir antes de integrar (Mandamiento 12)

---

## 2. ESTRUCTURA DE CARPETAS (Estado Real)

```
El_Efecto_ajedrez_PODCAST/
├── index.php                         ← Punto de entrada principal — Home SEO/SEO-IA
├── favicon.ico
├── .htaccess                         ← Blindaje Apache Nivel Militar (bloquea knowledge/, database/, logs/, .github/)
├── .env                               ← Credenciales REALES (NUNCA en Git)
├── .env.example                       ← Plantilla pública (sí en Git)
├── .gitignore                         ← Protección del repositorio
├── CLAUDE.md                          ← Este archivo — manual del agente
├── FUENTEDEVERDAD_CONSOLIDADA.md       ← Índice maestro de gobernanza del andamiaje
│
├── api/                               ← Endpoints PHP públicos (todos blindados)
│   ├── conexion.php                   ← Clase `Database` — PDO centralizado, ATTR_EMULATE_PREPARES=false
│   ├── cors.php                       ← Gestor CORS — lee ALLOWED_ORIGINS desde .env
│   ├── captura_lead.php               ← Endpoint de captación de leads (patrón 6 capas)
│   ├── status_check.php               ← Triple Handshake — diagnóstico FS/DB/SMTP
│   └── logo_test.php                  ← Laboratorio de auditoría de marca (noindex, solo interno)
│
├── app/                               ← Vistas / lógica de frontend adicional
├── database/                          ← Scripts SQL `.sql` + runner (bloqueada en .htaccess, excepción de Git para *.sql)
│   ├── create_table_leads_captura.sql ← Ejecutado en producción 2026-06-29
│   └── run_migration.php              ← Runner CLI-only de migraciones
├── assets/
│   ├── css/main.css                   ← Única hoja de estilos — paleta `--ajedrez-*`, ARF-Grid, lead-form, logo-lab
│   ├── js/main.js                     ← Controlador del formulario de captación (fetch + estados UI)
│   └── img/                           ← `logo.png` + variantes en evaluación, `favicon.ico`
│
├── .github/workflows/deploy.yml       ← Pipeline CI/CD real — FTP a /public_html/ajedrez/ en push a main
├── logs/                              ← Logs del sistema (bloqueados en .htaccess)
│
└── knowledge/                         ← Memoria del sistema (bloqueada en .htaccess, NUNCA en Git)
    ├── 00_ADN_Y_FILOSOFIA.md
    ├── 01_LEY_Y_PROTOCOLOS_DE_VUELO.md
    ├── 02_CODEX_Y_SCHEMA_MAESTRO.md
    ├── 03_CONTRATOS_API_Y_RUTAS.md
    ├── 04_ARQUITECTURA_Y_BLINDAJE.md
    ├── 05_MATRIZ_FINANCIERA_Y_VENTAS.md
    ├── 06_NUCLEO_COGNITIVO_Y_PROMPTS.md
    ├── 07_UI_MODULOS_Y_PANTALLAS.md
    ├── Presentacion.html               ← Estrategia de mercado del podcast (FODA, tendencias, monetización)
    └── info.txt                        ← CONFIDENCIAL. PROHIBIDO leer, parsear o subir a Git.
```

> La carpeta `artifacts/` (plantilla de andamiaje AXON_GENESIS heredada, con un `deploy.yml` de referencia para un stack Next.js que no aplica a este proyecto) fue eliminada del filesystem local — nunca estuvo versionada en Git. El pipeline real y activo vive en `.github/workflows/deploy.yml`.

> Los pilares `00`–`07` siguen en estado de plantilla genérica (placeholders `{{PROJECT_NAME}}`) heredados del andamiaje AXON_GENESIS. Se personalizan progresivamente a medida que se definen módulos, schema y contratos reales de este proyecto — nunca se copian contenidos de otro proyecto del holding.

---

## 3. LOS 18 MANDAMIENTOS — LEY SUPREMA

Referencia completa: `knowledge/01_LEY_Y_PROTOCOLOS_DE_VUELO.md`

| # | Mandamiento | Resumen Ejecutivo |
| :--- | :--- | :--- |
| 1 | Mobile-First | Todo componente nace para celular. Sin anchos fijos (px) en contenedores. |
| 2 | Seguridad Nivel Militar | Sanitización + Prepared Statements. Blindaje SQLi, XSS, CSRF. |
| 3 | Modo Oscuro | Contraste mínimo WCAG 4.5:1. Tema fluido Light/Dark. |
| 4 | Anti-Alucinación | PROHIBIDO inventar variables. Si no está en el Codex, DETENERSE. |
| 5 | Contrato de API Estricto | No alterar propiedades JSON sin modificar el Contrato oficial. |
| 6 | Ejecución Determinística | Sin "mejoras" ni extensiones no solicitadas. |
| 7 | Naming Registry | `snake_case` backend/DB. `camelCase` frontend. |
| 8 | Dead Code | Auditoría de huérfanos antes de cada entrega. |
| 9 | Inmutabilidad del Sistema | No crear tablas ni alterar schema sin autorización explícita. |
| 10 | Sinónimos Prohibidos | Un solo nombre válido por concepto. Cero traducciones libres. |
| 11 | Arranque Blindado | Todo proyecto inicia con `.env`, `.htaccess` y conexión PDO. |
| 12 | **Bóveda de Secretos** | **PROHIBIDO hardcodear credenciales, tokens o API Keys. Todo en `.env`.** |
| 13 | Aislamiento de Entornos | Local NUNCA apunta a DB de producción. 3 entornos: Local/Staging/Prod. |
| 14 | CORS ≠ Auth | Todo endpoint POST/PUT/DELETE requiere autenticación real. Sin token = 401. |
| 15 | Agente Residente | Todo proyecto tiene `CLAUDE.md` actualizado. |
| 16 | CI/CD Inquebrantable | Deploy automático vía `deploy.yml`. Despliegue manual prohibido. |
| 17 | Documentación Viva | Módulo sin documentar = módulo no terminado. Hub de reportes obligatorio. |
| 18 | **Auditoría AXON DCD** | **Ningún proyecto a producción sin pasar el scanner perimetral AXON DCD.** |

---

## 4. REGLAS DE HIERRO — SEGURIDAD (INAMOVIBLES)

### 🚨 REGLA DE PROTECCIÓN LINGÜÍSTICA
- Este proyecto opera bajo el principio de Fricción Cero y terminología unificada.
- Prohibido inventar nombres de variables, endpoints o interfaces que generen duplicidades o confusión técnica/comercial.
- Utiliza la tabla de mapeo del Codex de este proyecto (`knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md`) como la única verdad arquitectónica.

### 🚨 ADVERTENCIA CONFIDENCIAL — `knowledge/info.txt`
- **PROHIBIDO** abrir, leer, parsear o incluir en cualquier commit el archivo `knowledge/info.txt` o la carpeta `knowledge/` completa.
- `knowledge/` ya está excluida vía `.htaccess` (bloqueo HTTP) y `.gitignore` (`/knowledge/` — nunca se versiona).

### PROHIBIDO absolutamente:
- Hardcodear contraseñas, API Keys, tokens, DSN de BD en cualquier archivo PHP o JS.
- Escribir credenciales en comentarios de código.
- Usar `require_once 'archivo.php'` sin `__DIR__` (rutas relativas simples).
- Usar `Access-Control-Allow-Origin: *` en endpoints que modifican datos.
- Modificar el `.htaccess` sin autorización explícita del Arquitecto.
- Crear nuevas tablas o alterar el schema de BD sin autorización explícita.
- Mostrar errores de PDO o PHP en el frontend (usar try/catch + logs).
- Usar `!important` en CSS (Regla ORO — ver §10).

### OBLIGATORIO siempre:
- Toda credencial: `getenv('NOMBRE_VARIABLE')` o `parse_ini_file()` desde el `.env`.
- Toda ruta PHP: `require_once __DIR__ . '/ruta/archivo.php'` — sin excepción.
- Toda conexión a BD: a través de `api/conexion.php` únicamente.
- Antes de generar código: verificar que variables existen en `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md`.
- Al detectar credenciales hardcodeadas: reportar y corregir inmediatamente.
- **SECUENCIA SQL→PHP:** ningún código PHP transaccional toca la BD sin que exista antes el script `CREATE TABLE` correspondiente en `/database` (evita errores 1054).

---

## 5. SOBERANÍA DE NOMENCLATURA

El Arquitecto (Paola Palomares) aporta ideas conceptuales en lenguaje natural. La traducción a nombres técnicos finales es responsabilidad exclusiva del agente:

- **Variables de frontend:** `camelCase`.
- **Columnas de BD / backend:** `snake_case`.
- **Endpoints:** `snake_case`, vivien en `/api/`.

Toda traducción nueva se registra de inmediato en `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md` (Mapeo de Variables Validadas) antes de usarse en código — nunca después.

---

## 6. COMPORTAMIENTO DEL AGENTE (MODO DE OPERACIÓN)

**Modo:** Determinístico. No creativo. No expansivo.

### Antes de escribir código:
1. Consultar `knowledge/03_CONTRATOS_API_Y_RUTAS.md` — respetar contratos de API existentes.
2. Verificar que las variables a usar están en `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md`.
3. Confirmar que no se alteran tablas de BD (Mandamiento 9).
4. Si el módulo toca BD: generar primero el script `.sql` en `/database`, luego el PHP (Secuencia SQL→PHP).

### Al terminar un módulo:
1. Actualizar `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md` con nuevas tablas o variables.
2. Actualizar `knowledge/03_CONTRATOS_API_Y_RUTAS.md` si se creó un nuevo endpoint.
3. Reportar al Arquitecto el estado del módulo.

---

## 7. PIPELINE CI/CD (GitHub Actions → FTP)

**Archivo activo:** `.github/workflows/deploy.yml`
**Repositorio:** `https://github.com/dacadomx-collab/efecto-ajedrez.git`
**Trigger:** Push a rama `main`
**Destino:** `/public_html/ajedrez/` (Absolute Remote Dir: `/home/tourfindycom/public_html/ajedrez`)

**GitHub Secrets requeridos** (Settings → Secrets → Actions):
| Secret | Contenido |
| :--- | :--- |
| `FTP_SERVER` | Servidor FTP del hosting |
| `FTP_USERNAME` | Usuario FTP |
| `FTP_PASSWORD` | Contraseña FTP (NUNCA en código) |

**Excluido del deploy:**
- Credenciales: `.env`, `.env.*`
- Documentación interna: `knowledge/`
- Scripts SQL: `database/`
- Logs: `logs/`
- Git/CI: `.git*`, `.github/**`

---

## 8. REGLA ARF-GRID — MAQUETACIÓN RESPONSIVA (Mobile-First)

Toda cuadrícula de tarjetas, módulos o galerías del frontend usa el patrón **ARF-Grid**:

```css
.arf-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
}

.arf-grid__item {
    aspect-ratio: 1 / 1;   /* o el ratio que defina el diseño — nunca alto fijo en px */
}

.arf-grid__item:hover {
    /* transiciones atómicas sobre el aspect-ratio ya definido — sin reflow */
}
```

- Sin anchos ni altos fijos en `px` para contenedores — usar `%`, `fr`, `clamp()` o `aspect-ratio`.
- Los efectos `:hover` se diseñan como transformaciones atómicas (`transform`, `opacity`) sobre el `aspect-ratio` ya establecido — nunca alterando dimensiones que disparen reflow.
- `justify-content: center` garantiza que la última fila incompleta no quede desalineada a la izquierda.

---

## 9. REGLA DE ORO ("ORO") — CSS Y ESTILOS

1. **Paleta propia "El Efecto Ajedrez" — Trinchera Nocturna:** la identidad visual es 100% propia de este proyecto. Prohibido reutilizar nombres, namespaces o tokens de identidades de otros proyectos del holding (ej. "ALISER"). Variables CSS centralizadas en `:root` (`assets/css/main.css`) bajo el prefijo `--ajedrez-*`:
   - `--ajedrez-bg` (`#10141E`, Grafito Nocturno — fondo oficial recomendado)
   - `--ajedrez-bg-obsidiana` (`#0A0C14`, Negro Obsidiana — alternativo ultra oscuro)
   - `--ajedrez-bg-carbono` (`#1E2230`, Carbono Profundo — contraste intermedio)
   - `--ajedrez-accent` (`#E91E63`, Magenta Vibrante — acentos y botones)
   - `--ajedrez-text` / `--ajedrez-text-muted` (blanco / gris acero — WCAG ≥ 4.5:1)
   - `--font-heading` (Bebas Neue / Montserrat) y `--font-base` (Inter / Open Sans)
   Ningún color o fuente se hardcodea fuera de estas variables.
2. **Estilos centralizados, cero inline:** prohibido `style="..."` inline en HTML/JS. Todo estilo vive en archivos `.css` dentro de `assets/css/`.
3. **Resolución desde la raíz — SIN slash inicial:** toda ruta a CSS/JS/imágenes se resuelve como **relativa al documento, sin barra inicial** (`assets/css/main.css`, no `/assets/css/main.css`). El proyecto vive en una subcarpeta de `htdocs/` en local pero en la raíz del dominio en producción — una ruta con `/` inicial apunta a la raíz del host y rompe el render en local (bug detectado y corregido 2026-06-29). Tampoco se usan rutas relativas frágiles por traversal (`../../assets/...`).
4. **`!important` — prohibición absoluta:** si una regla necesita `!important` para ganar, el problema es de especificidad o de orden de carga — se corrige la cascada, nunca se parchea con `!important`.

---

## 10. ARCHIVOS QUE NUNCA SE MODIFICAN SIN AUTORIZACIÓN

- `knowledge/01_LEY_Y_PROTOCOLOS_DE_VUELO.md` — Los Mandamientos son ley.
- `.htaccess` — Blindaje crítico de seguridad.
- `.env` — Credenciales de producción.
- Schema de BD — Inmutabilidad del sistema.

## 11. ARCHIVOS QUE NUNCA SE SUBEN A GIT

- `.env` (cualquier variante real)
- `knowledge/` (carpeta completa, incluye `info.txt`)
- `logs/` (directorio completo)
- `backups/` (directorio completo)
- Cualquier archivo con credenciales reales.

---

## 12. HISTORIAL DE VERSIONES

| Versión | Fecha | Cambio Principal |
| :--- | :--- | :--- |
| v1.0 | 2026-06-09 | Creación inicial del manual operativo (plantilla AXON_GENESIS) |
| v1.1 | 2026-06-29 | Génesis Élite v3 — Personalización para El Efecto Ajedrez: Mentores al Revés. Bóveda de secretos, blindaje `.htaccess` (+`artifacts/`), reglas ARF-Grid y ORO documentadas. |
| v1.2 | 2026-06-29 | Capa transaccional activada (`api/conexion.php`, `cors.php`, `captura_lead.php`, `status_check.php`), migración `leads_captura` ejecutada en producción, pipeline real en `.github/workflows/deploy.yml`. Bug de rutas absolutas corregido (rutas relativas al documento). Paleta renombrada de "ALISER" a `--ajedrez-*` (propia del proyecto). `artifacts/` (plantilla heredada, nunca versionada) eliminada del filesystem local. `api/logo_test.php` añadido como laboratorio de auditoría de marca. |
| v1.3 | 2026-06-29 | Fix crítico de `deploy.yml`: `server-dir` duplicaba la ruta remota sobre el jail FTP de cPanel (corregido a `./`). `assets/img/historia/` excluida de Git y deploy (galería de consulta local). `favicon.ico` regenerado desde `assets/img/logo.png` (PNG-in-ICO, GD). Wordmark `assets/img/logo1.png` añadido al Hero. Enlaces a redes sociales (YouTube/Spotify/Instagram/TikTok/Facebook/Twitch) en header y footer — handles placeholder pendientes de confirmación. Reporte `TPN/Precision_Diagnostica_TPN.html` publicado. |
| v1.4 | 2026-06-29 | **`.htaccess` v2.0** — Parche de compatibilidad cPanel/Tourfindy (autorización explícita del Arquitecto). Eliminadas las directivas conflictivas: HSTS `preload`, redirect HTTPS forzado, `FilesMatch "^\."`, cabeceras CSP/Permissions-Policy. Reemplazadas por bloque de apertura mínimo (`Order allow,deny` + `Require all granted` + `Satisfy Any`) más blindaje perimetral mod_rewrite y FilesMatch de doble capa. Resolución documentada en `knowledge/02_CODEX_Y_SCHEMA_MAESTRO.md`. |
