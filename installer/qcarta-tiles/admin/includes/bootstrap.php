<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config.php';
if (!is_readable($configPath)) {
    http_response_code(500);
    echo 'Missing admin/config.php — copy config.example.php to config.php';
    exit;
}

$config = require $configPath;

$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

require_once __DIR__ . '/helpers.php';
