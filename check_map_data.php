<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$hostCount = $pdo->query("SELECT COUNT(*) FROM plugin_dflow_hosts")->fetchColumn();
$flowCount = $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows")->fetchColumn();
$latestFlow = $pdo->query("SELECT MAX(id) FROM plugin_dflow_flows")->fetchColumn();

echo "Hosts: $hostCount\n";
echo "Flows: $flowCount\n";
echo "Latest Flow ID: $latestFlow\n";
