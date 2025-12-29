<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
// $pdo is already available from bootstrap.php

$stmt = $pdo->prepare("SELECT data FROM plugin_bgp_data WHERE type = 'threat_intel'");
$stmt->execute();
$row = $stmt->fetch();
if (!$row) {
    echo "No threat_intel data found.\n";
    exit;
}

$data = json_decode($row['data'], true);
echo "Attacks found: " . count($data['attacks'] ?? []) . "\n";
foreach (array_slice($data['attacks'] ?? [], 0, 5) as $attack) {
    echo "- Attacker: {$attack['attacker']} -> Target: {$attack['target']} | SEC: " . ($attack['is_sec_logs']?'Y':'N') . " | Corgea: " . ($attack['is_corgea']?'Y':'N') . " | CVEs: " . implode(',', $attack['cves'] ?? []) . "\n";
}

echo "\nMalicious IPs sample:\n";
foreach (array_slice($data['malicious_ips'] ?? [], 0, 3) as $ip => $info) {
    echo "- IP: $ip | Source: {$info['source']} | Corgea: " . (isset($info['corgea'])?'Y':'N') . "\n";
}
