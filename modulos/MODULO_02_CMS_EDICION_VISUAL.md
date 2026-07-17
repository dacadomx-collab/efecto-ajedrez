# MODULO_02_CMS_EDICION_VISUAL — Motor de Edición Visual en Caliente (Inline Canvas)

**Clasificación:** Módulo Genérico de Arquitectura y Diseño Técnico | **Versión:** 1.0
**Alcance:** Documento agnóstico, reutilizable por cualquier proyecto de `{{HOLDING_NAME}}`. Ningún nombre de proyecto, cliente, dominio o módulo comercial real debe aparecer aquí — sustituir siempre por `{{PROJECT_NAME}}`, `{{MODULE_NAME}}`, `{{DASHBOARD_URL}}`, `{{TABLE_PREFIX}}`, etc.
**Dependencia:** Requiere [`MODULO_01_LOGIN_Y_ACCESO.md`](MODULO_01_LOGIN_Y_ACCESO.md) ya implementado — reutiliza su cookie de sesión (§3.1-3.2), su patrón de 6 capas (§2), su Matriz de Roles (§6) y su Mapeo Dinámico de Permisos (§6.1). Este módulo **no** define su propio esquema de autenticación.

> ⚠️ **Separación de responsabilidades:** este módulo vive en un archivo propio (no dentro de MODULO_01) porque resuelve un problema distinto — edición de contenido público, no acceso al sistema. Un proyecto consumidor puede adoptar MODULO_01 sin este módulo, y viceversa (aunque en la práctica este depende de aquel para la sesión y los roles).

---

## 1. 🗄️ SCHEMA MAESTRO DEL LIENZO (SQL PRIMERO)

```sql
CREATE TABLE `{{TABLE_PREFIX}}configuracion_layout` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pagina`          VARCHAR(60)  NOT NULL COMMENT 'Identificador estable de la página pública, ej. "{{PAGINA_ID}}"',
    `bloque_id`       VARCHAR(80)  NOT NULL COMMENT 'Identificador estable del bloque dentro de la página (data-block-id)',
    `tipo`            ENUM('texto', 'imagen') NOT NULL,
    `contenido`       TEXT         NULL COMMENT 'Texto editado — NULL si tipo=imagen',
    `imagen_path`     VARCHAR(255) NULL COMMENT 'Ruta relativa al archivo ya validado y renombrado — NULL si tipo=texto',
    `actualizado_por` INT UNSIGNED NULL COMMENT 'FK lógica a {{TABLE_PREFIX}}usuarios.id (MODULO_01 §1.1) — quién hizo el último cambio',
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_{{TABLE_PREFIX}}layout_pagina_bloque` (`pagina`, `bloque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notas de diseño:**
- Una fila por bloque editable, nunca una tabla por página — `(pagina, bloque_id)` es la clave natural, extensible a cualquier página nueva sin alterar el schema (Mandamiento 9 ya satisfecho de una sola vez).
- `contenido` e `imagen_path` son mutuamente excluyentes según `tipo` — la capa de persistencia (Sección 4.3) nunca escribe ambos en la misma fila.
- Sin fila para un `(pagina, bloque_id)` dado, la página pública renderiza su valor **hardcodeado original** (el HTML/PHP fuente sigue siendo la fuente de verdad por defecto) — esta tabla solo almacena **overrides**, nunca el contenido completo de la página. Esto es intencional: un proyecto consumidor nunca queda con una página en blanco por una fila faltante o corrupta.

---

## 2. 🖥️ INTERFAZ: "EDITAR {{MODULE_NAME}}"

### 2.1 Activación desde el Dashboard

- Visible solo bajo el Mapeo Dinámico de Permisos de MODULO_01 §6.1 — módulo `{{MODULO_PERMISO_ID}}` (ej. `landing_editor`), con la misma regla: `super_admin` siempre lo ve, `admin` depende de la matriz.
- Al activarse, el sistema **renderiza la página pública real** (mismo HTML, mismo CSS, mismo layout que ve el cliente final) — nunca una réplica o maqueta aparte. El modo edición es un **estado superpuesto** sobre la página real, no una página distinta.

### 2.2 Banner de advertencia perimetral

- Fijo en la cima de la pantalla (`position: sticky` o `fixed`, z-index por encima de todo), persistente durante toda la sesión de edición.
- Texto literal obligatorio: `"IMPORTANTE: PÁGINA PARA EDITAR"`.
- Incluye la instrucción pedagógica: *"Si deseas cambiar un texto o una imagen, simplemente haz clic sobre el elemento que quieres modificar."*
- Nunca se cierra con una "x" silenciosa que borre el contexto — el modo edición completo se abandona únicamente vía el botón "Cerrar" del pie de página (Sección 3).

