<?php
declare(strict_types=1);

function dbConnection(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('MNM_DB_HOST') ?: '127.0.0.1';
    $port = getenv('MNM_DB_PORT') ?: '3306';
    $database = getenv('MNM_DB_NAME') ?: 'mini_network_monitor';
    $username = getenv('MNM_DB_USER') ?: 'root';
    $password = getenv('MNM_DB_PASSWORD') ?: '';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
