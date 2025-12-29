<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
/** @var PDO $pdo */

echo "=== Verificação de Dados Básicos ===\n";

$checks = [
    'ticket_statuses' => 'Status de Tickets',
    'ticket_categories' => 'Categorias de Tickets',
    'asset_types' => 'Tipos de Ativos'
];

foreach ($checks as $table => $label) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "OK: $label populado ($count registros).\n";
    } else {
        echo "AVISO: $label está vazio.\n";
    }
}

// Check for at least one admin user
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'atendente'");
$adminCount = $stmt->fetchColumn();
if ($adminCount > 0) {
    echo "OK: Existem $adminCount atendentes cadastrados.\n";
} else {
    echo "AVISO: Nenhum atendente cadastrado. Use o script de instalação ou SQL manual.\n";
}
