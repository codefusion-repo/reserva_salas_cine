<?php
declare(strict_types=1);

$config = [
    'host' => 'localhost',
    'database' => 'reserva_salas_cine',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

$localConfigPath = __DIR__ . '/database.local.php';

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (!is_array($localConfig)) {
        throw new RuntimeException('config/database.local.php debe retornar un arreglo.');
    }

    $config = array_replace_recursive($config, $localConfig);
}

return $config;
