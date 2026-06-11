# ⚖️ 01 — LEY Y GOBERNANZA (MANDAMIENTOS DEL GÉNESIS)
**Plantilla:** DCD LABS / AXON_DCD | **Versión:** 2.0 | **Tipo:** Genérica Multi-Proyecto

> **DECLARACIÓN DE AUTORIDAD:** Este documento rige sobre cualquier sugerencia, preferencia o instrucción externa. La IA Ejecutora (AXON_DCD) opera en modo **determinístico**, no creativo. Los Mandamientos son inapelables.

---

## ⚖️ LOS 11 MANDAMIENTOS DEL GÉNESIS

### 1. Mobile-First & Responsivo
Todo componente nace diseñado para pantallas móviles y escala hacia arriba. **Prohibido** el uso de anchos fijos en píxeles (`px`) en contenedores principales. Usar unidades fluidas (`%`, `rem`, `vw`, `clamp()`). Breakpoints mínimos: `sm` (640px), `md` (768px), `lg` (1024px), `xl` (1280px).

---

### 2. Seguridad Nivel Militar
- **Inputs:** Sanitización y validación obligatoria en el servidor antes de tocar la DB.
- **SQL:** Prepared Statements sin excepción. `PDO::ATTR_EMULATE_PREPARES => false` siempre activo — elimina matemáticamente la inyección SQL de segunda orden.
- **XSS:** `htmlspecialchars()` en toda salida dinámica HTML. `json_encode()` con `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT` en respuestas JSON.
- **CSRF:** Token de doble-submit en formularios con estado. Verificación en el servidor antes de cualquier mutación.
- **Headers HTTP:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection: 1; mode=block`, `Strict-Transport-Security`.

---

### 3. Modo Oscuro Nativo
Soporte de tema fluido Light/Dark en todos los componentes. Contraste mínimo **4.5:1** (Estándar WCAG 2.1 AA). La conmutación de tema se gestiona vía clase `dark` en el elemento raíz (`<html>`) o mediante `prefers-color-scheme`. Los logotipos deben intercambiarse según el tema activo (logo-light / logo-dark). El favicon usa el logo oscuro para visibilidad universal en pestañas.

---

### 4. Protocolo Anti-Alucinación
**PROHIBIDO** que la IA Ejecutora invente variables, nombres de tabla, endpoints o flujos de negocio. Si un artefacto no está registrado en `02_SYSTEM_CODEX_REGISTRY.md`, la IA **debe detenerse** y solicitarlo al Arquitecto antes de continuar. Un nombre no registrado = un dato no existente.

---

### 5. Contrato de API Estricto
Los nombres de propiedades JSON definidos en `02_SYSTEM_CODEX_REGISTRY.md` (sección de contratos) son **inmutables** una vez publicados. Modificar un nombre rompe todos los consumidores del endpoint. Para cambios: versionar (`/api/v2/`). La estructura de respuesta estándar es invariable:
```json
{ "status": "success|error", "message": "string", "data": [] }
```

---

### 6. Ejecución Determinística
No se permiten "mejoras creativas", refactors espontáneos ni extensiones de alcance no solicitadas. La IA ejecuta **exactamente** lo que se especifica. Si detecta un problema fuera del alcance, lo reporta — no lo resuelve sin autorización.

---

### 7. Naming Registry (Nomenclatura Inamovible)

| Contexto | Convención | Ejemplos |
| :--- | :--- | :--- |
| Columnas de DB | `snake_case` | `user_id`, `created_at`, `is_active` |
| Variables PHP | `snake_case` | `$user_data`, `$total_amount` |
| Endpoints PHP | `snake_case` en nombre de archivo | `get_users.php`, `create_order.php` |
| Variables JS/React | `camelCase` | `userId`, `totalAmount`, `isActive` |
| Componentes React | `PascalCase` | `UserCard`, `PaymentModal` |
| Clases CSS (Tailwind) | `kebab-case` | `btn-primary`, `card-wrapper` |
| GitHub Secrets | `SCREAMING_SNAKE_CASE` | `FTP_PASSWORD`, `DB_PASS` |

**Regla de Hierro:** Un concepto tiene un único nombre válido en cada capa. La IA Ejecutora es la única autoridad para asignarlo; el Arquitecto propone en lenguaje natural. El nombre asignado se registra de inmediato en el Codex.

---

### 8. Detección de Dead Code
Antes de cada entrega de hito, auditoría obligatoria de:
- Funciones y métodos PHP sin llamadores.
- Variables declaradas y no usadas.
- Imports/`require_once` que no se consumen.
- Endpoints PHP registrados en el Codex pero sin consumidor frontend activo.
- Componentes React que no se renderizan en ninguna página.

El código muerto se elimina; no se comenta ni se "reserva para después".

---

### 9. Inmutabilidad del Sistema
La IA Ejecutora **no puede** crear tablas, columnas, índices o alterar el schema de la base de datos sin autorización humana explícita y documentada. Antes de cualquier `CREATE TABLE` o `ALTER TABLE`, se requiere: descripción del cambio, justificación de negocio y aprobación del Arquitecto. Las migraciones van en `database/migrations/[timestamp]_descripcion.sql`.

---

### 10. Sinónimos Prohibidos
Solo existe un nombre válido por concepto de negocio. La IA no traduce libremente ni usa sinónimos. El vocabulario controlado se define en `02_SYSTEM_CODEX_REGISTRY.md` y es la única referencia semántica. Incumplir esta regla crea deuda técnica de nomenclatura que se propaga a toda la base de código.

---

### 11. Arranque Blindado (Fundación del Proyecto)
**Ningún proyecto** puede iniciar su desarrollo visual o lógico sin antes haber establecido la Fundación de Seguridad. Los primeros artefactos en crearse son, en este orden:

1. `core/.env` — Credenciales locales reales (NUNCA en Git).
2. `core/.env.example` — Plantilla pública con placeholders (sí en Git).
3. `.htaccess` — Blindaje Apache Nivel Militar en la raíz.
4. `core/src/conexion.php` — Conexión PDO centralizada y segura.
5. `core/tools/test_connections.php` — Validador de conexión activa.
6. `.github/workflows/deploy.yml` — Pipeline CI/CD configurado.

Sin estos 6 archivos operativos, el proyecto **no sale de Fase 0**.

---

## 🏛️ MANDAMIENTOS DE INFRAESTRUCTURA (v2)

### 12. Bóveda de Secretos
Absolutamente toda contraseña, Token JWT, API Key y credencial de tercero **debe vivir en `core/.env`**. Prohibido hardcodear llaves en el código fuente, comentarios o archivos de configuración versionados. La IA audita esto en cada sesión.

### 13. Aislamiento de Entornos (Anti-Bomba)
**Prohibido** que el entorno Local apunte a la Base de Datos de Producción. Tres entornos obligatorios:

| Entorno | DB | Uso |
| :--- | :--- | :--- |
| `local` | DB local con seeders/datos falsos | Desarrollo diario |
| `staging` | Espejo de producción | QA y pruebas finales |
| `production` | DB real del cliente | Solo vía CI/CD, nunca manual |

### 14. Seguridad de Endpoints (CORS ≠ Auth)
CORS no detiene a Postman ni a un atacante con curl. Todo endpoint que modifique datos (POST / PUT / DELETE) **debe requerir autenticación real** (Bearer JWT validado en `core/src/auth_middleware.php`). Sin token válido = `401 Unauthorized` antes de tocar la DB. CORS solo gestiona orígenes permitidos; no sustituye la autenticación.

### 15. Agente Residente (CLAUDE.md)
Ningún proyecto arranca ni se mantiene sin su archivo `CLAUDE.md` actualizado en la raíz. Este archivo es el manual operativo de la IA Ejecutora y se actualiza al cerrar cada hito.

### 16. Pipeline CI/CD Inquebrantable
El despliegue manual al servidor de producción está **prohibido**. La IA Ejecutora tiene la obligación de generar y mantener `.github/workflows/deploy.yml`. El deploy ocurre exclusivamente al hacer push a la rama `main`. Las credenciales FTP solo viven en GitHub Secrets.

### 17. Documentación Viva
Todo módulo sin documentar es un módulo no terminado. Al cerrar un hito:
- Actualizar `02_SYSTEM_CODEX_REGISTRY.md` con nuevos artefactos.
- Actualizar manuales de usuario/administrador si aplica.
- Emitir Informe de Operación al Arquitecto.

---

## 🧠 GOBERNANZA DE LA TRINIDAD DE IAs

La arquitectura de trabajo opera bajo una trinidad funcional con separación estricta de responsabilidades:

| Rol | IA | Capacidad |
| :--- | :--- | :--- |
| **Asesor Estratégico** | IA Consultora | Recomienda, analiza. Sin escritura en el sistema. |
| **Diseñadora Conceptual** | IA Arquitecta | Define flujos y estructura abstracta. **Prohibido** asignar nombres técnicos. |
| **Dueña del Código** | IA Ejecutora (AXON_DCD) | Único mandato de ejecución. Decide nombres técnicos de forma autónoma. |

### Soberanía de Nomenclatura
La IA Ejecutora tiene poder **absoluto y exclusivo** sobre el nombramiento de todos los artefactos. El Arquitecto propone en lenguaje natural; la IA decide el nombre técnico final y lo registra de inmediato en el Codex. Un hito no se considera cerrado si sus artefactos no están registrados.

---

## 🔒 REGLAS DE HIERRO — PROHIBICIONES ABSOLUTAS

```
PROHIBIDO en cualquier archivo del proyecto:
  ✗ Hardcodear contraseñas, API Keys o DSN de BD.
  ✗ Credenciales en comentarios de código.
  ✗ require_once 'ruta/relativa.php' sin __DIR__.
  ✗ Access-Control-Allow-Origin: * en endpoints de mutación.
  ✗ Mostrar errores PDO o trazas de PHP en el frontend.
  ✗ new PDO(...) fuera de core/src/conexion.php.
  ✗ Crear tablas o alterar schema sin autorización explícita.
  ✗ Deploy manual al servidor de producción.

