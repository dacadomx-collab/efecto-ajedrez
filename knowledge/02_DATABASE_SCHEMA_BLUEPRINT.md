# 🗄️ 02 — DATABASE SCHEMA BLUEPRINT

> **Fuente de verdad única** para toda la estructura de datos del proyecto.
> La IA Ejecutora debe registrar AQUÍ cualquier tabla, columna o tipo de dato que cree o modifique.

---

## 📌 NOTA DE FUNDACIÓN

Todas las conexiones a esta base de datos deben realizarse **obligatoriamente** a través de la clase centralizada en `api/conexion.php`, leyendo las variables `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` del archivo `.env`.

**PROHIBIDO** instanciar PDO directamente en un endpoint. **PROHIBIDO** hardcodear credenciales.

---

## 📊 MAPEO DE VARIABLES VALIDADAS (FRONT vs BACK)

| Concepto | DB / Backend (`snake_case`) | Frontend (`camelCase`) | Tipo de Dato |
| :--- | :--- | :--- | :--- |
| [CONCEPTO_1] | `variable_back` | `variableFront` | [String / Int / Bool / Timestamp] |
| [CONCEPTO_2] | `variable_back` | `variableFront` | [String / Int / Bool / Timestamp] |

> **Regla de Hierro (Mandamiento 7):** `snake_case` para todo lo que vive en la DB o el backend PHP. `camelCase` para todo lo que vive en el frontend JS/React. Cero excepciones.

---

## 🗄️ ESTRUCTURA DE TABLAS (SCHEMA)

### Convenciones Globales de Diseño
- Toda tabla tiene `id` (INT, AUTO_INCREMENT, PK), `created_at` (TIMESTAMP DEFAULT NOW()), `updated_at` (TIMESTAMP DEFAULT NOW() ON UPDATE NOW()).
- Charset: `utf8mb4`. Collation: `utf8mb4_unicode_ci`.
- Motor: `InnoDB` (soporte de transacciones y FK).
- Soft-delete preferido: columna `deleted_at` (TIMESTAMP NULL) en lugar de `DELETE` físico.

---

### Tabla: `[nombre_tabla]`
**Propósito:** [Descripción de qué almacena esta tabla]

| Columna | Tipo | Nulo | Default | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `[columna_1]` | VARCHAR(255) | NO | — | [Descripción] |
| `[columna_2]` | TINYINT(1) | NO | 0 | [Descripción, ej: 1=activo, 0=inactivo] |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Última modificación |

**Índices:**
- `PRIMARY KEY (id)`
- `INDEX idx_[campo] ([campo])` — [Justificación del índice]

**Foreign Keys:**
- `[columna_fk]` → `[tabla_referenciada].[id]` ON DELETE [CASCADE / SET NULL / RESTRICT]

---

## 🧠 REGISTRO SEMÁNTICO (VOCABULARIO CONTROLADO)

Este registro define los nombres técnicos oficiales del proyecto. La IA Ejecutora es la **única autoridad** para asignar nombres; el Arquitecto propone en lenguaje natural.

| Concepto de Negocio | Nombre Técnico Oficial | Notas |
| :--- | :--- | :--- |
| [Concepto natural] | `nombre_tecnico` | [Notas de uso o restricciones] |

- ✅ **Términos Permitidos:** `[termino_1]`, `[termino_2]`
- ❌ **Términos Prohibidos:** `[sinonimo_1]`, `[traduccion_libre]`

---

## 🧩 REGISTRO DE COMPONENTES FRONTEND

| Componente | Ruta | Tipo | Estado | Props Principales |
| :--- | :--- | :--- | :--- | :--- |
| `[NombreComponente]` | `[ruta/archivo.tsx]` | [UI / Logic / Page] | [Active / WIP / Deprecated] | `[prop1, prop2]` |

**Reglas de Interfaz Aplicadas:**
- `[Componente_1]`: [Breve descripción de la regla de negocio que cumple]
