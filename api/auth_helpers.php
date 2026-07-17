<?php

declare(strict_types=1);

// Helpers compartidos por el ecosistema de autenticación del Dashboard
// (setup_genesis, login, logout, usuarios_crear, usuarios_invitar,
// invitacion_confirmar). Se centraliza aquí para evitar declarar la misma
// función en 6 endpoints distintos que se necesitan entre sí.

// Ancla la zona horaria de PHP para todo el proyecto — sin esto, PHP hereda
// el timezone del sistema operativo del servidor (en el entorno local de
// esta sesión, "Europe/Berlin", confirmado comparando date() de PHP contra
// NOW() de MySQL). Esa herencia implícita es un bug latente real: afecta
// cualquier cálculo de fecha/hora de negocio (ej. "próxima sesión martes/
// jueves 8:30pm" del CRM) sin que nadie lo note hasta que el servidor migre
// de host. MySQL almacena en UTC (su timezone SYSTEM, verificado); PHP fija
// aquí su propio default a la zona horaria de referencia del proyecto
// (México, audiencia principal) — la única fuente de verdad para lógica de
// negocio, independiente del sistema operativo donde corra el proceso.
date_default_timezone_set('America/Mexico_City');

/**
 * Formatea un timestamp almacenado en UTC (MySQL) a la zona horaria de
 * La Paz, Baja California Sur — "Registro de Ingreso" del Dashboard.
 * Meses abreviados en español (no hay garantía de ext-intl disponible en
 * todos los hosts, de ahí el mapeo manual en vez de IntlDateFormatter).
 */
function formatearFechaMazatlan(string $fechaUtc): string
{
    static $mesesAbrev = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    try {
        $dt = new DateTimeImmutable($fechaUtc, new DateTimeZone('UTC'));
        $dt = $dt->setTimezone(new DateTimeZone('America/Mazatlan'));
    } catch (Exception $e) {
        return $fechaUtc;
    }

    $mes = $mesesAbrev[((int) $dt->format('n')) - 1];

    // Formato "Jul 16, 2026, 09:31 pm" — mes abreviado en español, día y
    // hora siempre a 2 dígitos (padding), am/pm en minúsculas.
    return $mes . ' ' . $dt->format('d') . ', ' . $dt->format('Y') . ', ' . $dt->format('h:i') . ' ' . $dt->format('a');
}

function jsonResponse(string $status, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerEnv(): array
{
    static $env = null;

    if ($env === null) {
        $env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW) ?: [];
    }

    return $env;
}

function calcularIpHash(): string
{
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Resolución IP → Municipio/Estado/País — MODULO_03_CRM_EVENTOS_EN_VIVO §2.
 * Este proyecto no tiene contratado ningún proveedor de geolocalización de
 * pago (sin API key en .env). Se usa ip-api.com (gratuito, sin credenciales,
 * apto para bajo volumen) como mejor esfuerzo — un fallo NUNCA bloquea el
 * registro del interesado, simplemente deja los campos en NULL
 * (Mandamiento 4: no fabricar una respuesta falsa de ubicación).
 */
function resolverGeoIp(string $ip): array
{
    $vacio = ['pais' => null, 'estado' => null, 'ciudad' => null];

    // IPs privadas/locales (desarrollo en XAMPP) no son resolubles — se evita
    // incluso intentar la llamada de red.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $vacio;
    }

    $contexto = stream_context_create(['http' => ['timeout' => 3]]);
    $respuesta = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,regionName,city', false, $contexto);

    if ($respuesta === false) {
        return $vacio;
    }

    $datos = json_decode($respuesta, true);

    if (!is_array($datos) || ($datos['status'] ?? '') !== 'success') {
        return $vacio;
    }

    return [
        'pais' => $datos['country'] ?? null,
        'estado' => $datos['regionName'] ?? null,
        'ciudad' => $datos['city'] ?? null,
    ];
}

function calcularDeviceHash(): string
{
    $env = obtenerEnv();
    // JWT_SECRET actúa como pimienta de aplicación (ya documentado en
    // .env.example como string largo y aleatorio); si aún no se ha añadido a
    // la .env real, se usa DB_PASS como respaldo — nunca cadena vacía.
    $pepper = $env['JWT_SECRET'] ?? $env['DB_PASS'] ?? '';

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    return hash('sha256', $ip . '|' . $userAgent . '|' . $pepper);
}

