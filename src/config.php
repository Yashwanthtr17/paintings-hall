<?php
declare(strict_types=1);

if (!function_exists('getDb')) {
    function getDb(): PDO {
        $host = $_ENV['DB_HOST'] ?? 'db';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $dbname = $_ENV['DB_NAME'] ?? 'paintings_hall';
        $user = $_ENV['DB_USER'] ?? 'paintings';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $user, $pass, $options);
    }
}
