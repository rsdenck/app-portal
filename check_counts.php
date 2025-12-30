<?php
require_once __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$tables = [
    'plugin_dflow_interfaces',
    'plugin_dflow_vlans',
    'plugin_dflow_devices',
    'plugin_dflow_hosts',
    'plugin_dflow_flows',
    'plugin_dflow_topology',
    'plugin_dflow_ip_blocks',
    'plugin_dflow_ip_scanning'
];

echo "Database Record Counts:\n";
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "- $table: $count\n";
    } catch (Exception $e) {
        echo "- $table: Error - " . $e->getMessage() . "\n";
    }
}
