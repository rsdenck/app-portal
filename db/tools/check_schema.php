<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
/** @var PDO $pdo */

echo "=== Verificação de Schema do Banco de Dados ===\n";

$requiredTables = [
    'users',
    'client_profiles',
    'attendant_profiles',
    'ticket_categories',
    'ticket_statuses',
    'tickets',
    'ticket_history',
    'boletos',
    'zabbix_hostgroups',
    'zabbix_settings',
    'audit_logs',
    'asset_types',
    'assets'
];

$missing = [];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    if ($stmt->rowCount() === 0) {
        $missing[] = $table;
    }
}

if (empty($missing)) {
    echo "OK: Todas as tabelas obrigatórias existem.\n";
} else {
    echo "ERRO: As seguintes tabelas estão faltando:\n";
    foreach ($missing as $m) {
        echo " - $m\n";
    }
    echo "\nExecute 'mysql -u root -p portal_db < db/database.sql' para corrigir.\n";
}
