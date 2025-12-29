<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

header('Content-Type: application/json');

$vlanId = isset($_GET['vlan']) ? (int)$_GET['vlan'] : 0;

if ($vlanId <= 0) {
    echo json_encode(['error' => 'VLAN ID required']);
    exit;
}

// 1. Get Current Hour Traffic
$currentHour = (int)date('H');
$currentTraffic = $pdo->prepare("SELECT 
    SUM(bytes) as bytes, 
    SUM(packets) as packets 
    FROM plugin_dflow_flows 
    WHERE vlan = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$currentTraffic->execute([$vlanId]);
$current = $currentTraffic->fetch(PDO::FETCH_ASSOC);

// 2. Get Baseline for this Hour
$baselineStmt = $pdo->prepare("SELECT * FROM plugin_dflow_baselines WHERE vlan_id = ? AND hour_of_day = ?");
$baselineStmt->execute([$vlanId, $currentHour]);
$baseline = $baselineStmt->fetch(PDO::FETCH_ASSOC);

// 3. Get Top ASNs for this VLAN (Flow-First)
$topAsns = $pdo->prepare("SELECT 
    asn, 
    SUM(bytes) as traffic 
    FROM plugin_dflow_flows 
    WHERE vlan = ? 
    GROUP BY asn 
    ORDER BY traffic DESC 
    LIMIT 5");
$topAsns->execute([$vlanId]);
$asns = $topAsns->fetchAll(PDO::FETCH_ASSOC);

$status = 'Normal';
$anomalies = [];

if ($baseline && $current) {
    $thresholdBytes = $baseline['avg_bytes'] + (2 * $baseline['stddev_bytes']);
    $thresholdPackets = $baseline['avg_packets'] + (2 * $baseline['stddev_packets']);
    
    if ($current['bytes'] > $thresholdBytes && $thresholdBytes > 0) {
        $status = 'Anormal';
        $anomalies[] = 'Volume de bytes acima do baseline (> 2 stddev)';
    }
    
    if ($current['packets'] > $thresholdPackets && $thresholdPackets > 0) {
        $status = 'Anormal';
        $anomalies[] = 'Volume de pacotes acima do baseline (> 2 stddev)';
    }
}

echo json_encode([
    'vlan' => $vlanId,
    'status' => $status,
    'current' => [
        'bytes' => (int)($current['bytes'] ?? 0),
        'packets' => (int)($current['packets'] ?? 0)
    ],
    'baseline' => $baseline ? [
        'avg_bytes' => (int)$baseline['avg_bytes'],
        'avg_packets' => (int)$baseline['avg_packets'],
        'stddev_bytes' => (int)$baseline['stddev_bytes'],
        'stddev_packets' => (int)$baseline['stddev_packets']
    ] : null,
    'top_asns' => $asns,
    'anomalies' => $anomalies
]);
