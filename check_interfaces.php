<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
try {
    echo "Interfaces: " . $pdo->query("SELECT COUNT(*) FROM plugin_dflow_interfaces")->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
