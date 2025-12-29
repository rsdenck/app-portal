<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

echo "Starting DFlow Baseline Calculation (Flow-First Intelligence)...\n";

// We aggregate data from the last 7 days to build the baseline
$days = 7;
$startTime = date('Y-m-d H:i:s', strtotime("-$days days"));

echo "Analyzing flows since $startTime...\n";

// Query to get hourly aggregates per VLAN
$sql = "SELECT 
            vlan, 
            HOUR(ts) as hour_of_day, 
            SUM(bytes) as total_bytes, 
            SUM(pkts) as total_packets,
            DATE(ts) as flow_date
        FROM plugin_dflow_flows 
        WHERE ts >= ? AND vlan > 0
        GROUP BY vlan, hour_of_day, flow_date";

$stmt = $pdo->prepare($sql);
$stmt->execute([$startTime]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) {
    die("No historical flow data found to calculate baseline.\n");
}

$baselineData = [];

foreach ($data as $row) {
    $vlan = (int)$row['vlan'];
    $hour = (int)$row['hour_of_day'];
    
    if (!isset($baselineData[$vlan][$hour])) {
        $baselineData[$vlan][$hour] = ['bytes' => [], 'packets' => []];
    }
    
    $baselineData[$vlan][$hour]['bytes'][] = (int)$row['total_bytes'];
    $baselineData[$vlan][$hour]['packets'][] = (int)$row['total_packets'];
}

foreach ($baselineData as $vlan => $hours) {
    foreach ($hours as $hour => $stats) {
        $bytes = $stats['bytes'];
        $packets = $stats['packets'];
        
        $count = count($bytes);
        if ($count < 2) continue; // Need at least 2 samples for stddev

        $avgBytes = array_sum($bytes) / $count;
        $avgPackets = array_sum($packets) / $count;
        
        $sqDiffBytes = 0;
        $sqDiffPackets = 0;
        foreach ($bytes as $b) $sqDiffBytes += pow($b - $avgBytes, 2);
        foreach ($packets as $p) $sqDiffPackets += pow($p - $avgPackets, 2);
        
        $stdDevBytes = sqrt($sqDiffBytes / ($count - 1));
        $stdDevPackets = sqrt($sqDiffPackets / ($count - 1));

        $stmtUpsert = $pdo->prepare("INSERT INTO plugin_dflow_baselines 
            (vlan_id, hour_of_day, avg_bytes, stddev_bytes, avg_packets, stddev_packets, sample_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            avg_bytes = VALUES(avg_bytes), 
            stddev_bytes = VALUES(stddev_bytes), 
            avg_packets = VALUES(avg_packets), 
            stddev_packets = VALUES(stddev_packets), 
            sample_count = VALUES(sample_count)");
            
        $stmtUpsert->execute([
            $vlan, $hour, (int)$avgBytes, (int)$stdDevBytes, (int)$avgPackets, (int)$stdDevPackets, $count
        ]);
    }
}

echo "Baseline calculation finished for " . count($baselineData) . " VLANs.\n";