/**
 * $incluirGeo: SOLO true para login_exitoso (Panel "Registro de Ingreso",
 * exclusivo super_admin). Nunca true en intentos fallidos — evita exponer el
 * endpoint de login a la latencia y el límite de tasa de un proveedor de
 * geolocalización externo en cada intento, incluidos los de fuerza bruta.
 */
function registrarActividad(PDO $pdo, ?int $usuarioId, string $evento, string $detalle = '', bool $incluirGeo = false): void
{
    try {
        $ip = null;
        $geo = ['pais' => null, 'estado' => null, 'ciudad' => null];

        if ($incluirGeo) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $geo = resolverGeoIp((string) $ip);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO log_actividad (usuario_id, evento, ip_hash, device_hash, ip, ip_pais, ip_estado, ip_ciudad, detalle)
             VALUES (:usuario_id, :evento, :ip_hash, :device_hash, :ip, :ip_pais, :ip_estado, :ip_ciudad, :detalle)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':evento' => $evento,
            ':ip_hash' => calcularIpHash(),
            ':device_hash' => calcularDeviceHash(),
            ':ip' => $ip,
            ':ip_pais' => $geo['pais'],
            ':ip_estado' => $geo['estado'],
            ':ip_ciudad' => $geo['ciudad'],
            ':detalle' => $detalle,
        ]);
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] log_actividad: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    }
}

/**
 * Capa 2 — Middleware de autenticación y verificación de rol/estatus.
 * Termina la ejecución (401/403) si la sesión no es válida; retorna el
 * arreglo del usuario autenticado en caso de éxito.
 */
function requireAuth(PDO $pdo, array $rolesPermitidos = []): array
{
    $token = $_COOKIE['token_acceso'] ?? '';

    if ($token === '') {
        jsonResponse('error', 'No autenticado.', [], 401);
    }

    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, rol, estatus, token_expira_en, device_hash
         FROM usuarios WHERE token_acceso = :token LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $usuario = $stmt->fetch();

    if ($usuario === false || $usuario['token_expira_en'] === null || strtotime((string) $usuario['token_expira_en']) < time()) {
        jsonResponse('error', 'Sesión inválida o expirada.', [], 401);
    }

    if ($usuario['estatus'] === 'suspendido') {
        registrarActividad($pdo, (int) $usuario['id'], 'acceso_bloqueado', 'Cuenta suspendida.');
        jsonResponse('error', 'Cuenta suspendida.', [], 403);
    }

    if (!hash_equals((string) $usuario['device_hash'], calcularDeviceHash())) {
        registrarActividad($pdo, (int) $usuario['id'], 'device_mismatch', 'Token válido pero device_hash no coincide.');
        jsonResponse('error', 'Sesión inválida o expirada.', [], 401);
    }

    if ($rolesPermitidos !== [] && !in_array($usuario['rol'], $rolesPermitidos, true)) {
        jsonResponse('error', 'No tienes permisos para esta acción.', [], 403);
    }

    return $usuario;
}

/**
 * Motor Dinámico de Políticas de Contraseña — MODULO_01_LOGIN_Y_ACCESO §7.
 * Tres perfiles canónicos: simple (6+), media (8+ letras y números),
 * fuerte (14+ mayúscula/minúscula/número/símbolo).
 */
function politicaSeguridadDefinicion(string $perfil): array
{
    return match ($perfil) {
        'simple' => ['longitud_minima' => 6, 'requiere_mayuscula' => false, 'requiere_minuscula' => false, 'requiere_numero' => false, 'requiere_simbolo' => false],
        'fuerte' => ['longitud_minima' => 14, 'requiere_mayuscula' => true, 'requiere_minuscula' => true, 'requiere_numero' => true, 'requiere_simbolo' => true],
        default => ['longitud_minima' => 8, 'requiere_mayuscula' => false, 'requiere_minuscula' => true, 'requiere_numero' => true, 'requiere_simbolo' => false],
    };
}

