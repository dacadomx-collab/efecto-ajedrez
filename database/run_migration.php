<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso denegado: este script solo se ejecuta por CLI.');
}

require_once __DIR__ . '/../api/conexion.php';

$archivo = $argv[1] ?? null;

if ($archivo === null) {
    fwrite(STDERR, "Uso: php run_migration.php <archivo.sql>\n");
    exit(1);
}

$rutaSql = __DIR__ . '/' . $archivo;

if (!is_file($rutaSql)) {
    fwrite(STDERR, "Archivo no encontrado: {$rutaSql}\n");
    exit(1);
}

$sql = file_get_contents($rutaSql);

try {
    $pdo = (new Database())->getConnection();
    $pdo->exec($sql);
    echo "Migración aplicada correctamente: {$archivo}\n";
} catch (PDOException $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] {$archivo}: " . $e->getMessage() . "\n");
    exit(1);
}
