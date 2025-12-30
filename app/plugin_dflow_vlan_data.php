<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

if (php_sapi_name() !== 'cli') {
    $user = require_login('atendente');
}

// 1. Fetch all VLANs from inventory. If plugin_dflow_vlans is empty, try to get from interfaces
$vlanData = $pdo->query("SELECT vlan_id as vlan, vlan_name, device_ip FROM plugin_dflow_vlans")->fetchAll(PDO::FETCH_ASSOC);

if (empty($vlanData)) {
    // Fallback to interfaces table
    $vlanData = $pdo->query("SELECT DISTINCT vlan as vlan, CONCAT('VLAN ', vlan) as vlan_name, device_ip 
                             FROM plugin_dflow_interfaces 
                             WHERE vlan > 0 
                             ORDER BY vlan ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Add VLANs from IP blocks if not present
$blockVlans = $pdo->query("SELECT DISTINCT vlan_id as vlan FROM plugin_dflow_ip_blocks WHERE vlan_id > 0")->fetchAll(PDO::FETCH_COLUMN);
$existingVlans = array_column($vlanData, 'vlan');

foreach ($blockVlans as $bv) {
    if (!in_array($bv, $existingVlans)) {
        $vlanData[] = [
            'vlan' => $bv,
            'vlan_name' => 'VLAN ' . $bv,
            'device_ip' => 'Scan Block'
        ];
    }
}

$results = [];
foreach ($vlanData as $v) {
    $vlanId = (int)$v['vlan'];
    
    // Enrich with metrics
    $stmtMetrics = $pdo->prepare("SELECT 
                                    COUNT(DISTINCT ip_address) as unique_hosts,
                                    (SELECT SUM(bytes) FROM plugin_dflow_flows f WHERE f.vlan = ?) as total_bytes,
                                    (SELECT SUM(pkts) FROM plugin_dflow_flows f WHERE f.vlan = ?) as total_packets,
                                    (SELECT COUNT(*) FROM plugin_dflow_flows f WHERE f.vlan = ?) as total_flows,
                                    (SELECT AVG(rtt_ms) FROM plugin_dflow_flows f WHERE f.vlan = ?) as avg_rtt
                                 FROM plugin_dflow_hosts 
                                 WHERE vlan = ?");
    $stmtMetrics->execute([$vlanId, $vlanId, $vlanId, $vlanId, $vlanId]);
    $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC);

    $v['vlan_name'] = $v['vlan_name'] ?: 'VLAN ' . $vlanId;
    $v['total_bytes'] = (int)($metrics['total_bytes'] ?? 0);
    $v['total_packets'] = (int)($metrics['total_packets'] ?? 0);
    $v['total_flows'] = (int)($metrics['total_flows'] ?? 0);
    $v['unique_hosts'] = (int)($metrics['unique_hosts'] ?? 0);
    $v['avg_rtt'] = (float)($metrics['avg_rtt'] ?? 0);

    // Get top apps for this VLAN (DPI)
    $stmtApps = $pdo->prepare("SELECT COALESCE(app_proto, 'Unknown') as l7_proto, SUM(bytes) as traffic 
                                FROM plugin_dflow_flows 
                                WHERE vlan = ? 
                                GROUP BY app_proto 
                                ORDER BY traffic DESC LIMIT 5");
    $stmtApps->execute([$v['vlan']]);
    $v['l7_metrics'] = $stmtApps->fetchAll(PDO::FETCH_ASSOC);
    
    // Get protocol metrics per VLAN (L4)
    $stmtProto = $pdo->prepare("SELECT proto, COUNT(*) as sessions, SUM(bytes) as traffic 
                              FROM plugin_dflow_flows 
                              WHERE vlan = ?
                              GROUP BY proto 
                              ORDER BY traffic DESC LIMIT 5");
    $stmtProto->execute([$v['vlan']]);
    $v['protocol_metrics'] = $stmtProto->fetchAll(PDO::FETCH_ASSOC);
    
    // Get interfaces for this VLAN (Complete inventory)
    $stmtIf = $pdo->prepare("SELECT name, description, mac_address, status, speed, in_bytes, out_bytes, device_ip as ip_address 
                             FROM plugin_dflow_interfaces 
                             WHERE vlan = ?");
    $stmtIf->execute([$v['vlan']]);
    $v['interfaces'] = $stmtIf->fetchAll(PDO::FETCH_ASSOC);

    // Get hosts for this VLAN (Complete inventory)
    $stmtHosts = $pdo->prepare("SELECT ip_address, mac_address, hostname, vendor, throughput_in, throughput_out, last_seen 
                                FROM plugin_dflow_hosts 
                                WHERE vlan = ? 
                                ORDER BY last_seen DESC LIMIT 100");
    $stmtHosts->execute([$v['vlan']]);
    $v['hosts'] = $stmtHosts->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = $v;
}

header('Content-Type: application/json');
echo json_encode($results);
