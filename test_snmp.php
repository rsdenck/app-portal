<?php
require_once __DIR__ . '/includes/bootstrap.php';

echo "Testing SNMP MIB resolution...\n";
echo "MIBDIRS: " . getenv('MIBDIRS') . "\n";

// We'll try to translate a Cisco-specific OID if possible
// OID for vtpVlanName: .1.3.6.1.4.1.9.9.46.1.3.1.1.2
$oid = ".1.3.6.1.4.1.9.9.46.1.3.1.1.2";
$translated = @snmp_translate($oid);

if ($translated) {
    echo "Translation successful: $oid -> $translated\n";
} else {
    echo "Translation failed for $oid. Check MIBDIRS and MIB files.\n";
}

// Try a standard one
$stdOid = ".1.3.6.1.2.1.1.1.0";
$stdTranslated = @snmp_translate($stdOid);
echo "Standard OID translation: $stdOid -> $stdTranslated\n";
