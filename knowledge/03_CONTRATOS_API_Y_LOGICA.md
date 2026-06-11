# 🤝 CONTRATOS DE API Y LÓGICA DE NEGOCIO

## 📡 PROTOCOLO DE INTEGRACIÓN Y ENFORCEMENT AUTOMÁTICO
- **Cero Deriva (JSON Schema):** Los contratos no son solo documentación. Por cada endpoint documentado aquí, la IA Ejecutora (Claude) DEBE crear un archivo de validación `.json` o un validador estricto en PHP (ej. DTOs) para que la API rechace cargas inválidas con un error 422 antes de tocar la base de datos.
- **Librería de Snippets:** Para componentes repetitivos (Botones de pago, modales, alertas, conexiones estándar), Claude debe consultar primero si existe en la carpeta local `/knowledge/snippets/`. No reinventar la rueda si ya tenemos un componente blindado.

## 📡 PROTOCOLO DE INTEGRACIÓN
- **Intercambio:** JSON UTF-8.
- **Headers Base:** CORS habilitado, Methods (POST, GET, OPTIONS).
- **Estructura Standard de Respuesta:**
  `{ "status": "success/error", "message": "string", "data": [...] }`

## 🛠️ ENDPOINTS REGISTRADOS (CONTRATOS)
### Endpoint: `[nombre_archivo.php]`
- **Método:** [GET/POST]
- **Payload Requerido (Front):** `[ { "propiedad": "tipo" } ]`
- **Response Expected (Back):** `[ { "propiedad": "tipo" } ]`

## 🧠 LÓGICA DE NEGOCIO (REGLAS DE PIEDRA)
1. **[REGLA_1]:** [Descripción de la lógica matemática o de flujo].
2. **[REGLA_2]:** [Descripción de validación específica].
3. **Blindaje Técnico:** [Ej: Uso de TRIM, CAST, validación de NaN, etc].