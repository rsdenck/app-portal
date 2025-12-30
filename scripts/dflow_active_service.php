<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

// Disable time limit for the service
set_time_limit(0);
ignore_user_abort(true);

echo "Starting DFLOW Active Monitoring Service (30s interval)...\n";

while (true) {
    $startTime = time();
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Cycle starting...\n";

    // 1. Run SNMP Discovery (Interfaces, MACs, VLANs)
    echo "  Running SNMP Discovery...\n";
    include __DIR__ . '/dflow_snmp_collector.php';

    // 2. Run Batch IP Scanning for active blocks
    echo "  Running Batch IP Scan...\n";
    // We modify the scanner to be more efficient in a service loop
    include __DIR__ . '/dflow_batch_scanner.php';

    // 3. Correlation: Match IP <-> MAC via ARP/Hosts
    echo "  Correlating IP/MAC/VLAN data...\n";
    // This logic ensures interfaces table is up to date with IPs found in scanning
    $pdo->exec("
        UPDATE plugin_dflow_interfaces i
        JOIN plugin_dflow_hosts h ON i.mac_address = h.mac_address
        SET i.ip_address = h.ip_address
        WHERE i.ip_address IS NULL OR i.ip_address != h.ip_address
    ");

    $endTime = time();
    $duration = $endTime - $startTime;
    $sleep = max(30 - $duration, 5); // Ensure at least 5s sleep even if tasks took > 30s

    echo "[$timestamp] Cycle finished in {$duration}s. Sleeping for {$sleep}s...\n";
    sleep($sleep);
}
