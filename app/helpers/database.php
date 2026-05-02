<?php
declare(strict_types=1);

function db_config(): array
{
    static $config = null;

    if ($config === null) {
        $loaded = require __DIR__ . '/../../config/database.php';

        if (!is_array($loaded)) {
            throw new RuntimeException('La configuracion de base de datos debe retornar un arreglo.');
        }

        $config = $loaded;
    }

    return $config;
}

function db_dsn(?array $config = null): string
{
    $config ??= db_config();

    return sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['database'],
        $config['charset'] ?? 'utf8mb4'
    );
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = db_config();
        $pdo = new PDO(
            db_dsn($config),
            $config['username'],
            $config['password'],
            $config['options'] ?? []
        );
    }

    return $pdo;
}

function db_fetch_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}