---

## 3. ✏️ LÓGICA DE EDICIÓN INLINE (WORKFLOW)

### 3.1 Átomos interactivos

Cada bloque editable de la página pública lleva un atributo estable `data-block-id="{{BLOQUE_ID}}"` — este identificador es el mismo que la clave `bloque_id` del schema (Sección 1), nunca se genera dinámicamente en el cliente.

```html
<h1 data-block-id="{{BLOQUE_ID}}" data-block-type="texto">{{TEXTO_ACTUAL}}</h1>
<img data-block-id="{{BLOQUE_ID}}" data-block-type="imagen" src="{{IMAGEN_ACTUAL}}" alt="...">
```

### 3.2 Activación por clic

```javascript
function initInlineEditor() {
    if (!document.body.hasAttribute('data-edit-mode')) {
        return; // el modo edición se activa server-side, nunca por flag de cliente
    }

    document.querySelectorAll('[data-block-id]').forEach(function (bloque) {
        bloque.addEventListener('click', function (event) {
            if (bloque.dataset.blockType === 'texto') {
                activarEdicionTexto(bloque);
            } else if (bloque.dataset.blockType === 'imagen') {
                activarSelectorImagen(bloque);
            }
        });
    });
}
```

- **Texto:** al clic, el bloque pasa a `contenteditable="true"` (o un `<textarea>` superpuesto de la misma caja, si el proyecto consumidor prefiere evitar las peculiaridades de `contenteditable` en móvil) y gana foco inmediato.
- **Imagen:** al clic, se dispara un `<input type="file">` oculto (`accept="image/webp,image/png,image/jpeg"`) — nunca se sube el archivo hasta que el administrador confirme con el botón Guardar (Sección 3.3).

### 3.3 Controles contextuales de bloque

Al entrar en modo edición de **cualquier** bloque, aparecen dos botones atómicos anclados a ese bloque (nunca un único set de botones global para toda la página — cada bloque gestiona su propio ciclo de vida):

- **Guardar:** `fetch` asíncrono al endpoint de mutación (Sección 4), **un bloque a la vez** — nunca se agrupan múltiples bloques en una sola petición (simplicidad + granularidad de auditoría).
- **Cancelar ("Eliminar Edición"):** revierte el DOM al valor que tenía **antes de este clic** (guardado en una variable local al activar la edición, nunca releído del servidor — instantáneo, sin round-trip) y cierra el modo edición de ese bloque, sin recargar la página.

```javascript
function activarEdicionTexto(bloque) {
    const valorOriginal = bloque.textContent;
    bloque.setAttribute('contenteditable', 'true');
    bloque.focus();

    const controles = crearControlesBloque(bloque, {
        onGuardar: function () {
            guardarBloqueTexto(bloque.dataset.blockId, bloque.textContent);
        },
        onCancelar: function () {
            bloque.textContent = valorOriginal;
            bloque.removeAttribute('contenteditable');
            controles.remove();
        },
    });
}
```

---

## 4. 🛡️ BLINDAJE DEL ENDPOINT (PATRÓN DE 6 CAPAS)

Aplica el patrón canónico de MODULO_01 §2 sin modificaciones — dos endpoints distintos, uno por tipo de bloque:

### 4.1 Endpoint de texto (ej. `{{ENDPOINT_TEXTO}}`)

- **Capa 2 (Auth):** `requireAuth($pdo, ['super_admin', 'admin'])` + verificación del Mapeo Dinámico de Permisos (MODULO_01 §6.1) para `admin`.
- **Capa 4 (Sanitización):** `strip_tags()` + `htmlspecialchars()` sobre el contenido recibido, límite estricto de longitud por tipo de bloque (ej. un `<h1>` no acepta el mismo límite que un `<p>` de cuerpo — el proyecto consumidor documenta estos límites por `bloque_id` en su Codex).
- **Capa 5 (Persistencia):** `INSERT ... ON DUPLICATE KEY UPDATE` sobre `(pagina, bloque_id)` — nunca un `DELETE` + `INSERT` (evita ventanas de inconsistencia).

### 4.2 Endpoint de imagen (ej. `{{ENDPOINT_IMAGEN}}`)

