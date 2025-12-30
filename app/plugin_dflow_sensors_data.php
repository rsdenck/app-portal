<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

header('Content-Type: application/json');

try {
    // Get all sensors
    $stmt = $pdo->query("SELECT * FROM plugin_dflow_sensors ORDER BY name ASC");
    $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    if (empty($sensors)) {
        // If no sensors exist, we provide a "Virtual C Engine" template to show the vision
        $data[] = [
            'id' => 0,
            'name' => 'DFlow Probe C-Engine (Template)',
            'ip_address' => '127.0.0.1',
            'version' => '2.1.0-enterprise-c',
            'status' => 'waiting',
            'health_color' => '#888',
            'cpu_usage' => 0,
            'mem_usage' => 0,
            'pps' => 0,
            'bps' => 0,
            'active_flows' => 0,
            'last_heartbeat' => date('Y-m-d H:i:s'),
            'trends' => ['pps' => [], 'bps' => []]
        ];
    } else {
        foreach ($sensors as $sensor) {
            // Get recent metrics for each sensor (last 10 minutes)
            $stmtMetrics = $pdo->prepare("SELECT * FROM plugin_dflow_system_metrics 
                                         WHERE sensor_id = ? 
                                         AND timestamp > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                                         ORDER BY timestamp ASC");
            $stmtMetrics->execute([$sensor['id']]);
            $metrics = $stmtMetrics->fetchAll(PDO::FETCH_ASSOC);

            // Calculate trends
            $ppsTrend = [];
            $bpsTrend = [];
            foreach ($metrics as $m) {
                $ppsTrend[] = [
                    't' => strtotime($m['timestamp']) * 1000,
                    'v' => $m['processed_packets']
                ];
                $bpsTrend[] = [
                    't' => strtotime($m['timestamp']) * 1000,
                    'v' => $m['processed_bytes']
                ];
            }

            $sensor['trends'] = [
                'pps' => $ppsTrend,
                'bps' => $bpsTrend
            ];
            
            // Determine health color
            $lastSeen = strtotime($sensor['last_heartbeat']);
            $diff = time() - $lastSeen;
            
            if ($diff > 300) {
                $sensor['status'] = 'offline';
                $sensor['health_color'] = '#ff4d4d'; // Red
            } elseif ($sensor['cpu_usage'] > 80 || $sensor['mem_usage'] > 90) {
                $sensor['status'] = 'error';
                $sensor['health_color'] = '#ffa500'; // Orange
            } else {
                $sensor['status'] = 'online';
                $sensor['health_color'] = '#27c4a8'; // Green
            }

            $data[] = $sensor;
        }
    }

    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
