# 🧬 00 — ADN DEL PROYECTO (DIRECTRIZ MAESTRA)
**Plantilla:** DCD LABS / AXON_DCD | **Versión:** 2.0 | **Tipo:** Genérica Multi-Proyecto

> ⚠️ **INSTRUCCIÓN DE CLONACIÓN:** Sustituye todos los placeholders `[MAYÚSCULAS]` antes de iniciar el desarrollo. Este archivo es la fuente de verdad del negocio. La IA Ejecutora lo leerá en cada sesión como primer paso.

---

## 📌 1. IDENTIDAD DEL PROYECTO

| Campo | Valor |
| :--- | :--- |
| **Nombre del Proyecto** | `[NOMBRE_DEL_PROYECTO]` |
| **Cliente / Dueño** | `[NOMBRE_DEL_CLIENTE_O_EMPRESA]` |
| **Sector / Industria** | `[SECTOR. Ej: Turismo, SaaS, E-commerce, Fintech]` |
| **Ubicación Base** | `[CIUDAD, PAÍS]` |
| **Fecha de Inicio** | `[YYYY-MM-DD]` |
| **URL de Producción** | `https://[SUBDOMINIO_O_DOMINIO].com` |
| **Entorno Local** | `C:\xampp\htdocs\[NOMBRE_DEL_PROYECTO]\` |

### Objetivo Principal
> [Descripción en 1–2 líneas del propósito central del sistema. Qué problema resuelve. Para quién.]

### Promesa Central (si aplica)
> "[Tagline o propuesta de valor diferenciadora del producto]"

### Misión
> [Declaración formal de misión corporativa u operativa del sistema.]

### Visión
> [Declaración de hacia dónde escala el sistema a mediano/largo plazo.]

---

## 🎯 2. TARGET DE CLIENTE

- **Perfil Principal:** [Describe el usuario o cliente tipo. Ej: Emprendedores PYME, Turistas VIP, Administradores corporativos.]
- **Nivel Técnico:** [Alto / Medio / Bajo — impacta el diseño de la UX.]
- **Dispositivo Primario:** [Mobile / Desktop / Ambos — impacta el breakpoint principal.]
- **Geografía:** [País o región objetivo de operación.]

---

## 🎨 3. IDENTIDAD VISUAL

### Paleta de Colores (WCAG 2.1 — Contraste mínimo 4.5:1)

| Rol | HEX | Uso |
| :--- | :--- | :--- |
| Fondo Principal (Dark) | `[#XXXXXX]` | Base del modo oscuro |
| Acento / CTAs | `[#XXXXXX]` | Botones primarios, highlights |
| Superficies / Cards | `[#XXXXXX]` | Tarjetas, nav, paneles |
| Texto Principal | `[#XXXXXX]` | Cuerpo de texto sobre fondo oscuro |

### Activos Visuales

| Activo | Ruta Canónica | Uso |
| :--- | :--- | :--- |
| Logo Light Mode | `assets/img/[logo_light].png` | Navbar en tema claro |
| Logo Dark Mode | `assets/img/[logo_dark].png` | Navbar en tema oscuro + Favicon |
| Favicon Global | `assets/img/[logo_dark].png` | `<link rel="icon">` en todas las páginas |

### Vocabulario Controlado (Regla de Tono)

| ✅ Usar | ❌ Prohibido |
| :--- | :--- |
| `[Término oficial 1]` | `[Sinónimo prohibido 1]` |
| `[Término oficial 2]` | `[Sinónimo prohibido 2]` |

---

## 🛠️ 4. STACK TECNOLÓGICO Y ARQUITECTURA

### Backend
- **Lenguaje:** PHP 8.2+ — `declare(strict_types=1)` obligatorio en **todo** archivo nuevo.
- **Acceso a DB:** PDO centralizado (`core/src/conexion.php`). `PDO::ATTR_EMULATE_PREPARES => false` siempre activo.
- **Autenticación:** JWT HS256 sin dependencias externas (`core/src/jwt.php`).
- **Variables de entorno:** `parse_ini_file()` desde `core/.env`. Sintaxis INI estricta (comentarios con `;`).

### Frontend
- **Framework:** Next.js `[VERSIÓN]` con React `[VERSIÓN]`
- **Estilos:** Tailwind CSS `[VERSIÓN]`
- **Build Output:** `out/` (Static Export) — desplegado vía FTP
- **Modo Oscuro:** Nativo — `class="dark"` en `<html>` o CSS custom properties `prefers-color-scheme`.

### Base de Datos
- **Motor:** MySQL `[VERSIÓN]` / MariaDB `[VERSIÓN]`
- **Charset:** `utf8mb4` | **Collation:** `utf8mb4_unicode_ci` | **Motor de tabla:** `InnoDB`
- **Convenciones:** Todo `snake_case`. Toda tabla tiene `id`, `created_at`, `updated_at`. Soft-delete con `deleted_at`.

### Infraestructura
- **Servidor Local:** Apache / XAMPP en `localhost`
- **Servidor Producción:** Apache / cPanel — Shared Hosting
- **CI/CD:** GitHub Actions → FTP Deploy (`SamKirkland/FTP-Deploy-Action@v4.3.5`)
- **Trigger:** Push a rama `main`
- **server-dir:** `./` (el usuario FTP inicia sesión en el Document Root del subdominio)

---

## 🧩 5. MÓDULOS PRINCIPALES (CORE FEATURES)

> Completa esta sección en la sesión de arquitectura inicial. Un módulo por línea.

1. **[Nombre del Módulo 1]:** [Descripción de qué hace. Ej: Autenticación de usuarios por roles.]
2. **[Nombre del Módulo 2]:** [Descripción. Ej: CRUD de inventario con validación.]
3. **[Nombre del Módulo 3]:** [Descripción. Ej: Generación de reportes en PDF exportable.]
4. **[Nombre del Módulo 4]:** [Descripción. Ej: Panel de administración con RBAC.]

---

## 🔌 6. INTEGRACIONES Y TERCEROS

| Servicio | Propósito | Variable en `.env` | Estado |
| :--- | :--- | :--- | :--- |
| `[Stripe / PayPal / MercadoPago]` | Pasarela de pago | `PAYMENT_SECRET_KEY` | `[Pendiente / Activo]` |
| `[SendGrid / SMTP propio]` | Correo transaccional | `SMTP_PASS` | `[Pendiente / Activo]` |
| `[AWS S3 / Cloudinary]` | Almacenamiento de archivos | `STORAGE_KEY` | `[Pendiente / Activo]` |
| `[OpenAI / Anthropic / N/A]` | IA integrada | `AI_API_KEY` | `[Pendiente / N/A]` |

---

## ⚠️ 7. REGLAS ESPECÍFICAS DEL PROYECTO

> Documenta aquí las reglas de negocio que son únicas para este cliente y que no están cubiertas por los Mandamientos globales.

1. **[Regla 1]:** [Descripción. Ej: Solo los administradores pueden eliminar registros; los usuarios solo desactivan.]
2. **[Regla 2]:** [Descripción. Ej: Las reservaciones con más de 48h de antelación son reembolsables.]
3. **[Regla 3]:** [Descripción. Ej: El precio final siempre se calcula en el backend; el frontend nunca lo altera.]

---

## 📊 8. BASE DE DATOS DE PRODUCCIÓN

| Campo | Valor |
| :--- | :--- |
| **Host** | `localhost` (cPanel) |
| **Nombre** | `[NOMBRE_BASE_DE_DATOS]` |
| **Usuario** | `[USUARIO_DB]` |
| **Conexión** | Siempre a través de `core/src/conexion.php` |

---

## 📁 9. ESTRUCTURA DE CARPETAS (PLANO BASE)

```
[NOMBRE_DEL_PROYECTO]/
├── index.php / index.html           ← Punto de entrada
├── .htaccess                        ← Blindaje Apache Nivel Militar
├── .gitignore                       ← Protección del repositorio
├── CLAUDE.md                        ← Manual operativo del agente
│
├── core/                            ← Núcleo backend privado
│   ├── .env                         ← Credenciales REALES (NUNCA en Git)
│   ├── .env.example                 ← Plantilla pública (sí en Git)
│   └── src/
│       ├── conexion.php             ← PDO centralizado + CORS + logging
│       ├── jwt.php                  ← Utilidad JWT HS256
│       └── auth_middleware.php      ← Validación Bearer JWT + RBAC
│
├── api/                             ← Endpoints PHP (todos blindados)
│   └── [endpoint].php
│
├── app/ (o z/)                      ← Código fuente Next.js
│   ├── src/
│   ├── public/
│   └── out/                         ← Build estático (generado, NO en Git)
│
├── assets/                          ← CSS, JS, imágenes estáticas
│   └── img/
│
├── logs/                            ← Logs del sistema (bloqueados en .htaccess)
│   └── backend.log
│
├── database/                        ← Scripts SQL de migración y seeders
│   ├── migrations/
│   └── seeders/
│
├── core/tools/
│   └── test_connections.php         ← Validador de conexión (OBLIGATORIO pre-deploy)
│
├── .github/
│   └── workflows/
│       └── deploy.yml               ← Pipeline CI/CD automático
│
└── knowledge/                       ← Codex del sistema (bloqueado en .htaccess)
    ├── 00_ADN_DEL_PROYECTO.md       ← Este archivo
    ├── 01_LEY_Y_GOBERNANZA.md
    └── 02_SYSTEM_CODEX_REGISTRY.md
```
