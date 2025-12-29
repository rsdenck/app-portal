<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$flows = $pdo->query("SELECT * FROM plugin_dflow_flows LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "plugin_dflow_flows sample:\n";
print_r($flows);

$hosts = $pdo->query("SELECT * FROM plugin_dflow_hosts LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "\nplugin_dflow_hosts sample:\n";
print_r($hosts);
