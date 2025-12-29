<?php
require_once __DIR__ . '/includes/snmp_api.php';

echo "MIBDIRS: " . getenv('MIBDIRS') . "\n";
if (function_exists('snmp_get_quick_print')) {
    echo "SNMP extension is loaded.\n";
} else {
    echo "SNMP extension is NOT loaded.\n";
}
