<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$tables = [
    'plugin_dflow_flows',
    'plugin_dflow_hosts',
    'plugin_dflow_topology',
    'plugin_dflow_system_metrics',
    'plugin_dflow_blocked_ips',
    'plugin_dflow_bgp_prefixes',
    'plugin_dflow_threat_intel'
];

foreach ($tables as $table) {
    try {
        $pdo->exec("TRUNCATE TABLE $table");
        echo "Table $table truncated.\n";
    } catch (PDOException $e) {
        echo "Error truncating $table: " . $e->getMessage() . "\n";
    }
}
echo "DFlow cleanup complete.\n";
