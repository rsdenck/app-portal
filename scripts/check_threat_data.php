<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$stmt = $pdo->prepare("SELECT updated_at, LENGTH(data) as size FROM plugin_bgp_data WHERE type = 'threat_intel'");
$stmt->execute();
$row = $stmt->fetch();

if ($row) {
    echo "Last Update: " . $row['updated_at'] . "\n";
    echo "Data Size: " . $row['size'] . " bytes\n";
    
    $dataRaw = $row['data'] ?? '{}';
    $data = json_decode($dataRaw, true);
    echo "Stats:\n";
    print_r($data['stats'] ?? []);
    
    if (isset($data['tor_nodes'])) {
        echo "Tor Nodes count: " . count($data['tor_nodes']) . "\n";
        echo "Sample Tor Node: " . (count($data['tor_nodes']) > 0 ? array_keys($data['tor_nodes'])[0] : "None") . "\n";
    } else {
        echo "tor_nodes key missing in data!\n";
    }
} else {
    echo "No threat_intel data found in plugin_bgp_data table.\n";
}
