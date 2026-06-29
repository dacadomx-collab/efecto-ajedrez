<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW);

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '',
            $env['DB_NAME'] ?? ''
        );

        self::$connection = new PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$connection;
    }
}
