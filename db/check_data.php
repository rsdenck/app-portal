<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
$stmt = $pdo->query("SELECT * FROM plugin_bgp_data");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Type: " . $row['type'] . "\n";
    if ($row['type'] === 'threat_intel') {
        $data = json_decode($row['data'], true);
        echo "Attacks: " . count($data['attacks']) . "\n";
        if (!empty($data['attacks'])) {
            print_r(array_slice($data['attacks'], 0, 2));
        }
    }
}