/** Lee la fila única (id=1) de configuracion_seguridad. Respaldo seguro si no existe aún. */
function obtenerConfiguracionSeguridad(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT politica_password, duracion_recordarme_dias FROM configuracion_seguridad WHERE id = 1 LIMIT 1');
        $fila = $stmt->fetch();

        return [
            'politica_password' => $fila['politica_password'] ?? 'media',
            'duracion_recordarme_dias' => (int) ($fila['duracion_recordarme_dias'] ?? 60),
        ];
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] configuracion_seguridad: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');

        return ['politica_password' => 'media', 'duracion_recordarme_dias' => 60];
    }
}

function obtenerPoliticaActiva(PDO $pdo): string
{
    return obtenerConfiguracionSeguridad($pdo)['politica_password'];
}

function obtenerDuracionRecordarme(PDO $pdo): int
{
    return obtenerConfiguracionSeguridad($pdo)['duracion_recordarme_dias'];
}

/**
 * Jerarquía de roles (MODULO_01_LOGIN_Y_ACCESO §6) — recorta el rol solicitado
 * al máximo que el actor puede otorgar. Nunca confía en el payload crudo.
 * Un actor puede otorgar su propio nivel o inferior, nunca uno superior
 * (ej. admin puede crear otro admin, pero jamás un super_admin).
 */
function clamparRolSegunActor(string $rolSolicitado, string $rolActor): string
{
    $niveles = ['super_admin' => 100, 'admin' => 80];
    $nivelActor = $niveles[$rolActor] ?? 0;
    $nivelSolicitado = $niveles[$rolSolicitado] ?? 0;

    if ($nivelSolicitado <= 0 || $nivelSolicitado > $nivelActor) {
        return 'admin';
    }

    return $rolSolicitado;
}

/**
 * Mapeo Dinámico de Permisos por Módulo — MODULO_01_LOGIN_Y_ACCESO §6.1.
 * super_admin ve siempre todo (no configurable). Para otros roles, un
 * módulo sin fila se considera habilitado (fail-safe: restringe, no exige
 * registro manual de cada módulo nuevo).
 */
function esModuloVisible(PDO $pdo, string $modulo, string $rol): bool
{
    if ($rol === 'super_admin') {
        return true;
    }

    try {
        $stmt = $pdo->prepare('SELECT habilitado FROM permisos_modulos WHERE modulo = :modulo AND visible_para_rol = :rol LIMIT 1');
        $stmt->execute([':modulo' => $modulo, ':rol' => $rol]);
        $fila = $stmt->fetch();

        return $fila === false || (int) $fila['habilitado'] === 1;
    } catch (PDOException $e) {
        error_log('[' . date('Y-m-d H:i:s') . '] permisos_modulos: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');

        return true; // fail-safe: un error de lectura no debe tumbar el Dashboard
    }
}

/**
 * Núcleo Cognitivo de Bienvenida — MODULO_01_LOGIN_Y_ACCESO §10.3/10.4.
 * Este proyecto no tiene contratado ningún proveedor de IA ("AURA") — no hay
 * endpoint ni API key en .env para eso (Mandamiento 12: nunca hardcodear
 * credenciales; Mandamiento 4: nunca fabricar una integración inexistente).
 * La frase se toma de un banco curado, con selección determinística, y se
 * persiste una sola vez por día (PRIMARY KEY (fecha) evita condiciones de
 * carrera y llamadas redundantes).
 */
function bancoFrasesBienvenida(): array
{
    return [
        'Cada paso pequeño hoy es una pieza que mueves con estrategia hacia el mañana.',
        'La calma que le das a tu familia hoy es la fuerza que construyes para siempre.',
        'No necesitas tener todas las respuestas — solo la disposición de seguir intentando.',
        'Un respiro consciente vale más que mil reacciones apresuradas.',
        'Ser paciente contigo mismo(a) también es una forma de criar con amor.',
        'El progreso real casi nunca se ve en un solo día — confía en el proceso.',
        'Hoy es una buena oportunidad para elegir la calma antes que el control.',
        'Tu esfuerzo silencioso de cada día también cuenta, aunque nadie lo aplauda.',
    ];
}

