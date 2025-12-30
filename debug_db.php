<?php
$pdo = new PDO('mysql:host=localhost;dbname=portal', 'root', '');
echo "VLANs:\n";
print_r($pdo->query('SELECT * FROM plugin_dflow_vlans')->fetchAll(PDO::FETCH_ASSOC));
echo "\nHosts:\n";
print_r($pdo->query('SELECT * FROM plugin_dflow_hosts LIMIT 5')->fetchAll(PDO::FETCH_ASSOC));
echo "\nInterfaces:\n";
print_r($pdo->query('SELECT * FROM plugin_dflow_interfaces LIMIT 5')->fetchAll(PDO::FETCH_ASSOC));
