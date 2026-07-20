<?php

declare(strict_types=1);

// CAPA 1 — CORS
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/conexion.php';

const REGISTROS_POR_PAGINA = 15;

// CAPA 3 — Método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse('error', 'Método no permitido.', [], 405);
}

$pdo = (new Database())->getConnection();

// CAPA 2 — Auth middleware: exclusivo super_admin (mismo alcance que el
// resto de la auditoría de accesos, MODULO_01_LOGIN_Y_ACCESO §9.3).
requireAuth($pdo, ['super_admin']);

// CAPA 4 — Payload (querystring)
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$buscar = isset($_GET['buscar']) ? trim((string) $_GET['buscar']) : '';

if (mb_strlen($buscar) > 190) {
    jsonResponse('error', 'Término de búsqueda inválido.', [], 422);
}

// CAPA 5 — Persistencia
try {
    // "Borrar" en este panel oculta (log_actividad_ocultos), nunca hace
    // DELETE real -- log_actividad es una bitácora append-only bloqueada por
    // trigger a nivel de BD (trg_log_actividad_no_delete). Se excluye aquí
    // cualquier fila ya ocultada por un super_admin.
    $filtroWhere = "la.evento = 'login_exitoso' AND o.log_actividad_id IS NULL";
    $parametros = [];

    if ($buscar !== '') {
        // PDO::ATTR_EMULATE_PREPARES=false (api/conexion.php) usa prepared
        // statements nativos de MySQL, que no soportan reutilizar el mismo
        // placeholder con nombre más de una vez en la misma consulta —
        // provocaba un 500 en cualquier búsqueda. Un placeholder distinto
        // por ocurrencia, mismo valor.
        $filtroWhere .= ' AND (u.nombre LIKE :buscar1 OR u.email LIKE :buscar2 OR la.ip_ciudad LIKE :buscar3 OR la.ip_estado LIKE :buscar4 OR la.ip_pais LIKE :buscar5)';
        $comodin = '%' . $buscar . '%';
        $parametros[':buscar1'] = $comodin;
        $parametros[':buscar2'] = $comodin;
        $parametros[':buscar3'] = $comodin;
        $parametros[':buscar4'] = $comodin;
        $parametros[':buscar5'] = $comodin;
    }

    $stmtTotal = $pdo->prepare(
        "SELECT COUNT(*) FROM log_actividad la
         LEFT JOIN usuarios u ON u.id = la.usuario_id
         LEFT JOIN log_actividad_ocultos o ON o.log_actividad_id = la.id
         WHERE {$filtroWhere}"
    );
    $stmtTotal->execute($parametros);
    $total = (int) $stmtTotal->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / REGISTROS_POR_PAGINA));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * REGISTROS_POR_PAGINA;

    $stmt = $pdo->prepare(
        "SELECT la.id, la.evento, la.ip, la.ip_pais, la.ip_estado, la.ip_ciudad, la.created_at, u.nombre, u.email
         FROM log_actividad la
         LEFT JOIN usuarios u ON u.id = la.usuario_id
         LEFT JOIN log_actividad_ocultos o ON o.log_actividad_id = la.id
         WHERE {$filtroWhere}
         ORDER BY la.created_at DESC
         LIMIT " . REGISTROS_POR_PAGINA . " OFFSET {$offset}"
    );
    $stmt->execute($parametros);
    $registros = $stmt->fetchAll();

    $datos = array_map(function (array $registro): array {
        return [
            'id' => (int) $registro['id'],
            'nombre' => $registro['nombre'] ?? '—',
            'email' => $registro['email'] ?? '—',
            'ip' => $registro['ip'] ?? '—',
            'ubicacion' => implode(', ', array_filter([$registro['ip_ciudad'], $registro['ip_estado'], $registro['ip_pais']])) ?: '—',
            'fecha' => formatearFechaMazatlan((string) $registro['created_at']),
        ];
    }, $registros);

    jsonResponse('success', 'Registros obtenidos.', [
        'registros' => $datos,
        'pagina' => $pagina,
        'total_paginas' => $totalPaginas,
        'total' => $total,
    ]);
} catch (PDOException $e) {
    // CAPA 6 — Try/Catch global
    error_log('[' . date('Y-m-d H:i:s') . '] registro_ingreso_listar.php: ' . $e->getCode() . ' — ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'No pudimos obtener el registro de ingreso.', [], 500);
}
