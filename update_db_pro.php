<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

echo "Updating database schema for DFlow Production...\n";

try {
    // 1. New table for L2 Topology (Neighbors)
    $pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dflow_topology (
        id INT AUTO_INCREMENT PRIMARY KEY,
        local_device_ip VARCHAR(45) NOT NULL,
        local_port_index INT NOT NULL,
        remote_device_name VARCHAR(255),
        remote_port_id VARCHAR(100),
        remote_chassis_id VARCHAR(100),
        remote_system_desc TEXT,
        protocol ENUM('LLDP', 'CDP') NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY neighbor (local_device_ip, local_port_index, remote_chassis_id, remote_port_id)
    ) ENGINE=InnoDB;");
    echo "- Table plugin_dflow_topology created/verified.\n";

    // 2. New table for VLAN Inventory
    $pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dflow_vlans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        vlan_id INT NOT NULL,
        vlan_name VARCHAR(100),
        vlan_status VARCHAR(20),
        vlan_type VARCHAR(50),
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY device_vlan (device_ip, vlan_id)
    ) ENGINE=InnoDB;");
    echo "- Table plugin_dflow_vlans created/verified.\n";

    // 2b. New table for SNMP Devices Inventory
    $pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dflow_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        hostname VARCHAR(255),
        description TEXT,
        vendor VARCHAR(100),
        model VARCHAR(100),
        os_version VARCHAR(100),
        uptime BIGINT UNSIGNED,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY device_ip (ip_address)
    ) ENGINE=InnoDB;");
    echo "- Table plugin_dflow_devices created/verified.\n";

    // 3. Add new columns to plugin_dflow_flows for enhanced telemetry
    $columnsToAdd = [
        'tcp_flags' => 'TINYINT UNSIGNED DEFAULT 0',
        'rtt_ms' => 'FLOAT DEFAULT NULL',
        'sni' => 'VARCHAR(255) DEFAULT NULL',
        'ja3' => 'CHAR(32) DEFAULT NULL',
        'eth_type' => 'VARCHAR(10) DEFAULT NULL',
        'pcp' => 'TINYINT UNSIGNED DEFAULT 0'
    ];

    foreach ($columnsToAdd as $col => $type) {
        $stmt = $pdo->query("SHOW COLUMNS FROM plugin_dflow_flows LIKE '$col'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE plugin_dflow_flows ADD COLUMN $col $type");
            echo "- Added column $col to plugin_dflow_flows.\n";
        }
    }

    // 4. Add device_ip to interfaces to track which device owns which interface
    $stmt = $pdo->query("SHOW COLUMNS FROM plugin_dflow_interfaces LIKE 'device_ip'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE plugin_dflow_interfaces ADD COLUMN device_ip VARCHAR(45) AFTER id");
        echo "- Added column device_ip to plugin_dflow_interfaces.\n";
    }

    // Fix UNIQUE KEY for interfaces (should be per device)
    try {
        $pdo->exec("ALTER TABLE plugin_dflow_interfaces DROP INDEX if_index");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE plugin_dflow_interfaces ADD UNIQUE KEY dev_if (device_ip, if_index)");
        echo "- Updated UNIQUE KEY for plugin_dflow_interfaces.\n";
    } catch (Exception $e) {}

    echo "Schema update completed successfully.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
