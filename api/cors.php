<?php

declare(strict_types=1);

function efectoAjedrezAllowedOrigins(): array
{
    $env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW);
    $raw = $env['ALLOWED_ORIGINS'] ?? '';

    return array_filter(array_map('trim', explode(',', $raw)));
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = efectoAjedrezAllowedOrigins();

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: false');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
