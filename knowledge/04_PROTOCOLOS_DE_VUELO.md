# 🧪 PROTOCOLOS DE VUELO (CHECKLISTS DE CALIDAD)

## 🤖 DIRECTRIZ DE AGENTE AUTÓNOMO (VS CODE)
La IA Ejecutora (Agente) actúa como entidad soberana con permisos de lectura/escritura en el sistema de archivos bajo el modelo de gobernanza de la **Trinidad de IAs**.

### Protocolo de Operación Autónoma
- **PROHIBIDO:** Entregar bloques de código largos para que el humano los copie y pegue manualmente.
- **PROHIBIDO:** Solicitar al usuario, a la IA Consultora o a la IA Arquitecta que dicten nombres de variables, tablas, columnas, endpoints o parámetros. El Agente recibe intención conceptual y decide los nombres técnicos de forma autónoma.
- **OBLIGATORIO:** La IA debe buscar, abrir, editar, guardar y verificar los archivos directamente usando sus herramientas de entorno.
- **OBLIGATORIO — REGISTRO DETERMINÍSTICO:** Inmediatamente después de asignar cualquier nombre técnico (variable, tabla, endpoint, columna, componente), el Agente debe actualizar el `02_SYSTEM_CODEX_REGISTRY.md` de forma autónoma. Esta acción es parte del hito, no un paso opcional posterior.
- **FLUJO DE TRABAJO:** La IA Arquitecta define la estrategia y los flujos conceptuales. El Agente traduce esos conceptos a código, ejecuta directamente en los archivos, registra en el Codex, realiza las pruebas locales necesarias y emite un "Informe de Operación" al cerrar cada hito.

### Regla de Cierre de Hito
Un hito se considera **técnicamente cerrado** únicamente cuando se cumplen estas tres condiciones de forma simultánea:
1. El código está escrito, guardado y funcional en el entorno local.
2. Todos los artefactos nuevos (tablas, variables, endpoints) están registrados en `02_SYSTEM_CODEX_REGISTRY.md`.
3. Se ha emitido el Informe de Operación al Arquitecto con el estado final.

## 🛫 PRE-CODE CHECKLIST (OBLIGATORIO)
Antes de generar código, la IA debe confirmar:
- [ ] ¿Las variables están registradas en el Codex?
- [ ] ¿El endpoint respeta el Contrato de API?
- [ ] ¿El diseño propuesto es Mobile-First?
- [ ] ¿Existe una Regla de Piedra que afecte esta lógica?

## 🛡️ FOUNDATION CHECK (ARRANQUE DE PROYECTO)
Al iniciar un proyecto desde cero, la IA (Claude) DEBE ejecutar y confirmar la creación de esta infraestructura antes de programar cualquier vista:
- [ ] **Bóveda de Secretos:** ¿Creé el `.env` (para contraseñas y TOKENS) y el `.env.example`?
- [ ] **Blindaje Apache/Servidor:** ¿Creé el archivo `.htaccess` en la raíz con reglas para bloquear carpetas ocultas, denegar acceso al `.env` y enrutamiento limpio?
- [ ] **Gitignore Strict:** ¿El `.gitignore` está configurado para proteger el `.env` real, carpetas del OS y node_modules/vendor?
- [ ] **Pipeline CI/CD:** ¿Creé la ruta `.github/workflows/deploy.yml` con el flujo de automatización FTP (Fase 3 de Despliegue Continuo)?

### 📡 FOUNDATION CHECK — Validación SFL (Synaptic Flow Ledger)
> **Obligatorio** en todo módulo o endpoint nuevo antes de declararlo funcional. Referencia: Mandamiento 19.

#### Captura de Variables (Local / Staging)
- [ ] **`network_latency_ms`:** ¿El módulo mide y registra la latencia de red en milisegundos antes de entregar la respuesta?
- [ ] **`db_query_status`:** ¿El bloque PDO captura el resultado de la consulta como `'ok'`, `'error'` o `'timeout'` y lo escribe en el log?
- [ ] **`tokens_in_flight`:** Si el módulo despacha tokens a un modelo de IA, ¿el conteo se registra en el log interno antes del retorno?
- [ ] **`synaptic_input_payload`:** ¿El payload de entrada del módulo se serializa a JSON y se escribe en el log en `APP_ENV=local` o `APP_ENV=staging`?

#### Aislamiento y Seguridad (Obligatorio antes de todo push)
- [ ] **Fuga cero al cliente:** ¿Confirmé que ningún campo SFL (`network_latency_ms`, `db_query_status`, `tokens_in_flight`, `synaptic_input_payload`) aparece en el JSON de respuesta enviado al frontend?
- [ ] **Guard de entorno activo:** ¿El bloque SFL está envuelto en una condición `if (getenv('APP_ENV') !== 'production')` que garantiza su desactivación total en producción?
- [ ] **Log destino correcto:** ¿Los registros SFL van exclusivamente a `logs/backend.log` (o `APP_LOG_PATH`) y no a stdout, headers HTTP ni tablas de negocio expuestas?
- [ ] **Datos sensibles auditados:** ¿Revisé que `synaptic_input_payload` no contenga contraseñas, tokens JWT, API Keys ni datos personales antes de escribirlo en el log?

