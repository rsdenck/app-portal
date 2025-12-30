<?php
require_once __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

function describe($pdo, $table) {
    echo "--- $table ---\n";
    try {
        $q = $pdo->query("DESCRIBE $table");
        while($r = $q->fetch(PDO::FETCH_ASSOC)) {
            echo "{$r['Field']} ({$r['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

describe($pdo, 'plugin_dflow_hosts');
describe($pdo, 'plugin_dflow_interfaces');
describe($pdo, 'plugin_dflow_vlans');
describe($pdo, 'plugin_dflow_devices');
describe($pdo, 'plugin_dflow_flows');

echo "\n--- Counts ---\n";
foreach(['plugin_dflow_hosts', 'plugin_dflow_interfaces', 'plugin_dflow_vlans', 'plugin_dflow_devices', 'plugin_dflow_flows'] as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "$t: $count\n";
}

echo "\n--- Hosts Inventory ---\n";
$q = $pdo->query("SELECT ip_address, mac_address, vlan, last_seen FROM plugin_dflow_hosts ORDER BY last_seen DESC LIMIT 20");
while($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['ip_address']} | {$r['mac_address']} | VLAN {$r['vlan']} | {$r['last_seen']}\n";
}

echo "\n--- Interfaces Sample ---\n";
$q = $pdo->query("SELECT device_ip, if_index, name, vlan FROM plugin_dflow_interfaces LIMIT 10");
while($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['device_ip']} | {$r['if_index']} | {$r['name']} | VLAN {$r['vlan']}\n";
}
