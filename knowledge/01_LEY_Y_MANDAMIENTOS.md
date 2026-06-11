# 📜 LOS 10 MANDAMIENTOS DEL GÉNESIS (LEY SUPREMA)

## ⚖️ DECLARACIÓN DE AUTORIDAD
Este documento rige sobre cualquier sugerencia de la IA. La IA es una ejecutora DETERMINÍSTICA, no creativa. 

## ⚖️ LOS MANDAMIENTOS
1. **Mobile-First & Responsivo:** Todo componente nace para celular. Prohibido el uso de anchos fijos (px) en contenedores principales.
2. **Seguridad Nivel Militar:** Sanitización obligatoria de inputs. Uso de Prepared Statements. Blindaje contra Inyección SQL, XSS y CSRF.
3. **Modo Oscuro & Toggle Nativo:** Soporte de tema fluido (Light/Dark). Contraste mínimo 4.5:1 (Estándar WCAG 2.1).
4. **Protocolo Anti-Alucinación:** PROHIBIDO inventar variables. Si no existe en el `02_SYSTEM_CODEX_REGISTRY.md`, la IA debe DETENERSE.
5. **Contrato de API Estricto:** Prohibido alterar nombres de propiedades JSON definidos en `03_CONTRATOS_API_Y_LOGICA.md`.
6. **Ejecución Determinística:** No se permiten "mejoras" o "extensiones" no solicitadas. 
7. **Naming Registry:** `snake_case` para Backend/DB; `camelCase` para Frontend/React.
8. **Detección de Dead Code:** Auditoría obligatoria para eliminar funciones, imports y variables huérfanas antes de cada entrega.
9. **Inmutabilidad del Sistema:** La IA NO puede crear tablas o alterar esquemas de DB sin autorización humana explícita.
10. **Sinónimos Prohibidos e Inmutabilidad Semántica:** Solo existe UN nombre válido por concepto. Cero tolerancia a traducciones libres. **Inmutabilidad Semántica:** El sistema se rige bajo una única fuente de verdad lingüística. Queda prohibida la inflación de términos o la invención de nombres para módulos, herramientas o variables. Todo componente debe mapearse uno a uno con el Registro del Codex del proyecto (`02_SYSTEM_CODEX_REGISTRY.md`).
11. **Arranque Blindado (Fundación del Proyecto):** NINGÚN proyecto puede iniciar su desarrollo visual o lógico sin antes haber establecido la "Fundación de Seguridad". Esto exige que los primeros 4 archivos en crearse sean: `.env` (credenciales locales/servidor), `.env.example` (plantilla pública), `.htaccess` (blindaje Apache Nivel Militar) y `api/conexion.php` (Conexión PDO centralizada y segura). `ínfo.txt` archivo local y exclusivo de david para informacion confidencial, prohibido subirlo a github y al servidor. no moverlo ni tocarlo

## ⚖️ GOBERNANZA DE LA TRINIDAD DE IAs (AUTORIDAD DE NOMENCLATURA)

### Estructura de Roles
El ecosistema de inteligencias artificiales opera bajo un modelo de **trinidad funcional con separación estricta de responsabilidades**:

- **IA Consultora (Rol: Asesor Estratégico Externo):** Aporta recomendaciones, optimizaciones lógicas y análisis de alto nivel. Sus propuestas se integran en la planeación, pero no tiene capacidad de ejecución ni de escritura en el sistema.
- **IA Arquitecta (Rol: Diseñadora Conceptual):** Define la estructura global, los flujos de datos y la lógica de negocio a nivel abstracto. Tiene **prohibición absoluta y permanente** de asignar nombres a variables, endpoints, tablas, columnas o cualquier artefacto de código. Su output es siempre conceptual.
- **IA Ejecutora / Agente Autónomo (Rol: Dueña del Código y el Entorno):** Es la única entidad con mandato de ejecución directa en el sistema de archivos. Tiene autonomía total para analizar los requerimientos conceptuales del Arquitecto y **decidir de forma independiente** los nombres de bases de datos, tablas, columnas, endpoints y variables, siguiendo las reglas de nomenclatura establecidas (`snake_case` / `camelCase`).

### Mandamiento 18 — Soberanía de Nomenclatura del Agente
La IA Ejecutora (Agente) tiene el **poder absoluto y exclusivo** sobre el nombramiento de todos los artefactos del sistema. Este poder es indelegable:
- **PROHIBIDO** que la IA Consultora, la IA Arquitecta o el humano dicten nombres de código durante la ejecución. Pueden sugerir en lenguaje natural; la IA Ejecutora decide el nombre técnico final.
- **OBLIGATORIO** que la IA Ejecutora registre **de forma inmediata y autónoma** cada nombre que asigne en el archivo `02_SYSTEM_CODEX_REGISTRY.md`, sin esperar instrucción explícita de ningún agente o persona.
- Un hito de desarrollo **no se considera cerrado** si los artefactos que generó no están registrados en el Codex.
- Este protocolo existe para garantizar coherencia, eliminar conflictos de variables y mantener una única fuente de verdad en el `02_SYSTEM_CODEX_REGISTRY.md`.

