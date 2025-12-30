<?php
require_once __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

try {
    // Add vlan_id to ip_blocks
    $pdo->exec("ALTER TABLE plugin_dflow_ip_blocks ADD COLUMN vlan_id INT DEFAULT NULL");
    echo "Added vlan_id to plugin_dflow_ip_blocks\n";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}

try {
    // Ensure hosts table has necessary columns for the inventory
    $columns = [
        'mac_address' => "VARCHAR(17) DEFAULT NULL",
        'vendor' => "VARCHAR(100) DEFAULT NULL",
        'hostname' => "VARCHAR(255) DEFAULT NULL",
        'throughput_in' => "BIGINT DEFAULT 0",
        'throughput_out' => "BIGINT DEFAULT 0",
        'vlan' => "INT DEFAULT NULL"
    ];

    foreach ($columns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE plugin_dflow_hosts ADD COLUMN $col $def");
            echo "Added column $col to plugin_dflow_hosts\n";
        } catch (Exception $e) {
            // Probably already exists
        }
    }
    echo "Enriched plugin_dflow_hosts table check completed.\n";

    // Assign VLANs to IP blocks for testing
    $blocks = [
        '186.250.184.0/22' => 10,
        '143.0.120.0/22' => 20,
        '132.255.220.0/22' => 30
    ];

    foreach ($blocks as $cidr => $vlan) {
        $stmt = $pdo->prepare("UPDATE plugin_dflow_ip_blocks SET vlan_id = ? WHERE cidr = ?");
        $stmt->execute([$vlan, $cidr]);
        echo "Assigned VLAN $vlan to block $cidr\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
