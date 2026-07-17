# Checklist de Ejecución — MODULO_02_CMS_EDICION_VISUAL aplicado a El Efecto Ajedrez

**Relación:** Instancia concreta del blueprint genérico [`MODULO_02_CMS_EDICION_VISUAL.md`](MODULO_02_CMS_EDICION_VISUAL.md) para este proyecto (página objetivo: `club-lectura.php`, el "Círculo de Lectura"). Este archivo **sí** usa nombres reales — es el registro de auditoría de qué se construyó, dónde, y qué falta. Compañero de [`CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md`](CHECKLIST_EJECUCION_LOGIN_EFECTO_AJEDREZ.md).

**Autorización:** Dirección explícita del Productor Tzunum, 2026-07-17. `knowledge/` no fue tocado (prohibición absoluta del `CLAUDE.md`, sin excepción).

---

## ✅ Checklist de Desarrollo Operativo

### Fase 1 — Documentación
- [x] `modulos/MODULO_02_CMS_EDICION_VISUAL.md` — blueprint genérico nuevo (no se mezcló con MODULO_01, dominios distintos).
- [x] `MODULO_01_LOGIN_Y_ACCESO.md` enlaza al nuevo módulo como referencia cruzada.

### Fase 2 — Schema y permisos
- [x] `database/create_table_configuracion_layout.sql` — tabla de overrides por bloque (`pagina`, `bloque_id`, `tipo`, `contenido`/`imagen_path`). Migrada.
- [x] `database/seed_permisos_modulos_landing.sql` — nuevo módulo `landing` en la matriz de permisos dinámicos (habilitado por defecto para `admin`, mismo criterio que `usuarios`). Migrada.
- [x] `assets/img/landing-uploads/.htaccess` — directorio nuevo, `.htaccess` propio que desactiva ejecución de scripts (defensa en profundidad; **no** se tocó el `.htaccess` raíz protegido).

### Fase 3 — Página convertida
- [x] `club-lectura.html` → **`club-lectura.php`** (eliminado el `.html`, actualizado el enlace en `index.php`). Necesario porque el editor requiere contenido dinámico desde BD — imposible en HTML estático.
- [x] 13 bloques marcados con `data-block-id`/`data-block-type`: título y subtítulo del hero, foto de la anfitriona, título/lead de detalles, 3 valores de detalles (días/horario/enlace), título de beneficios, 3 tarjetas completas (título+texto), título/lead de cierre.
- [x] Modo edición activado **server-side únicamente** (cookie + rol + Mapeo Dinámico de Permisos) — nunca por el parámetro `?modo_edicion=1` sin verificar.

### Fase 4 — Endpoints (patrón de 6 capas)
- [x] `api/layout_bloque_guardar.php` — `strip_tags()` + `htmlspecialchars()`, límite de 2000 caracteres, `INSERT ... ON DUPLICATE KEY UPDATE` con placeholders `_update` separados (mismo bug de MySQL nativo que en `configuracion_seguridad.php`/`permisos_modulos.php` — corregido de una vez desde el diseño).
- [x] `api/layout_imagen_subir.php` — MIME real vía `finfo_file()` (nunca extensión ni Content-Type declarado), whitelist `webp`/`png`/`jpeg`, `getimagesize()` como segunda verificación independiente, máximo 5MB, renombrado criptográfico (`bin2hex(random_bytes(16))`), directorio con `.htaccess` propio.
- [x] Ambos endpoints respetan la matriz de permisos (`esModuloVisible('landing', rol)`) para `admin`; `super_admin` siempre puede.

### Fase 5 — Frontend
- [x] Banner perimetral persistente (`"IMPORTANTE: PÁGINA PARA EDITAR"` + instrucción pedagógica), sticky, solo en modo edición.
- [x] `initInlineEditor()` en `main.js`: clic en texto → `contenteditable` + controles Guardar/Cancelar por bloque (cancelar revierte instantáneo, local, sin round-trip); clic en imagen → selector de archivo nativo → subida automática + reemplazo del `src` en el DOM.
- [x] Pie de página fijo en modo edición: "Ver en vivo" (abre `club-lectura.php` real en pestaña nueva) y "Cerrar" (redirige a `dashboard.php`).
- [x] `dashboard.php` — tarjeta "Editar Landing Page" (gateada por `$verLanding`), enlace a `club-lectura.php?modo_edicion=1`, y checkbox `landing` en el panel de matriz de permisos del `super_admin`.

### Fase 6 — Verificación end-to-end (cuenta de prueba temporal, creada y eliminada en esta sesión)
- [x] `php -l` (6 archivos) y `node --check` — sin errores.
- [x] Página pública sin `?modo_edicion=1`: `200`, sin banner, sin `data-edit-mode` (confirmado que el modo edición no se filtra a visitantes).
- [x] Con admin autorizado + `?modo_edicion=1`: banner y controles presentes.
- [x] Guardar bloque de texto con payload `<script>alert(1)</script>` → sanitizado a texto plano (sin tags), reflejado correctamente en la página pública tras el guardado.
- [x] Subida de imagen real (JPG) → renombrado criptográfico, archivo servido `200`, ruta persistida en BD.
- [x] Subida de archivo PHP disfrazado de `.jpg` → rechazado `422` por el MIME real (`finfo`), nunca tocó el filesystem.
- [x] Con `admin` sin permiso en la matriz (`landing` deshabilitado): `?modo_edicion=1` no activa el modo edición (0 coincidencias de `data-edit-mode`) y `POST layout_bloque_guardar.php` responde `403`.
- [x] Limpieza final verificada: 1 solo usuario en BD (cuenta raíz real), `configuracion_layout` en 0 filas, matriz de permisos revertida a su default, imagen de prueba eliminada del filesystem, página pública confirmada de vuelta a su contenido original.

### Fase 7 — Pendiente (no construido en esta pasada, requiere decisión adicional)
- [ ] Extender el editor a otras páginas públicas (`index.php`) — hoy solo `club-lectura.php` está instrumentado, según lo pedido ("Círculo de Lectura").
- [ ] Límite de longitud diferenciado por tipo de bloque (hoy: 2000 caracteres fijo para todos) — requiere que el Codex del proyecto documente límites específicos por `bloque_id` si se necesita mayor granularidad.
- [ ] Borrado/purga de imágenes reemplazadas (hoy: subir una nueva imagen dispara el override en BD pero la imagen anterior queda huérfana en `assets/img/landing-uploads/` — limpieza de huérfanos no solicitada, requiere decisión de retención).