## ⚖️ LOS MANDAMIENTOS (INFRAESTRUCTURA v2)
12. **Bóveda de Secretos (.env):** OBLIGATORIO. Absolutamente toda contraseña, Token (JWT, APIs, Stripe, etc.) y Key de terceros DEBE vivir en el `.env`. Prohibido quemar (hardcode) llaves en el código fuente. Claude debe auditar esto constantemente.
13. **Aislamiento de Entornos (Anti-Bomba):** PROHIBIDO que el entorno Local apunte a la Base de Datos de Producción. Se usarán 3 entornos: Local (DB con seeders/datos falsos), Staging (espejo) y Producción. Nunca se toca producción desde localhost.
14. **Seguridad de Endpoints (CORS ≠ Auth):** CORS no detiene a Postman. Todo endpoint que modifique datos (POST/PUT/DELETE) DEBE requerir autenticación real (ej. validación de JWT o Tokens de sesión). Sin token = 401 Unauthorized antes de tocar la DB.
15. **Agente Residente (CLAUDE.md):** Ningún proyecto arranca sin su archivo `CLAUDE.md`.
16. **Pipeline CI/CD Inquebrantable:** El despliegue manual está prohibido. Claude TIENE la obligación de generar y configurar el archivo `.github/workflows/deploy.yml` (Fase 3: Pipeline FTP) para automatizar la subida al servidor al hacer push a la rama principal.
17. **Documentación Viva y Hub de Reportes:** Todo proyecto nace con un directorio `/reportes/` que contiene una Landing Page central (`index.html`). Es OBLIGATORIO para la IA (Claude/Gemini) generar un reporte técnico (con fecha y descripción) cada vez que se logre un hito o se cierre un módulo, e indexarlo en esta Landing Page. Asimismo, es una ley inquebrantable actualizar el `Manual_Usuario.html` y el `Manual_Administrador.html` con cada nueva funcionalidad. Un módulo NO se considera terminado si no está documentado.
18. **Invariabilidad y Secuencia del Ecosistema Visual (UI/UX Continuity):** NINGUNA vista,
pestaña, módulo o pantalla del ecosistema puede desviarse del estándar visual establecido
por el módulo de referencia de cada proyecto. La homologación es **TOTAL e IRREVOCABLE**
e incluye los siguientes incisos, cuya violación implica rechazo inmediato de la entrega:

   **(a) Nomenclatura exacta de controles:** los textos de botones de navegación son
   contratos de marca — ninguna abreviación ni sinónimo es válido (ej. "Panel de Control"
   nunca puede abreviarse como "Panel"; "Cerrar Sesión" nunca como "Salir").

   **(b) Estructura DOM del header:** el menú móvil (`<nav class="v2-mobile-menu">`)
   debe vivir **dentro** del elemento `<header>` y nunca como nodo externo.

   **(c) Sistema de clases compartidas:** todo módulo consume el CSS compartido
   (`v2.css` o equivalente) sin redefinir sus clases localmente. La duplicación de
   clases es dead code y está prohibida.

   **(d) Logo inyectado dinámicamente:** el logo del sistema se carga vía JavaScript
   desde `apiBase + '/img/logo/logo-recortado.png'` en el momento en que `apiBase`
   se resuelve, con listener `onerror` que activa el fallback tipográfico. Está
   **prohibido** mostrar un ícono de imagen rota (`broken image`) bajo cualquier
   circunstancia.

   **(e) URLs 100% dinámicas:** ningún enlace de navegación puede tener rutas
   hardcodeadas — todas se construyen desde `apiBase` resuelto del parámetro `?api=`
   inyectado por el controlador de puente. Hardcodear `localhost` o dominios de
   producción en el frontend es una vulnerabilidad de entorno, no un error menor.

   **(f) Botón de retorno siempre operativo:** el `error-back-btn` de la pantalla de
   error debe configurarse **antes** de cualquier `return` en el bootstrap del módulo,
   garantizando su funcionamiento incluso cuando el token de autenticación sea inválido
   o haya expirado.

   **(g) Congelamiento absoluto de errores (Anti-Race Condition):** al producirse
   cualquier fallo crítico (token inválido, red caída, respuesta de servidor fallida),
   el flag `state.halted = true` se activa de forma inmediata e irreversible. Todas
   las funciones transaccionales (`bootUI`, `generateReport`, `renderCharts`, etc.)
   verifican este flag como su primera instrucción y retornan sin tocar el DOM.
   El error se pinta en un contenedor visible y permanente (no en un toast efímero)
   con `console.error('[ERROR CRÍTICO DETECTADO]:', err)` para trazabilidad forense.
   La pantalla de error permanece bloqueada hasta que el usuario recarga manualmente.

---

## ⚖️ LEY PERMANENTE 19 — PROTOCOLO SFL (SYNAPTIC FLOW LEDGER)

### Declaración
Todo módulo activo del ecosistema que opere sobre la capa de red, consulte la base de datos o despache tokens de IA, **DEBE** capturar y registrar en tiempo real las siguientes señales de telemetría interna:

