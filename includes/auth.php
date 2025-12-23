<?php

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) {
        return null;
    }
    if (!isset($user['id'], $user['role'], $user['name'], $user['email'])) {
        return null;
    }
    return $user;
}

function require_login(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        redirect('/index.php');
    }
    if ($role !== null && ($user['role'] ?? null) !== $role) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function user_has_permission(array $user, string $permission): bool
{
    $role = (string)($user['role'] ?? '');
    $map = [
        'cliente' => ['cliente.portal'],
        'atendente' => [
            'cliente.portal',
            'atendente.portal',
            'admin.tickets',
            'admin.clients',
            'admin.config',
            'admin.docs',
            'admin.monitoramento',
        ],
    ];
    $allowed = $map[$role] ?? [];
    return in_array($permission, $allowed, true);
}

function require_permission(string $permission): array
{
    $user = require_login();
    if (!user_has_permission($user, $permission)) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}
