<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

echo "Creating Baseline table for DFlow Network Intelligence...\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS plugin_dflow_baselines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vlan_id INT NOT NULL,
        hour_of_day TINYINT NOT NULL,
        avg_bytes BIGINT UNSIGNED DEFAULT 0,
        stddev_bytes BIGINT UNSIGNED DEFAULT 0,
        avg_packets BIGINT UNSIGNED DEFAULT 0,
        stddev_packets BIGINT UNSIGNED DEFAULT 0,
        sample_count INT UNSIGNED DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY vlan_hour (vlan_id, hour_of_day)
    ) ENGINE=InnoDB;");
    echo "- Table plugin_dflow_baselines created/verified.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