- **Capa 4 (Validación de archivo — más estricta que la de texto):**
  1. MIME-type real del archivo (`finfo_file()`, **nunca** confiar en la extensión ni en el `Content-Type` que envía el navegador).
  2. Whitelist cerrada: `image/webp`, `image/png`, `image/jpeg` — cualquier otro tipo se rechaza con 422, sin excepción.
  3. Peso máximo (`{{TAMANO_MAXIMO_MB}}`, ej. 5MB) — rechazo inmediato si se excede, antes de mover el archivo.
  4. **Renombrado criptográfico obligatorio:** `bin2hex(random_bytes(16)) . '.' . $extensionValidada` — el nombre original del archivo **nunca** se persiste ni se usa como parte de la ruta final (evita path traversal y ejecución de scripts disfrazados de imagen).
  5. El directorio de destino tiene su propio `.htaccess` que desactiva la ejecución de PHP (`php_flag engine off` o equivalente del servidor) — defensa en profundidad incluso si algún archivo lograra pasar la validación de MIME con contenido malicioso.
- **Capa 5 (Persistencia):** el `imagen_path` guardado es **siempre** relativo y generado por el servidor — nunca el path que envía el cliente.

### 4.3 Contrato de Respuesta

Mismo contrato de MODULO_01 §2.1 (`{status, message, data}`). Para el endpoint de imagen, `data.imagen_path` es la única forma en que el frontend conoce la URL final — nunca la construye por sí mismo a partir del nombre de archivo original.

---

## 5. 🚪 ACCIONES GLOBALES DEL LIENZO

Pie de página fijo, presente durante todo el modo edición:

- **"Ver en vivo":** `<a target="_blank" href="{{URL_PUBLICA_PAGINA}}">` — abre la URL pública real en pestaña nueva, sin parámetro de modo edición, para que el administrador vea exactamente lo que ve el público.
- **"Cerrar":** apaga el modo edición (deja de enviar el flag/cookie que lo activa) y redirige de inmediato a `{{DASHBOARD_URL}}` — nunca dejar al administrador en la página pública sin los controles de edición ni sin ruta de regreso clara.

---

## 5.5 Puente Fail-Safe ante Mutación de Extensión

Cuando una página pública pasa de estática (`.html`) a dinámica (`.{{EXTENSION_BACKEND}}`) para volverse editable (Sección 1), su URL pública cambia. Si esa URL **ya fue compartida** (redes sociales, WhatsApp, campañas de correo), el archivo antiguo debe seguir existiendo como puente, nunca como un 404:

```html
<!DOCTYPE html>
<html lang="{{IDIOMA}}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0; url={{RUTA_NUEVA}}">
    <link rel="canonical" href="{{URL_PUBLICA_NUEVA}}">
    <title>{{TITULO_PAGINA}}</title>
</head>
<body>
    <p>Redirigiendo a <a href="{{RUTA_NUEVA}}">{{RUTA_NUEVA}}</a>…</p>
    <script>window.location.replace('{{RUTA_NUEVA}}');</script>
</body>
</html>
```

- Doble mecanismo (meta-refresh + `window.location.replace`) — funciona incluso si el proyecto consumidor no puede emitir una cabecera HTTP 301/302 real (ej. hosting compartido sin control de servidor); `replace()` además evita que la URL vieja quede en el historial del navegador.
- El archivo puente **nunca** se edita más allá de esto — no lleva contenido real, banner de edición, ni bloques `data-block-id`; es exclusivamente un redirector transparente.
- El usuario final **nunca** debe notar la fricción del cambio de extensión — cero pantalla intermedia visible, cero mensaje de error.

---

## 6. ✅ VALIDACIÓN DE CONSISTENCIA

| # | Verificación | Cubierto en |
| :--- | :--- | :---: |
| 1 | Modo edición activado server-side, nunca por un flag manipulable en el cliente | §3.2 |
| 2 | Cada bloque se guarda de forma independiente (granularidad de auditoría, sin mutaciones agrupadas) | §3.3 |
| 3 | Cancelar es instantáneo y local — no depende de una relectura del servidor | §3.3 |
| 4 | Sanitización estricta de texto (XSS) antes de persistir | §4.1 |
| 5 | Validación de imagen por contenido real (`finfo`), no por extensión ni Content-Type declarado | §4.2 |
| 6 | Renombrado criptográfico — el nombre de archivo original nunca llega al filesystem | §4.2 |
| 7 | Directorio de subida sin ejecución de scripts — defensa en profundidad | §4.2 |
| 8 | Ausencia de fila en `configuracion_layout` nunca produce una página en blanco (fallback al valor original) | §1 |
| 9 | "Cerrar" siempre redirige a una ruta seleccionada, nunca deja al administrador varado en modo edición | §5 |