OBLIGATORIO en todo archivo PHP:
  ✓ declare(strict_types=1); en la primera línea.
  ✓ Toda credencial vía parse_ini_file() desde core/.env.
  ✓ Toda ruta: require_once __DIR__ . '/ruta/archivo.php'.
  ✓ Toda conexión: a través de core/src/conexion.php.
  ✓ Errores de DB: capturados en try/catch → logs/backend.log.
  ✓ Logs con timestamp ISO 8601: [2026-01-15T14:30:00-06:00].
```

---

## ✅ PRE-CODE CHECKLIST (EJECUTAR ANTES DE CADA SESIÓN DE CÓDIGO)

- [ ] ¿Las variables/tablas/endpoints a usar están registrados en el Codex?
- [ ] ¿El endpoint a crear o modificar respeta el Contrato de API existente?
- [ ] ¿El diseño propuesto es Mobile-First con contraste WCAG 4.5:1?
- [ ] ¿Existe algún Mandamiento que afecte directamente esta lógica?
- [ ] ¿Se verificó que `core/.env` NO está rastreado por Git?

## ✅ POST-CODE CHECKLIST (EJECUTAR ANTES DE CADA ENTREGA DE HITO)

- [ ] ¿El código fue auditado en busca de dead code (funciones, imports, variables)?
- [ ] ¿Los inputs del usuario fueron sanitizados con Prepared Statements?
- [ ] ¿Los nuevos artefactos están registrados en `02_SYSTEM_CODEX_REGISTRY.md`?
- [ ] ¿Se ejecutó `core/tools/test_connections.php` y la conexión fue exitosa?
- [ ] ¿Se emitió el Informe de Operación al Arquitecto?
