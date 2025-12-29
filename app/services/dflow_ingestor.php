<?php
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
/** @var PDO $pdo */

// Use absolute paths and allow environment override
$baseDir = dirname(__DIR__, 2);
$logDir = getenv('DFLOW_LOG_DIR') ?: $baseDir . '/src/dflow-engine';

function log_ingestor($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

function processFlows(PDO $pdo, string $logFile): int {
    if (!file_exists($logFile)) {
        // log_ingestor("Flow log not found: $logFile");
        return 0;
    }
    
    $handle = fopen($logFile, 'r+');
    if (!$handle) {
        log_ingestor("Could not open flow log: $logFile");
        return 0;
    }
    
    if (!flock($handle, LOCK_EX)) {
        log_ingestor("Could not lock flow log: $logFile");
        fclose($handle);
        return 0;
    }

    log_ingestor("Processing flows from $logFile...");
    $pdo->beginTransaction();
    
    // Check if file is empty before processing
    $fileSize = filesize($logFile);
    log_ingestor("File size: $fileSize bytes");
    $stmt = $pdo->prepare("INSERT INTO plugin_dflow_flows 
        (src_ip, src_port, dst_ip, dst_port, proto, app_proto, bytes, pkts, vlan, ts, tcp_flags, rtt_ms, eth_type, pcp) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?)");

    $stmtHost = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, mac_address, vlan, total_bytes, last_seen) 
        VALUES (?, ?, ?, ?, FROM_UNIXTIME(?)) 
        ON DUPLICATE KEY UPDATE 
        mac_address = IF(VALUES(mac_address) != '00:00:00:00:00:00', VALUES(mac_address), mac_address),
        vlan = IF(VALUES(vlan) > 0, VALUES(vlan), vlan),
        total_bytes = total_bytes + VALUES(total_bytes), 
        last_seen = VALUES(last_seen)");

    $count = 0;
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;
        $data = explode('|', $line);
        if (count($data) < 16) {
            log_ingestor("Skipping line (count " . count($data) . "): $line");
            continue;
        }

        $ts = (int)$data[0];
        if ($ts < 1000000) {
            log_ingestor("Invalid timestamp ($ts) in line: $line");
            continue;
        }
        $srcIp = $data[1];
        $dstIp = $data[2];
        $srcPort = (int)$data[3];
        $dstPort = (int)$data[4];
        $protoNum = (int)$data[5];
        $bytes = (int)$data[6];
        $packets = (int)$data[7];
        $l7 = $data[8];
        $sni = $data[9];
        $ja3 = $data[10];
        $anomaly = $data[11];
        $cve = $data[12];
        $srcMac = $data[13];
        $dstMac = $data[14];
        $vlan = (int)$data[15];
        $tcpFlags = isset($data[16]) ? (int)$data[16] : 0;
        $rtt = isset($data[17]) ? (float)$data[17] : null;
        $ethType = isset($data[18]) ? $data[18] : null;
        $pcp = isset($data[19]) ? (int)$data[19] : 0;

        $proto = $protoNum == 6 ? 'TCP' : ($protoNum == 17 ? 'UDP' : (string)$protoNum);
        
        $stmt->execute([
            $srcIp, $srcPort, $dstIp, $dstPort, $proto, $l7, $bytes, $packets, $vlan, $ts,
            $tcpFlags, $rtt, $ethType, $pcp
        ]);

        // Update Host stats for Source
        $stmtHost->execute([$srcIp, $srcMac, $vlan, $bytes, $ts]);
        // Update Host stats for Destination
        $stmtHost->execute([$dstIp, $dstMac, $vlan, $bytes, $ts]);

        $count++;
        if ($count % 500 === 0) { // Batching
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }
    $pdo->commit();
    ftruncate($handle, 0);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $count;
}

function processMetrics(PDO $pdo, string $logFile): int {
    if (!file_exists($logFile)) return 0;
    $handle = fopen($logFile, 'r+');
    if (!$handle || !flock($handle, LOCK_EX)) return 0;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO plugin_dflow_system_metrics 
        (timestamp, thread_id, processed_packets, processed_bytes, dropped_packets, active_sessions, total_flows, hash_collisions) 
        VALUES (FROM_UNIXTIME(?), ?, ?, ?, ?, ?, ?, ?)");

    $count = 0;
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;
        $data = explode('|', $line);
        if (count($data) < 8) continue;

        $stmt->execute([
            (int)$data[0], (int)$data[1], (int)$data[2], (int)$data[3],
            (int)$data[4], (int)$data[5], (int)$data[6], (int)$data[7]
        ]);
        $count++;
    }
    $pdo->commit();
    ftruncate($handle, 0);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $count;
}

// Process all thread-specific flow logs
$flowLogs = glob($logDir . '/dflow_pending_flows_t*.log');
// Also check the legacy log if it exists
if (file_exists($logDir . '/dflow_pending_flows.log')) {
    $flowLogs[] = $logDir . '/dflow_pending_flows.log';
}

$totalFlows = 0;
foreach ($flowLogs as $file) {
    $totalFlows += processFlows($pdo, $file);
}

// Process all thread-specific metrics logs
$metricsLogs = glob($logDir . '/dflow_metrics_t*.log');
if (file_exists($logDir . '/dflow_metrics.log')) {
    $metricsLogs[] = $logDir . '/dflow_metrics.log';
}

$totalMetrics = 0;
foreach ($metricsLogs as $file) {
    $totalMetrics += processMetrics($pdo, $file);
}

echo "Processed $totalFlows flows and $totalMetrics metric entries across " . count($flowLogs) . " threads.\n";
