<?php
require 'includes/bootstrap.php';
/** @var PDO $pdo */

$sqls = [
    // Ensure plugin_dflow_vlans has device_ip
    "CREATE TABLE IF NOT EXISTS plugin_dflow_vlans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        vlan_id INT NOT NULL,
        vlan_name VARCHAR(100),
        vlan_status VARCHAR(20) DEFAULT 'active',
        vlan_type VARCHAR(50) DEFAULT 'static',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_dev_vlan (device_ip, vlan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Check and add columns manually to be safe
];

foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed: " . substr($sql, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Manually check columns for interfaces
try {
    $cols = $pdo->query("SHOW COLUMNS FROM plugin_dflow_interfaces")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('device_ip', $cols)) {
        $pdo->exec("ALTER TABLE plugin_dflow_interfaces ADD COLUMN device_ip VARCHAR(45) AFTER id");
        echo "Added device_ip to plugin_dflow_interfaces\n";
    }
} catch (Exception $e) { echo "Interfaces check error: " . $e->getMessage() . "\n"; }

// Manually check columns for hosts
try {
    $cols = $pdo->query("SHOW COLUMNS FROM plugin_dflow_hosts")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('vlan', $cols)) {
        $pdo->exec("ALTER TABLE plugin_dflow_hosts ADD COLUMN vlan INT AFTER hostname");
        echo "Added vlan to plugin_dflow_hosts\n";
    }
    if (!in_array('mac_address', $cols)) {
        $pdo->exec("ALTER TABLE plugin_dflow_hosts ADD COLUMN mac_address VARCHAR(17) AFTER ip_address");
        echo "Added mac_address to plugin_dflow_hosts\n";
    }
} catch (Exception $e) { echo "Hosts check error: " . $e->getMessage() . "\n"; }

echo "Schema fix completed.\n";
