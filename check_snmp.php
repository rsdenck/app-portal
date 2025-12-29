<?php
require __DIR__ . '/includes/bootstrap.php';
/** @var PDO $pdo */
$p = plugin_get_by_name($pdo, 'snmp');
print_r($p);

if ($p && $p['is_active']) {
    echo "SNMP is active.\n";
} else {
    echo "SNMP is NOT active.\n";
}
