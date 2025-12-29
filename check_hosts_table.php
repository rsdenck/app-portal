<?php
$config = require 'config/config.php';
$db = $config['db'];
$pdo = new PDO($db['dsn'], $db['user'], $db['pass']);
$count = $pdo->query('SELECT COUNT(*) FROM plugin_dflow_hosts')->fetchColumn();
echo "Total de hosts: $count\n";
$hosts = $pdo->query('SELECT ip_address FROM plugin_dflow_hosts LIMIT 5')->fetchAll(PDO::FETCH_COLUMN);
print_r($hosts);