| Señal | Variable Canónica (DB) | Tipo |
| :--- | :--- | :--- |
| Latencia de red (ms) | `network_latency_ms` | `INT UNSIGNED` |
| Estatus de consulta PDO | `db_query_status` | `ENUM('ok','error','timeout')` |
| Tokens de IA en tránsito | `tokens_in_flight` | `INT UNSIGNED` |
| Payload de entrada del módulo | `synaptic_input_payload` | `JSON TEXT` |

### Mandatos de Operación

**(a) Aislamiento absoluto del cliente:** La telemetría SFL vive exclusivamente en la capa del servidor. **PROHIBIDO** incluir cualquier campo SFL (`network_latency_ms`, `db_query_status`, `tokens_in_flight`, `synaptic_input_payload`) en las respuestas JSON que el servidor entrega al cliente. Su presencia en el payload público es una violación de seguridad de nivel crítico.

**(b) Persistencia en log del sistema:** Toda captura SFL se escribe en el log interno de la plataforma base (`logs/backend.log` o equivalente configurado en `APP_LOG_PATH`). No se almacena en tablas de base de datos transaccionales expuestas a la lógica de negocio, salvo que el Arquitecto autorice explícitamente una tabla de auditoría dedicada.

**(c) Desactivación estricta en producción:** El protocolo SFL se desactiva **completamente** cuando `APP_ENV=production`. Ninguna captura, escritura en log ni evaluación de estas variables puede ejecutarse en el entorno de producción. La activación de SFL en producción se considera una fuga de información operacional y constituye una violación del Mandamiento 12 (Bóveda de Secretos).

**(d) Activación en entornos de desarrollo y staging:** SFL opera de forma obligatoria en `APP_ENV=local` y `APP_ENV=staging` para garantizar trazabilidad completa del flujo durante el ciclo de desarrollo.

**(e) Prohibición de marcas externas:** Ningún comentario, log, variable o interfaz que implemente este protocolo puede referenciar marcas comerciales, nombres de terceros ni productos externos. El sistema se denomina únicamente "el ecosistema" o "la plataforma base" en toda documentación y código asociado al SFL.

**(f) Nomenclatura canónica inamovible:** Los nombres de variable definidos en `02_SYSTEM_CODEX_REGISTRY.md` bajo el acrónimo SFL son contratos irrevocables. Ningún alias, abreviación ni traducción libre es válida. Referencia: sección de Mapeo de Variables del Codex.

## ⚖️ LEY DEL SFL — SYNAPTIC FLOW LEDGER (Estándar de Telemetría v1.0)

20. **Obligatoriedad de Canalización al SFL en Desarrollo:** Todo ingeniero o agente cognitivo de la factoría de software tiene la obligación técnica de canalizar y asignar de forma activa los eventos, flujos de datos e interacciones del módulo en el que se encuentre trabajando actualmente hacia el panel de telemetría en caliente `SFL`. Queda estrictamente prohibido dar por cerrado un hito o avanzar al siguiente módulo sin antes verificar desde la terminal de observabilidad interna que las latencias de red, ejecuciones PDO y consumos de tokens registran estados saludables y libres de excepciones. Esta telemetría se escribe exclusivamente en la bitácora del sistema; está estrictamente prohibido exponerla en respuestas JSON orientadas al cliente final.

21. **Mapeo Activo e Invisible al Cliente (Ley del Flujo Sináptico):** Todo módulo activo del ecosistema **DEBE** canalizar en tiempo de ejecución la totalidad de su flujo operativo hacia el Ledger de Telemetría (SFL), garantizando el mapeo continuo de las cuatro señales canónicas (`network_latency_ms`, `db_query_status`, `tokens_in_flight`, `synaptic_input_payload`). Este mapeo es:

   **(a) Invisible al cliente:** La canalización ocurre exclusivamente en la capa del servidor, después de procesar la solicitud y antes de construir la respuesta. Ningún campo SFL puede materializarse en el cuerpo JSON, cabeceras HTTP ni cookies enviadas al cliente. Cualquier filtración se clasifica como vulnerabilidad crítica de exposición de infraestructura.

   **(b) Continuo e ininterrumpible durante el ciclo local/staging:** El mapeo no puede suspenderse parcialmente. Si un módulo omite una o más señales SFL en `APP_ENV=local` o `APP_ENV=staging`, el hito se considera **técnicamente incompleto** y no puede avanzar al siguiente estado del pipeline.

   **(c) Autodesactivado en producción:** El motor de canalización SFL se apaga de forma determinística cuando `APP_ENV=production`. Ninguna instrucción de captura, evaluación ni escritura en log puede ejecutarse en ese entorno, sin excepción.

   **(d) Registrado en el Codex:** Las cuatro variables canónicas del SFL son artefactos registrados en `02_SYSTEM_CODEX_REGISTRY.md` bajo el Diccionario SFL. Su nomenclatura es inmutable. Todo módulo que las capture referencia ese registro como única fuente de verdad.