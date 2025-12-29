<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */

$plugin = plugin_get_by_name($pdo, 'bgpview');
if ($plugin) {
    echo "Plugin: " . $plugin['name'] . "\n";
    echo "Active: " . ($plugin['is_active'] ? 'Yes' : 'No') . "\n";
    echo "Config: " . json_encode($plugin['config'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Plugin bgpview not found.\n";
}
