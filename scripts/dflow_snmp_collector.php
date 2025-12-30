<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

echo "Starting DFlow SNMP Discovery & Correlation Collector...\n";

// 1. Load SNMP Plugin
$snmpPlugin = plugin_get_by_name($pdo, 'snmp');
if (!$snmpPlugin || !$snmpPlugin['is_active']) {
    die("SNMP Plugin not active or not configured.\n");
}

// 2. Fetch devices from both config and database
$devices = $snmpPlugin['config']['devices'] ?? [];

$dbDevices = $pdo->query("SELECT ip_address as ip, snmp_community as community, snmp_version as version FROM plugin_dflow_devices")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dbDevices as $dbDev) {
    $exists = false;
    foreach ($devices as $cfgDev) {
        if (($cfgDev['ip'] ?? '') === $dbDev['ip']) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $devices[] = $dbDev;
    }
}

if (empty($devices)) {
    die("No SNMP devices configured or found in database.\n");
}

$hasSnmp = extension_loaded('snmp');
if (!$hasSnmp) {
    die("CRITICAL: PHP SNMP extension not found.\n");
}

// Disable annoying warnings
@snmp_set_quick_print(true);
@snmp_set_enum_print(true);
if (function_exists('snmp_set_valueretrieval')) {
    @snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
}

