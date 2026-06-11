# 🤝 03 — API CONTRACTS AND ROUTING

> **Fuente de verdad única** para todos los contratos de API, endpoints registrados y lógica de negocio de las rutas.
> **Mandamiento #5:** Prohibido alterar nombres de propiedades JSON definidos aquí sin autorización del Arquitecto.
> **Mandamiento #14:** Todo endpoint que modifique datos requiere autenticación real (JWT Bearer).

---

## 📡 PROTOCOLO DE INTEGRACIÓN (ESTÁNDAR GLOBAL)

| Parámetro | Valor |
| :--- | :--- |
| **Formato** | JSON UTF-8 |
| **Charset** | `Content-Type: application/json; charset=UTF-8` |
| **CORS** | Whitelist explícita — ver `api/cors.php`. CORS no reemplaza auth. |
| **Autenticación** | `Authorization: Bearer <JWT_TOKEN>` en headers |
| **Métodos** | GET, POST, PUT, DELETE, OPTIONS (preflight) |

### Estructura Standard de Respuesta (INAMOVIBLE)

```json
{
  "status":  "success | error",
  "message": "Descripción legible del resultado",
  "data":    [] 
}
```

- `status`: Siempre `"success"` o `"error"`. Sin variantes.
- `message`: String en español, legible por el usuario o el frontend.
- `data`: Array de objetos o array vacío `[]`. **NUNCA** `null`.

### Códigos HTTP Estándar del Sistema

| Código | Uso |
| :--- | :--- |
| `200` | OK — operación exitosa con datos |
| `201` | Created — recurso creado exitosamente |
| `204` | No Content — preflight OPTIONS |
| `400` | Bad Request — payload JSON malformado |
| `401` | Unauthorized — sin token o token inválido/expirado |
| `403` | Forbidden — token válido pero sin permisos (rol insuficiente / CORS) |
| `404` | Not Found — recurso no encontrado |
| `405` | Method Not Allowed — método HTTP incorrecto |
| `409` | Conflict — conflicto de estado (ej: cancelar algo ya cancelado) |
| `422` | Unprocessable Entity — payload válido pero datos inválidos (validación) |
| `500` | Internal Server Error — error de servidor (nunca exponer detalles al frontend) |

---

## 🛡️ ENFORCEMENT AUTOMÁTICO DE CONTRATOS

- **Cero Deriva (JSON Schema):** Por cada endpoint documentado aquí, la IA Ejecutora (Claude) DEBE crear validaciones estrictas en PHP para que la API rechace cargas inválidas con un `422` antes de tocar la base de datos.
- **Librería de Snippets:** Para componentes repetitivos, consultar primero `/knowledge/snippets/`. No reinventar si ya existe un componente blindado.
- **Prepared Statements:** Toda consulta SQL usa `PDO::prepare()` + `execute([])`. Sin excepción.

---

## 🛠️ ENDPOINTS REGISTRADOS

### Plantilla de Registro de Endpoint

```
### Endpoint: `api/[nombre_archivo.php]`
- **Método:**        GET | POST | PUT | DELETE
- **Auth:**          Público | Bearer JWT | Bearer JWT + Role: [rol]
- **Descripción:**   [Qué hace este endpoint en lenguaje natural]
- **Payload (Front → Back):**
  ```json
  { "campo": "tipo | descripción" }
  ```
- **Response (Back → Front):**
  ```json
  { "status": "success", "message": "...", "data": [ { "campo": "tipo" } ] }
  ```
- **Errores Posibles:** 401 (sin auth), 422 (payload inválido), 500 (error interno)
```

---

### Endpoint: `api/[nombre_archivo.php]`
- **Método:** [GET / POST]
- **Auth:** [Público / Bearer JWT]
- **Descripción:** [Descripción de la acción]
- **Payload Requerido (Front):**
```json
{ "propiedad": "tipo" }
```
- **Response Expected (Back):**
```json
{ "status": "success", "message": "string", "data": [] }
```

---

## 🧠 LÓGICA DE NEGOCIO (REGLAS DE PIEDRA)

Las reglas de negocio son invariantes del sistema. La IA Ejecutora implementa la lógica; no la inventa ni la modifica sin autorización.

1. **[REGLA_1]:** [Descripción de la lógica matemática o de flujo]
2. **[REGLA_2]:** [Descripción de validación específica]
3. **Blindaje Técnico Base:**
   - `TRIM()` en todos los strings de entrada antes de persistir
   - `CAST` / tipado fuerte en todos los campos numéricos (`(int)`, `(float)`)
   - Validar contra `NaN`, `null`, strings vacíos en campos requeridos
   - Longitud máxima validada antes de tocar la DB (coincide con longitud de columna)

---

## 📐 PATRONES DE SEGURIDAD EN ENDPOINTS (PLANTILLA)

Todo endpoint PHP de este proyecto sigue este patrón de 6 capas:

```php
<?php
declare(strict_types=1);

// 1. CORS (siempre primero)
require_once __DIR__ . '/cors.php';

// 2. AUTH (en endpoints protegidos)
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/auth_middleware.php';
requireRole(['admin'], $authPayload);

// 3. MÉTODO HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

// 4. LEER Y VALIDAR PAYLOAD
$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
// ... validaciones ...

// 5. CONEXIÓN A DB
require_once __DIR__ . '/conexion.php';
$pdo = (new Database())->getConnection();

// 6. LÓGICA DE NEGOCIO (con try/catch + log)
try {
    $stmt = $pdo->prepare("SELECT * FROM tabla WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetchAll();
    jsonResponse('success', 'Operación exitosa.', $result);
} catch (PDOException $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] [endpoint] ' . $e->getMessage());
    jsonResponse('error', 'Error interno. Intente más tarde.', [], 500);
}
```
