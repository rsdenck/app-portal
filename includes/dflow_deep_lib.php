<?php
declare(strict_types=1);

/**
 * Performs deep analysis for a specific IP using the native DFlow engine data.
 */
function deep_analyze_ip(PDO $pdo, string $ip): array {
    // In native mode, we don't "trigger" a capture, we fetch what's already been collected
    // by the background engine and possibly enriched.
    
    // 1. Fetch current L7 data from flows
    $stmt = $pdo->prepare("SELECT app_proto, src_ip, dst_ip, tcp_flags, rtt_ms, last_seen 
                          FROM plugin_dflow_flows 
                          WHERE (src_ip = ? OR dst_ip = ?) 
                          AND app_proto != 'Unknown'
                          ORDER BY last_seen DESC LIMIT 50");
    $stmt->execute([$ip, $ip]);
    $flows = $stmt->fetchAll();

    $count = 0;
    if ($flows) {
        $insert = $pdo->prepare("INSERT INTO plugin_dflow_deep_analysis 
            (ip_address, protocol, analysis_type, detail_key, detail_value, severity, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($flows as $f) {
            $role = ($f['src_ip'] === $ip) ? 'Source' : 'Destination';
            
            // Log the application protocol discovery
            $insert->execute([
                $ip, 
                'L4/L7', 
                'DPI_DISCOVERY', 
                'App Identified', 
                "Detected {$f['app_proto']} communication as $role", 
                'info',
                $f['last_seen']
            ]);

            // If it's a TCP flow with performance data
            if ($f['rtt_ms'] > 0) {
                $insert->execute([
                    $ip, 
                    'TCP', 
                    'PERFORMANCE', 
                    'Latency', 
                    "Estimated RTT: " . round($f['rtt_ms'], 2) . "ms", 
                    $f['rtt_ms'] > 200 ? 'medium' : 'low',
                    $f['last_seen']
                ]);
            }

            $count++;
        }
    }

    return [
        'success' => true, 
        'count' => $count,
        'message' => 'Native DPI data retrieved and synchronized from DFlow Engine.'
    ];
}