foreach ($devices as $dev) {
    $ip = $dev['ip'] ?? '';
    if (!$ip) continue;

    echo "Processing Device: $ip...\n";
    $community = $dev['community'] ?? 'public';
    $version = $dev['version'] ?? '2c';

    try {
        // --- Vendor & Device Info ---
        $sysDescr = (string)@snmp2_get($ip, $community, ".1.3.6.1.2.1.1.1.0");
        $sysObjectID = (string)@snmp2_get($ip, $community, ".1.3.6.1.2.1.1.2.0");
        $sysName = (string)@snmp2_get($ip, $community, ".1.3.6.1.2.1.1.5.0");
        $uptimeRaw = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.3.0");

        $vendor = 'Generic';
        $model = 'Unknown Device';

        if ($sysObjectID) {
            if (strpos($sysObjectID, "1.3.6.1.4.1.9") !== false) $vendor = 'Cisco';
            elseif (strpos($sysObjectID, "1.3.6.1.4.1.2011") !== false) $vendor = 'Huawei';
            elseif (strpos($sysObjectID, "1.3.6.1.4.1.11") !== false) $vendor = 'HP';
            elseif (strpos($sysObjectID, "1.3.6.1.4.1.2636") !== false) $vendor = 'Juniper';
            elseif (strpos($sysObjectID, "1.3.6.1.4.1.14988") !== false) $vendor = 'MikroTik';
            elseif (strpos($sysObjectID, "1.3.6.1.4.1.8072") !== false) $vendor = 'Net-SNMP';
        }

        $uptime = 0;
        if ($uptimeRaw) {
            if (preg_match('/\((\d+)\)/', (string)$uptimeRaw, $m)) {
                $uptime = (int)$m[1];
            } elseif (is_numeric($uptimeRaw)) {
                $uptime = (int)$uptimeRaw;
            }
        }

        $stmtDev = $pdo->prepare("INSERT INTO plugin_dflow_devices 
            (ip_address, hostname, description, vendor, model, uptime) 
            VALUES (?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            hostname = VALUES(hostname), 
            description = VALUES(description), 
            vendor = VALUES(vendor),
            model = VALUES(model),
            uptime = VALUES(uptime),
            last_seen = NOW()");
        $stmtDev->execute([$ip, trim($sysName ?: $ip, '" '), trim($sysDescr, '" '), $vendor, $model, $uptime]);
        echo "  Identified as: $vendor ($model), Hostname: $sysName\n";

        // --- Interface Discovery ---
        echo "  Scanning interfaces...\n";
        $ifIndices = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.2.2.1.1");
        if (!$ifIndices) {
            echo "    Failed to walk ifIndex. Trying ifDescr fallback...\n";
            $ifIndices = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.2.2.1.2");
        }

        if ($ifIndices) {
            foreach ($ifIndices as $oid => $idx) {
                $idx = (int)trim((string)$idx, '" ');
                if ($idx <= 0) {
                    // Extract index from OID if the value is not the index itself
                    $parts = explode('.', $oid);
                    $idx = (int)end($parts);
                }

                $name = trim((string)@snmp2_get($ip, $community, ".1.3.6.1.2.1.31.1.1.1.1.$idx"), '" ');
                if (!$name) $name = trim((string)@snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.2.$idx"), '" ');
                
                $descr = trim((string)@snmp2_get($ip, $community, ".1.3.6.1.2.1.31.1.1.1.18.$idx"), '" '); // ifAlias
                if (!$descr) $descr = $name;

                $macRaw = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.6.$idx");
                $mac = '00:00:00:00:00:00';
                if ($macRaw) {
                    $hex = bin2hex((string)$macRaw);
                    if (strlen($hex) == 12) {
                        $mac = strtoupper(implode(':', str_split($hex, 2)));
                    }
                }

                $statusRaw = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.8.$idx");
                $status = (trim((string)$statusRaw, '" ') == '1' || strpos(strtolower((string)$statusRaw), 'up') !== false) ? 'up' : 'down';
                
                $speed = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.5.$idx");
                $inBytes = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.10.$idx");
                $outBytes = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.16.$idx");
                $inPkts = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.11.$idx");
                $outPkts = (int)@snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.17.$idx");

                $vlan = 0;
                if (preg_match('/vlan\s*(\d+)/i', $name, $m)) $vlan = (int)$m[1];
                elseif (preg_match('/vlan\s*(\d+)/i', $descr, $m)) $vlan = (int)$m[1];

                $stmtIf = $pdo->prepare("INSERT INTO plugin_dflow_interfaces 
                    (device_ip, if_index, name, description, mac_address, vlan, speed, status, in_bytes, out_bytes, in_packets, out_packets) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    description = VALUES(description), 
                    mac_address = VALUES(mac_address), 
                    vlan = IF(VALUES(vlan) > 0, VALUES(vlan), vlan), 
                    speed = VALUES(speed),
                    status = VALUES(status),
                    in_bytes = VALUES(in_bytes),
                    out_bytes = VALUES(out_bytes),
                    in_packets = VALUES(in_packets),
                    out_packets = VALUES(out_packets),
                    updated_at = NOW()");
                $stmtIf->execute([$ip, $idx, $name, $descr, $mac, $vlan, $speed, $status, $inBytes, $outBytes, $inPkts, $outPkts]);
            }
            echo "    Processed " . count($ifIndices) . " interfaces.\n";
        }

        // --- VLAN Discovery ---
        echo "  Discovering VLANs...\n";
        $vlanNames = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.17.7.1.4.3.1.1");
        if ($vlanNames) {
            foreach ($vlanNames as $oid => $vName) {
                $vlanId = (int)substr($oid, strrpos($oid, '.') + 1);
                $vName = trim((string)$vName, '" ');
                $stmtVlan = $pdo->prepare("INSERT INTO plugin_dflow_vlans (device_ip, vlan_id, vlan_name, vlan_status) 
                                         VALUES (?, ?, ?, 'active') 
                                         ON DUPLICATE KEY UPDATE vlan_name = VALUES(vlan_name), last_updated = NOW()");
                $stmtVlan->execute([$ip, $vlanId, $vName]);
            }
        }

        // --- ARP Table Discovery (Hosts) ---
        echo "  Fetching ARP table for host inventory...\n";
        $arpTable = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.4.22.1.2");
        if ($arpTable) {
            // Get interface-to-vlan mapping for this device
            $ifToVlan = [];
            $stmtMap = $pdo->prepare("SELECT if_index, vlan FROM plugin_dflow_interfaces WHERE device_ip = ?");
            $stmtMap->execute([$ip]);
            while ($row = $stmtMap->fetch(PDO::FETCH_ASSOC)) {
                $ifToVlan[(int)$row['if_index']] = (int)$row['vlan'];
            }

            foreach ($arpTable as $oid => $macRaw) {
                // OID format: .1.3.6.1.2.1.4.22.1.2.ifIndex.ip.ip.ip.ip
                $parts = explode('.', $oid);
                $partsCount = count($parts);
                if ($partsCount >= 10) {
                    $ipAddr = $parts[$partsCount-4] . "." . $parts[$partsCount-3] . "." . $parts[$partsCount-2] . "." . $parts[$partsCount-1];
                    $ifIndex = (int)$parts[$partsCount-5];
                    
                    $vlanId = $ifToVlan[$ifIndex] ?? 0;

                    $hex = bin2hex((string)$macRaw);
                    if (strlen($hex) == 12) {
                        $mac = strtoupper(implode(':', str_split($hex, 2)));
                        $stmtHost = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, mac_address, vlan, last_seen) 
                            VALUES (?, ?, ?, NOW()) 
                            ON DUPLICATE KEY UPDATE 
                            mac_address = VALUES(mac_address), 
                            vlan = IF(VALUES(vlan) > 0, VALUES(vlan), vlan),
                            last_seen = NOW()");
                        $stmtHost->execute([$ipAddr, $mac, $vlanId]);
                    }
                }
            }
        }

    } catch (Exception $e) {
        echo "  SNMP error for $ip: " . $e->getMessage() . "\n";
    }
}

echo "SNMP Collector finished.\n";