function obtenerFraseBienvenidaDelDia(PDO $pdo): string
{
    $hoy = (new DateTimeImmutable())->format('Y-m-d');

    $stmt = $pdo->prepare('SELECT frase FROM frase_bienvenida_diaria WHERE fecha = :fecha LIMIT 1');
    $stmt->execute([':fecha' => $hoy]);
    $fila = $stmt->fetch();

    if ($fila !== false) {
        return $fila['frase'];
    }

    $banco = bancoFrasesBienvenida();
    // Selección determinística por día del año — misma frase para todos los
    // usuarios en el mismo día, sin aleatoriedad que complique la caché.
    $indice = ((int) (new DateTimeImmutable())->format('z')) % count($banco);
    $fraseNueva = $banco[$indice];

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO frase_bienvenida_diaria (fecha, frase, origen) VALUES (:fecha, :frase, :origen)'
        );
        $stmt->execute([':fecha' => $hoy, ':frase' => $fraseNueva, ':origen' => 'banco_estatico']);
    } catch (PDOException $e) {
        // Condición de carrera esperable (23000, duplicado) si dos requests
        // llegaron el mismo segundo — releer la fila ya insertada por la otra.
        if ($e->getCode() === '23000') {
            $stmt = $pdo->prepare('SELECT frase FROM frase_bienvenida_diaria WHERE fecha = :fecha LIMIT 1');
            $stmt->execute([':fecha' => $hoy]);
            $filaExistente = $stmt->fetch();

            return $filaExistente['frase'] ?? $fraseNueva;
        }

        error_log('[' . date('Y-m-d H:i:s') . '] frase_bienvenida_diaria: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    }

    return $fraseNueva;
}

function passwordCumplePolitica(string $password, array $definicion): bool
{
    if (mb_strlen($password) < $definicion['longitud_minima']) {
        return false;
    }

    if ($definicion['requiere_mayuscula'] && preg_match('/[A-Z]/', $password) !== 1) {
        return false;
    }

    if ($definicion['requiere_minuscula'] && preg_match('/[a-z]/', $password) !== 1) {
        return false;
    }

    if ($definicion['requiere_numero'] && preg_match('/[0-9]/', $password) !== 1) {
        return false;
    }

    if ($definicion['requiere_simbolo'] && preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
        return false;
    }

    return true;
}

function mensajePoliticaPassword(array $definicion): string
{
    $requisitos = ['mínimo ' . $definicion['longitud_minima'] . ' caracteres'];

    if ($definicion['requiere_mayuscula']) {
        $requisitos[] = 'mayúscula';
    }
    if ($definicion['requiere_minuscula']) {
        $requisitos[] = 'minúscula';
    }
    if ($definicion['requiere_numero']) {
        $requisitos[] = 'número';
    }
    if ($definicion['requiere_simbolo']) {
        $requisitos[] = 'símbolo';
    }

    return 'La contraseña debe tener ' . implode(', ', $requisitos) . '.';
}

function setCookieToken(string $token, int $ttlSegundos): void
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';

    setcookie('token_acceso', $token, [
        'expires' => time() + $ttlSegundos,
        'path' => '/',
        'secure' => $esProduccion,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function borrarCookieToken(): void
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';

    setcookie('token_acceso', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $esProduccion,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Cookie de perfil NO sensible, legible por JS (a diferencia de token_acceso)
 * — solo para que el frontend pinte nombre/rol sin otra llamada a la API.
 * Nunca contiene password_hash, token ni ningún dato de autenticación.
 */
function setCookiePerfil(array $usuario, int $ttlSegundos): void
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';

    $perfil = json_encode([
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email'],
        'rol' => $usuario['rol'],
    ], JSON_UNESCAPED_UNICODE);

    setcookie('usuario_perfil', (string) $perfil, [
        'expires' => time() + $ttlSegundos,
        'path' => '/',
        'secure' => $esProduccion,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function borrarCookiePerfil(): void
{
    $env = obtenerEnv();
    $esProduccion = ($env['APP_ENV'] ?? '') === 'production';

    setcookie('usuario_perfil', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $esProduccion,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}
