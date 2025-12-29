<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

// Populate hosts from existing flows
$flows = $pdo->query("SELECT * FROM plugin_dflow_flows")->fetchAll(PDO::FETCH_ASSOC);

$stmtHost = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, total_bytes, last_seen) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    total_bytes = total_bytes + VALUES(total_bytes), 
    last_seen = VALUES(last_seen)");

$count = 0;
foreach ($flows as $f) {
    $ts = $f['ts'] ?? $f['start_time'] ?? date('Y-m-d H:i:s');
    $stmtHost->execute([$f['src_ip'], $f['bytes'], $ts]);
    $stmtHost->execute([$f['dst_ip'], $f['bytes'], $ts]);
    $count += 2;
}

echo "Populated $count host entries (including duplicates handled by ON DUPLICATE KEY).\n";

$finalCount = $pdo->query("SELECT COUNT(*) FROM plugin_dflow_hosts")->fetchColumn();
echo "Total unique hosts now: $finalCount\n";
