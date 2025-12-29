<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$vlanData = $pdo->query("SELECT vlan, 
                                COUNT(DISTINCT src_ip) as unique_hosts, 
                                SUM(bytes) as total_bytes, 
                                SUM(pkts) as total_packets,
                                COUNT(*) as total_flows,
                                AVG(rtt_ms) as avg_rtt
                         FROM plugin_dflow_flows 
                         WHERE vlan > 0
                         GROUP BY vlan
                         ORDER BY total_bytes DESC")->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($vlanData as $v) {
    // Get top apps for this VLAN
    $stmtApps = $pdo->prepare("SELECT COALESCE(app_proto, 'Unknown') as app_proto, SUM(bytes) as app_bytes 
                                FROM plugin_dflow_flows 
                                WHERE vlan = ? 
                                GROUP BY app_proto 
                                ORDER BY app_bytes DESC LIMIT 5");
    $stmtApps->execute([$v['vlan']]);
    $v['top_apps'] = $stmtApps->fetchAll(PDO::FETCH_ASSOC);
    
    // Get L7 metrics per VLAN
    $stmtL7 = $pdo->prepare("SELECT l7_proto, COUNT(*) as sessions, SUM(bytes) as traffic 
                              FROM plugin_dflow_flows 
                              WHERE vlan = ? AND l7_proto IS NOT NULL AND l7_proto != ''
                              GROUP BY l7_proto 
                              ORDER BY traffic DESC LIMIT 5");
    $stmtL7->execute([$v['vlan']]);
    $v['l7_metrics'] = $stmtL7->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = $v;
}

header('Content-Type: application/json');
echo json_encode($results);