## 🚀 PROTOCOLO DE DESPLIEGUE (CI/CD GITHUB)
Antes de dar luz verde al pase a producción, el Agente y el Arquitecto deben confirmar:
- [ ] **Fuga de Secretos:** ¿Confirmé al 100% que el `.env` NO está siendo rastreado por Git?
- [ ] **GitHub Actions:** ¿El archivo `deploy.yml` está correctamente configurado (trigger en `main`, build correcto y acción FTP usando GitHub Secrets para las contraseñas)?
- [ ] **Validación Local:** ¿El código funciona perfectamente en local conectado a su respectiva base de datos antes del push?

## 🔒 SYSTEM IMMUTABILITY CHECK
- [ ] ¿Estoy intentando crear una tabla o campo nuevo sin permiso? (DETENERSE SI ES SÍ).
- [ ] ¿Estoy intentando "optimizar" algo que altera el Codex? (DETENERSE SI ES SÍ).

## 🛬 POST-CODE VALIDATION (AUTO-AUDITORÍA Y LINTERS)
Antes de entregar el código al usuario:
- [ ] **Linters y Formateo:** ¿El código pasó por un formateador estándar (Prettier para JS/HTML, PHP_CodeSniffer para PHP)? Cero tiempo perdido en tabulaciones manuales.
- [ ] **Limpieza:** ¿Eliminé variables e imports no usados? (Dead Code).
- [ ] **Seguridad:** ¿Sanitice inputs y protegí contra tipos erróneos (NaN/Null)?

## 🚀 PROTOCOLO DE DESPLIEGUE (CI/CD Y TESTING)
Antes de hacer PUSH a producción, el sistema debe superar estas 3 capas de verdad:
- [ ] **Smoke Tests:** ¿El login y el flujo principal (ej. proceso de pago) funcionan de inicio a fin en el entorno de Staging?
- [ ] **Contract Testing:** ¿El frontend envía exactamente lo que el backend espera y viceversa?
- [ ] **Aislamiento:** ¿Confirmé que el `.env` local no se subirá a GitHub?

## 🛡️ AUDITORÍA FINAL DE SEGURIDAD (AXON DCD)
NINGÚN proyecto se da por terminado sin pasar por el motor de inteligencia perimetral AXON DCD (`/AXON_DCD/index.php`). El Arquitecto debe confirmar:
- [ ] **Permisos Seguros:** ¿AXON DCD validó que los directorios tienen permisos `755` y los archivos `644`?
- [ ] **Cero Fugas:** ¿AXON DCD confirmó que no hay archivos críticos expuestos (`.env`, `config.php`, `.sql`)?
- [ ] **Aprobación de Radares:** ¿Se pasaron los radares de Cabeceras de Seguridad, OSINT, SSL y Escáner de Puertos?

## ✅ POST-IMPLEMENTACIÓN (DOCUMENTACIÓN VIVA Y HUB)
Después de que el usuario confirme que un componente funciona sin errores, la IA debe auditar y actualizar la documentación oficial del sistema automáticamente.
- [ ] **Codex Actualizado:** ¿Se registró la nueva tabla, variable o componente en el `02_SYSTEM_CODEX_REGISTRY.md`?
- [ ] **Actualización de Manuales:** ¿Se inyectó la explicación operativa de este nuevo módulo en el `Manual_Usuario.html` y en el `Manual_Administrador.html`?
- [ ] **Reporte de Hito (Hub):** ¿Se generó el reporte ejecutivo/técnico del avance y se indexó en la Landing Page de `/reportes/index.html` con su fecha y descripción correcta?
- [ ] **Contrato Verificado:** ¿El endpoint documentado en `03_CONTRATOS_API_Y_LOGICA.md` coincide 100% con el código final?
- [ ] **Cierre de Hito:** ¿Se informó al Arquitecto sobre el estado final y la actualización del Hub de Reportes?
- [ ] **Verificación SFL Obligatoria (Mandamiento 21 — Ley del Flujo Sináptico):** ¿Confirmé mediante referencia cruzada con el log interno que `network_latency_ms`, `db_query_status`, `tokens_in_flight` y `synaptic_input_payload` registran actividad correcta, mapean con el Diccionario SFL del Codex y no aparecen en ningún payload de respuesta al cliente? Este ítem es condición **bloqueante** para el cierre técnico del hito.

## 🚀 PROTOCOLO DE DESPLIEGUE (CI/CD GITHUB)
Antes de hacer PUSH a la rama principal en GitHub Desktop, el Agente y el Arquitecto deben confirmar:
- [ ] ¿El código funciona perfectamente en local (`C:\xampp\htdocs\PROYECTO`) conectado a la DB remota?
- [ ] ¿Los cambios en BD se aplicaron directamente en el servidor remoto?
- [ ] ¿El `.gitignore` está ocultando el `.env` para que no se suba a GitHub?
- [ ] ¿Se verificó que el `deploy.yml` (GitHub Actions) ejecutará el pase a producción sin romper la versión actual?