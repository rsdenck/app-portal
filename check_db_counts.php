<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
echo "Hosts: " . $pdo->query("SELECT COUNT(*) FROM plugin_dflow_hosts")->fetchColumn() . "\n";
echo "Flows: " . $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows")->fetchColumn() . "\n";
