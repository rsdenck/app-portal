<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

echo "Starting DFlow Sensor Health & Performance Monitor...\n";

// Sensor Configuration
$sensorName = getenv('DFLOW_SENSOR_NAME') ?: gethostname();
$sensorIp = getenv('DFLOW_SENSOR_IP') ?: '127.0.0.1';

// Get or create sensor ID
$stmtId = $pdo->prepare("SELECT id FROM plugin_dflow_sensors WHERE name = ?");
$stmtId->execute([$sensorName]);
$sensor = $stmtId->fetch();
if (!$sensor) {
    $pdo->prepare("INSERT INTO plugin_dflow_sensors (name, ip_address, status) VALUES (?, ?, 'online')")
        ->execute([$sensorName, $sensorIp]);
    $sensorId = (int)$pdo->lastInsertId();
} else {
    $sensorId = (int)$sensor['id'];
}

function getSystemMetrics() {
    $cpu = 0;
    $memPercent = 0;

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows - use wmic or powershell
        $cpuLoad = shell_exec('wmic cpu get loadpercentage /value');
        if (preg_match('/LoadPercentage=(\d+)/', $cpuLoad, $matches)) {
            $cpu = (float)$matches[1];
        }

        $memInfo = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value');
        if (preg_match('/FreePhysicalMemory=(\d+)/', $memInfo, $free) && preg_match('/TotalVisibleMemorySize=(\d+)/', $memInfo, $total)) {
            $freeMem = (int)$free[1];
            $totalMem = (int)$total[1];
            if ($totalMem > 0) {
                $memPercent = (($totalMem - $freeMem) / $totalMem) * 100;
            }
        }
    } else {
        // Linux/Unix
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpu = min(100, $load[0] * 10); // Simplistic normalization
        }
        
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total) && preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available)) {
                $totalMem = (int)$total[1];
                $availMem = (int)$available[1];
                if ($totalMem > 0) {
                    $memPercent = (($totalMem - $availMem) / $totalMem) * 100;
                }
            }
        }
    }
    
    return [
        'cpu' => $cpu,
        'mem' => $memPercent
    ];
}

while (true) {
    try {
        $metrics = getSystemMetrics();
        
        // Fetch real-time traffic stats from plugin_dflow_system_metrics (populated by dflow_ingestor)
        $stmtStats = $pdo->prepare("SELECT SUM(processed_packets) as pps, SUM(processed_bytes) as bps, SUM(dropped_packets) as drops, SUM(total_flows) as flows 
                                   FROM plugin_dflow_system_metrics 
                                   WHERE sensor_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmtStats->execute([$sensorId]);
        $traffic = $stmtStats->fetch(PDO::FETCH_ASSOC);

        // Update or Insert Sensor Status
        $stmtSensor = $pdo->prepare("INSERT INTO plugin_dflow_sensors 
            (name, ip_address, status, cpu_usage, mem_usage, pps, bps, packet_drops, active_flows, last_heartbeat, version) 
            VALUES (?, ?, 'online', ?, ?, ?, ?, ?, ?, NOW(), '1.0.0-enterprise') 
            ON DUPLICATE KEY UPDATE 
            status = 'online', 
            cpu_usage = VALUES(cpu_usage), 
            mem_usage = VALUES(mem_usage), 
            pps = VALUES(pps), 
            bps = VALUES(bps), 
            packet_drops = VALUES(packet_drops), 
            active_flows = VALUES(active_flows), 
            last_heartbeat = NOW()");
        
        $stmtSensor->execute([
            $sensorName,
            $sensorIp,
            (float)$metrics['cpu'],
            (float)$metrics['mem'],
            (int)($traffic['pps'] ?? 0),
            (int)($traffic['bps'] ?? 0),
            (int)($traffic['drops'] ?? 0),
            (int)($traffic['flows'] ?? 0)
        ]);

        echo "[" . date('Y-m-d H:i:s') . "] Heartbeat sent for sensor: $sensorName (PPS: " . ($traffic['pps'] ?? 0) . ")\n";

    } catch (Exception $e) {
        echo "Error in Sensor Daemon: " . $e->getMessage() . "\n";
    }

    sleep(30); // Heartbeat every 30 seconds
}
