<?php
require __DIR__ . '/includes/bootstrap.php';
$flows = $pdo->query('SELECT COUNT(*) FROM plugin_dflow_flows')->fetchColumn();
$hosts = $pdo->query('SELECT COUNT(*) FROM plugin_dflow_hosts')->fetchColumn();
echo "Flows: $flows\n";
echo "Hosts: $hosts\n";
