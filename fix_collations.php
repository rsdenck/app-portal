<?php
require_once __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$tables = [
    'plugin_dflow_flows',
    'plugin_dflow_hosts',
    'plugin_dflow_interfaces',
    'plugin_dflow_vlans',
    'plugin_dflow_devices',
    'plugin_dflow_topology',
    'plugin_dflow_recon',
    'plugin_dflow_ip_scanning',
    'plugin_dflow_ip_blocks'
];

echo "Fixing table collations to utf8mb4_unicode_ci...\n";

foreach ($tables as $table) {
    try {
        $pdo->exec("ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Fixed $table\n";
    } catch (Exception $e) {
        echo "Error fixing $table: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
