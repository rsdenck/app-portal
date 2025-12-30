<?php
require 'includes/bootstrap.php';
$tables = ['plugin_dflow_hosts', 'plugin_dflow_interfaces', 'plugin_dflow_vlans', 'plugin_dflow_flows'];
foreach ($tables as $t) {
    echo "--- Table: $t ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
