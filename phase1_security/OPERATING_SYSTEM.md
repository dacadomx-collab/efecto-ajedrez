# 🧠 OPERATING_SYSTEM.md — AXON_DCD
## Manual de Convenciones del Sistema · DCD LABS
**Versión:** 1.0.0 | **Autor:** DCD LABS | **Entorno:** produccion
**Generado:** 2026-06-11 07:51:27 — FoundationExecutor v1.0 — AXON_GENESIS

> Este archivo es la directiva de arranque para toda IA o desarrollador que interactúe con este repositorio.
> Cualquier desviación activa el **Protocolo Anti-Alucinación**.

---

## 1. Identificación del Proyecto

| Campo | Valor |
|:------|:------|
| **Nombre** | AXON_DCD |
| **Slug** | `axon_dcd` |
| **Versión** | 1.0.0 |
| **Descripción** | Sistema generado por AXON_GENESIS |
| **Autor / Arquitecto** | DCD LABS |
| **Entorno activo** | `produccion` |
| **Generado con** | AXON_GENESIS Cognitive Foundation Engine |

---

## 2. Stack Tecnológico

| Capa | Tecnología | Convención de Código |
|:-----|:-----------|:---------------------|
| **Backend** | PHP 8.0 | `snake_case` · `declare(strict_types=1)` en cada archivo |
| **Frontend** | Next.js 14 | `camelCase` · Componentes en `PascalCase` |
| **Base de Datos** | MySQL 8.0 | `snake_case` · Prepared Statements obligatorios |
| **Estilos** | Tailwind CSS | Utility-first · Mobile-First · Sin `!important` |

---

## 3. Arquitectura de Rutas del Proyecto

```
axon_dcd/
├── api/                        # Endpoints PHP (Gateway REST)
│   └── admin/
│       └── genesis/
├── CORE/                       # Cerebro del sistema (PDO · Auth · IA · Vault)
│   ├── src/
│   └── .env                   ← Secretos (NUNCA en repositorio)
├── public/                     # Assets estáticos públicos
├── z/                          # Frontend Next.js 14 (App Router)
│   ├── app/
│   ├── components/
│   └── lib/
│       └── api.ts             ← Cliente API canónico (sin URLs hardcodeadas)
├── knowledge/                  # Memoria inmutable del sistema (excluida del repo)
├── .env                        ← Secretos de infraestructura (excluido de Git)
├── .htaccess                   ← Blindaje Apache (7 capas)
├── .gitignore                  ← Guardrail de secretos
└── .github/
    └── workflows/
        └── deploy.yml         ← Pipeline CI/CD automatizado
```

---

## 4. Los 18 Mandamientos de DCD LABS

1. **Mobile-First:** Todo componente nace para celular. Sin anchos fijos en `px`.
2. **Seguridad Nivel Militar:** Sanitización + Prepared Statements. Blindaje SQLi, XSS, CSRF.
3. **Modo Oscuro:** Contraste mínimo WCAG 4.5:1. Tema fluido Light/Dark.
4. **Anti-Alucinación:** PROHIBIDO inventar variables. Si no está en el Codex, DETENERSE.
5. **Contrato de API Estricto:** No alterar propiedades JSON.
6. **Ejecución Determinística:** Sin "mejoras" no solicitadas.
7. **Naming Registry:** `snake_case` backend/DB · `camelCase` frontend.
8. **Dead Code:** Auditoría de huérfanos antes de cada entrega.
9. **Inmutabilidad del Schema:** No alterar tablas sin autorización explícita.
10. **Sinónimos Prohibidos:** Un solo nombre válido por concepto.
11. **Arranque Blindado:** `.env`, `.htaccess` y conexión PDO presentes.
12. **Bóveda de Secretos:** PROHIBIDO hardcodear credenciales, tokens o API Keys.
13. **Aislamiento de Entornos:** Local NUNCA apunta a DB de producción sin proxy.
14. **CORS ≠ Auth:** Todo endpoint requiere autenticación real. Sin token = 403.
15. **Agente Residente:** El `OPERATING_SYSTEM.md` gobierna el IDE.
16. **CI/CD Inquebrantable:** Deploy automático vía `deploy.yml`.
17. **Documentación Viva:** Módulo sin documentar = módulo no terminado.
18. **Auditoría AXON DCD:** Ningún proyecto a producción sin scanner perimetral.

---

## 5. Variables de Entorno Requeridas

| Variable | Descripción | Requerida |
|:---------|:------------|:---------:|
| `DB_HOST` | Host de la base de datos remota | ✅ |
| `DB_NAME` | Nombre de la base de datos | ✅ |
| `DB_USER` | Usuario de la base de datos | ✅ |
| `DB_PASS` | Contraseña de la base de datos | ✅ |
| `FTP_HOST` | Host FTP para deployment | ✅ |
| `FTP_USER` | Usuario FTP | ✅ |
| `FTP_PASSWORD` | Contraseña FTP | ✅ |
| `FTP_SERVER_DIR` | Directorio remoto FTP destino | ✅ |
| `SMTP_HOST` | Servidor SMTP transaccional | ✅ |
| `SMTP_USER` | Usuario SMTP | ✅ |
| `SMTP_PASSWORD` | Contraseña SMTP | ✅ |
| `SMTP_PORT` | Puerto SMTP (465 SSL / 587 TLS) | ✅ |
| `SMTP_FROM` | Email remitente | ✅ |
| `ALLOWED_ORIGINS` | Orígenes CORS permitidos (CSV) | ✅ |
| `NEXT_PUBLIC_API_URL` | URL del backend para el frontend | ✅ |
| `AI_ENCRYPTION_KEY` | Clave maestra de cifrado IA (64 hex chars) | ✅ |
| `GENESIS_VAULT_KEY` | Clave maestra GenesisVault AES-256-GCM (64 hex chars) | ✅ |

---

## 6. Guía de Deploy Automatizado

El deploy es **100% automatizado** vía GitHub Actions (`deploy.yml`):

1. Push a rama `main` → dispara el workflow automáticamente.
2. GitHub Actions instala dependencias (`npm ci --prefer-offline`).
3. Compila el frontend Next.js (`npm run build`) → genera `/z/out`.
4. Verifica que `/z/out` existe y contiene archivos.
5. Sube PHP + assets vía FTP a `/public_html/` en el servidor Hostinger.

### GitHub Secrets Requeridos

Configura en `Settings → Secrets and variables → Actions`:

| Secret | Fuente (desde `.env`) |
|:-------|:----------------------|
| `FTP_HOST` | Variable `FTP_HOST` |
| `FTP_USERNAME` | Variable `FTP_USER` |
| `FTP_PASSWORD` | Variable `FTP_PASSWORD` |
| `FTP_SERVER_DIR` | Variable `FTP_SERVER_DIR` |
| `NEXT_PUBLIC_API_URL` | Variable `NEXT_PUBLIC_API_URL` |

---

*© 2026 DCD LABS — DCD LABS · Generado por AXON_GENESIS Cognitive Foundation Engine*