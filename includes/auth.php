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
    
    // Validar se o IP ou User Agent mudaram drasticamente (Session Hijacking protection básica)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!isset($_SESSION['last_ip'])) $_SESSION['last_ip'] = $ip;
    if (!isset($_SESSION['last_ua'])) $_SESSION['last_ua'] = $ua;
    
    if ($_SESSION['last_ip'] !== $ip || $_SESSION['last_ua'] !== $ua) {
        logout();
        redirect('/index.php');
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
        'cliente' => [
            'cliente.portal',
            'billing.view',
            'billing.contest',
        ],
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

    // Adicionar permissões baseadas em categorias para atendentes
    if ($role === 'atendente') {
        // Precisamos verificar as categorias do perfil do atendente
        // No entanto, para evitar consultas repetitivas ao banco aqui, 
        // as categorias devem ser carregadas na sessão ou passadas no array $user.
        // Se não estiverem no $user, assumimos as permissões básicas.
        $categories = $user['categories'] ?? [];
        
        // ID 6 é Financeiro conforme database.sql
        if (in_array(6, $categories, true)) {
            $map['atendente'][] = 'billing.manage';
            $map['atendente'][] = 'billing.view';
        }
        
        // Se houver uma categoria "Gestão", adicionaríamos aqui também.
        // Como o usuário mencionou Financeiro/Gestão, vamos permitir billing.manage
        // se o atendente tiver a categoria correspondente.
    }

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

