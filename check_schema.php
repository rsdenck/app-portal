<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

echo "--- plugin_dflow_hosts ---\n";
print_r($pdo->query("DESCRIBE plugin_dflow_hosts")->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- plugin_dflow_flows ---\n";
print_r($pdo->query("DESCRIBE plugin_dflow_flows")->fetchAll(PDO::FETCH_ASSOC));
