<?php
require_once __DIR__ . '/includes/bootstrap.php';
$stmt = $pdo->prepare('SELECT count(*) FROM plugin_dflow_hosts WHERE vlan = 10');
$stmt->execute();
echo 'Hosts in VLAN 10: ' . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query('SELECT ip_address, vlan FROM plugin_dflow_hosts WHERE vlan > 0 LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
