<?php

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function require_method_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(): void
{
    require_method_post();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(400);
        exit('CSRF validation failed');
    }
}

function safe_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return null;
    }
    if ($value === '' || !preg_match('/^\d+$/', $value)) {
        return null;
    }
    $intVal = (int)$value;
    if ((string)$intVal !== ltrim($value, '0') && $value !== '0') {
        return $intVal;
    }
    return $intVal;
}

function truncate(string $text, int $length = 120): string
{
    if (function_exists('mb_substr')) {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
    return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
}

function realpath_under_base(string $baseDir, string $relativePath): ?string
{
    if (str_contains($relativePath, "\0")) {
        return null;
    }

    $relativePath = str_replace(['\\', "\r", "\n"], ['/', '', ''], $relativePath);
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '' || str_contains($relativePath, '../') || str_contains($relativePath, '/..')) {
        return null;
    }

    $baseReal = realpath($baseDir);
    if ($baseReal === false) {
        return null;
    }

    $candidate = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $candidateReal = realpath($candidate);
    if ($candidateReal === false) {
        return null;
    }

    $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $candidatePrefix = rtrim($candidateReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($candidatePrefix, $basePrefix)) {
        return null;
    }

    return $candidateReal;
}

function format_bytes(mixed $bytes): string
{
    if ($bytes === null || !is_numeric($bytes)) {
        return 'N/A';
    }
    $bytes = (float)$bytes;
    if ($bytes < 0) {
        return 'N/A';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $idx = 0;
    while ($bytes >= 1024 && $idx < count($units) - 1) {
        $bytes /= 1024;
        $idx++;
    }
    return number_format($bytes, 2) . ' ' . $units[$idx];
}

function format_percent(mixed $value): string
{
    if ($value === null || !is_numeric($value)) {
        return 'N/A';
    }
    return number_format((float)$value, 1) . '%';
}

function format_bps(mixed $bps): string
{
    if ($bps === null || !is_numeric($bps)) {
        return '0 bps';
    }
    $bps = (float)$bps;
    if ($bps >= 1000000000) return number_format($bps / 1000000000, 1) . ' Gbps';
    if ($bps >= 1000000) return number_format($bps / 1000000, 1) . ' Mbps';
    if ($bps >= 1000) return number_format($bps / 1000, 1) . ' Kbps';
    return number_format($bps, 0) . ' bps';
}


