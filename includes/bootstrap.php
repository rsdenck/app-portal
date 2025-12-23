<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

header_remove('X-Powered-By');
header('Content-Type: text/html; charset=utf-8');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_name((string)($config['app']['session_name'] ?? 'portal_session'));
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/nsx_api.php';
require_once __DIR__ . '/netflow_api.php';
require_once __DIR__ . '/zabbix.php';
require_once __DIR__ . '/elasticsearch.php';
require_once __DIR__ . '/plugins.php';
require_once __DIR__ . '/projects.php';

$pdo = db($config);
ticket_ensure_schema($pdo);
ticket_unread_ensure_schema($pdo);
plugins_ensure_table($pdo);
projects_ensure_schema($pdo);

function upload_file(array $file, string $destinationDir): ?array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'xlsx', 'xls', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'gif'];
    
    if (!in_array($extension, $allowed, true)) {
        return null;
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $destinationDir . '/' . $newName;

    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'name' => $file['name'],
            'path' => $targetPath,
            'type' => $file['type'],
            'size' => $file['size']
        ];
    }

    return null;
}
