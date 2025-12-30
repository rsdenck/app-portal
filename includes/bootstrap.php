<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

header_remove('X-Powered-By');
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");
header('Referrer-Policy: strict-origin-when-cross-origin');

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
require_once __DIR__ . '/snmp_api.php';

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/nsx_api.php';
require_once __DIR__ . '/netflow_api.php';
require_once __DIR__ . '/zabbix.php';
require_once __DIR__ . '/veeam_api.php';
require_once __DIR__ . '/vcenter_api.php';
require_once __DIR__ . '/elasticsearch.php';
require_once __DIR__ . '/plugins.php';
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/dflow.php';

$pdo = db($config);
ticket_ensure_schema($pdo);
user_ensure_schema($pdo);
ticket_unread_ensure_schema($pdo);
plugins_ensure_table($pdo);
projects_ensure_schema($pdo);
dflow_ensure_tables($pdo);

if (!function_exists('upload_file')) {
    function upload_file(array $file, string $destinationDir): ?array
    {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Proteção contra path traversal no diretório
    $baseUploadDir = realpath(__DIR__ . '/../uploads');
    if (!$baseUploadDir) {
        $baseUploadDir = __DIR__ . '/../uploads';
        if (!is_dir($baseUploadDir)) {
            mkdir($baseUploadDir, 0755, true);
        }
        $baseUploadDir = realpath($baseUploadDir);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'xlsx', 'xls', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'gif'];
    
    if (!in_array($extension, $allowed, true)) {
        return null;
    }

    // Validar tipo MIME real (MIME sniffing prevention)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMime = [
        'application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'image/png', 'image/jpeg', 'image/gif'
    ];
    if (!in_array($mimeType, $allowedMime, true)) {
        return null;
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $destinationDir . '/' . $newName;

    // Garantir que o diretório de destino não contém caracteres nulos
    if (str_contains($destinationDir, "\0")) {
        return null;
    }

    // Garantir que o destino está dentro da pasta de uploads permitida
    if (!str_starts_with(realpath($destinationDir) ?: $destinationDir, $baseUploadDir)) {
        return null;
    }

    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'name' => htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
            'path' => $targetPath,
            'type' => $mimeType,
            'size' => $file['size']
        ];
    }

    return null;
    }
}

