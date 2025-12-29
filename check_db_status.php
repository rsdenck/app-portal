<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$tables = ['plugin_dflow_hosts', 'plugin_dflow_flows', 'plugin_dflow_topology', 'plugin_dflow_interfaces'];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "$table: $count rows\n";
    } catch (Exception $e) {
        echo "$table: Error - " . $e->getMessage() . "\n";
    }
}
