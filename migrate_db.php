<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$migrations = [
    "ALTER TABLE plugin_dflow_interfaces ADD COLUMN mac_address VARCHAR(17) AFTER description",
    "ALTER TABLE plugin_dflow_interfaces ADD COLUMN vlan INT DEFAULT 0 AFTER mac_address"
];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed: $sql\n";
    } catch (Exception $e) {
        echo "Failed: $sql - " . $e->getMessage() . "\n";
    }
}
