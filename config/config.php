<?php

return [
    'app' => [
        'base_url' => '',
        'session_name' => 'portal_session',
    ],
    'db' => [
        'dsn' => getenv('PORTAL_DB_DSN') ?: sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            getenv('PORTAL_DB_HOST') ?: '127.0.0.1',
            getenv('PORTAL_DB_PORT') ?: '3306',
            getenv('PORTAL_DB_NAME') ?: 'portal',
            getenv('PORTAL_DB_CHARSET') ?: 'utf8mb4'
        ),
        'user' => getenv('PORTAL_DB_USER') ?: 'admin',
        'pass' => getenv('PORTAL_DB_PASS') ?: 'admin',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    'storage' => [
        'boletos_dir' => __DIR__ . '/../storage/boletos',
    ],
    'zabbix' => [
        'url' => 'https://monitoramento.armazem.cloud/api_jsonrpc.php',
        'user' => '',
        'pass' => '',
        'token' => '',
        'timeout_seconds' => 15,
    ],
];

