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

$devices = $snmpPlugin['config']['devices'] ?? [];
if (empty($devices)) {
    die("No SNMP devices configured.\n");
}

// Check for SNMP extension
$hasSnmp = extension_loaded('snmp');
if (!$hasSnmp) {
    die("CRITICAL: PHP SNMP extension not found. Cannot perform real-time discovery.\n");
}

foreach ($devices as $dev) {
    $ip = $dev['ip'] ?? '';
    if (!$ip) continue;

    echo "Processing Device: $ip...\n";
    
    $community = $dev['community'] ?? 'public';
    $interfaces = [];
    $vendor = 'Generic';
    $model = 'Unknown Device';

    if ($hasSnmp) {
        snmp_set_quick_print(true);
        snmp_set_enum_print(true);
        
        // --- NEW: Enhanced Vendor Identification ---
        $sysDescr = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.1.0");
        $sysObjectID = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.2.0");
        
        if ($sysObjectID) {
            // Try to translate OID to name using our new MIB base
            snmp_set_oid_output_format(SNMP_OID_OUTPUT_FULL);
            $translated = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.2.0");
            snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
            
            if (preg_match('/::([a-zA-Z0-9_-]+)/', (string)$translated, $m)) {
                $model = $m[1];
            }
            
            if (strpos((string)$sysObjectID, "1.3.6.1.4.1.9") !== false) $vendor = 'Cisco';
            elseif (strpos((string)$sysObjectID, "1.3.6.1.4.1.2011") !== false) $vendor = 'Huawei';
            elseif (strpos((string)$sysObjectID, "1.3.6.1.4.1.11") !== false) $vendor = 'HP';
            elseif (strpos((string)$sysObjectID, "1.3.6.1.4.1.2636") !== false) $vendor = 'Juniper';
            elseif (strpos((string)$sysObjectID, "1.3.6.1.4.1.14988") !== false) $vendor = 'MikroTik';
            elseif (strpos((string)$sysObjectID, "1.3.6.1.4.1.8072") !== false) $vendor = 'Net-SNMP';
        }
        
        echo "  Identified as: $vendor ($model)\n";
         // --- END Vendor Identification ---

         // --- NEW: Save Device Info ---
         $sysName = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.5.0");
         $uptime = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.3.0");
         
         $stmtDev = $pdo->prepare("INSERT INTO plugin_dflow_devices 
            (ip_address, hostname, description, vendor, model, uptime) 
            VALUES (?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            hostname = VALUES(hostname), 
            description = VALUES(description), 
            vendor = VALUES(vendor), 
            model = VALUES(model), 
            uptime = VALUES(uptime)");
         $stmtDev->execute([
             $ip, 
             trim((string)$sysName, '" '), 
             trim((string)$sysDescr, '" '), 
             $vendor, 
             $model, 
             (int)$uptime
         ]);
         // --- END Save Device Info ---

         snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

        try {
            // Get Interface Indices
            $ifIndices = @snmp2_real_walk($ip, $community, ".1.3.6.1.2.1.2.2.1.1");
            if ($ifIndices) {
                foreach ($ifIndices as $oid => $index) {
                    $index = trim($index, '" ');
                    $name = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.2.$index");
                    $descr = @snmp2_get($ip, $community, ".1.3.6.1.2.1.31.1.1.1.18.$index") ?: $name; // ifAlias
                    $macRaw = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.6.$index");
                    $speed = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.5.$index");
                    $status = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.7.$index");
                    
                    // Traffic counters
                    $inBytes = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.10.$index") ?: 0;
                    $outBytes = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.16.$index") ?: 0;
                    $inPkts = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.11.$index") ?: 0;
                    $outPkts = @snmp2_get($ip, $community, ".1.3.6.1.2.1.2.2.1.17.$index") ?: 0;

                    // Format MAC
                    $mac = '00:00:00:00:00:00';
                    if ($macRaw) {
                        $hex = bin2hex($macRaw);
                        if (strlen($hex) == 12) {
                            $mac = implode(':', str_split($hex, 2));
                        }
                    }

                    // VLAN discovery (Simplified: try to find VLAN in description or use Bridge MIB if you want complexity)
                    $vlan = 0;
                    if (preg_match('/vlan\s*(\d+)/i', (string)$name, $m)) $vlan = (int)$m[1];
                    elseif (preg_match('/vlan\s*(\d+)/i', (string)$descr, $m)) $vlan = (int)$m[1];

                    $interfaces[] = [
                        'index' => (int)$index,
                        'name' => trim((string)$name, '" '),
                        'alias' => trim((string)$descr, '" '),
                        'mac' => $mac,
                        'vlan' => $vlan,
                        'speed' => (int)$speed,
                        'status' => (int)$status == 1 ? 'up' : 'down',
                        'in_bytes' => (int)$inBytes,
                        'out_bytes' => (int)$outBytes,
                        'in_pkts' => (int)$inPkts,
                        'out_pkts' => (int)$outPkts
                    ];
                }
            }
        } catch (Exception $e) {
            echo "SNMP error for $ip: " . $e->getMessage() . "\n";
        }
    }

    if (empty($interfaces)) {
        echo "No interfaces discovered for $ip via SNMP. Skipping device.\n";
        continue;
    }

    foreach ($interfaces as $iface) {
        $stmt = $pdo->prepare("INSERT INTO plugin_dflow_interfaces 
            (device_ip, if_index, name, description, mac_address, vlan, speed, status, in_bytes, out_bytes, in_packets, out_packets) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            device_ip = VALUES(device_ip),
            name = VALUES(name), 
            description = VALUES(description), 
            mac_address = VALUES(mac_address), 
            vlan = IF(VALUES(vlan) > 0, VALUES(vlan), vlan), 
            speed = VALUES(speed),
            status = VALUES(status),
            in_bytes = VALUES(in_bytes),
            out_bytes = VALUES(out_bytes),
            in_packets = VALUES(in_packets),
            out_packets = VALUES(out_packets)");
        $stmt->execute([
            $ip, $iface['index'], $iface['name'], $iface['alias'], $iface['mac'], $iface['vlan'], $iface['speed'], $iface['status'],
            $iface['in_bytes'], $iface['out_bytes'], $iface['in_pkts'], $iface['out_pkts']
        ]);

        // --- NEW: LLDP/CDP Discovery ---
        if ($hasSnmp) {
            echo "  Checking LLDP/CDP for interface {$iface['index']}...\n";
            // LLDP-MIB: lldpRemSysName (.1.0.8802.1.1.2.1.4.1.1.9)
            $lldpRemSysName = @snmp2_real_walk($ip, $community, ".1.0.8802.1.1.2.1.4.1.1.9");
            if ($lldpRemSysName) {
                foreach ($lldpRemSysName as $oid => $val) {
                    $parts = explode('.', $oid);
                    // OID format: .1.0.8802.1.1.2.1.4.1.1.9.time.localIfIndex.remIndex
                    $localIfIndex = $parts[count($parts)-2];
                    if ($localIfIndex == $iface['index']) {
                        $remoteSysName = trim((string)$val, '" ');
                        $remotePortId = @snmp2_get($ip, $community, ".1.0.8802.1.1.2.1.4.1.1.7." . $parts[count($parts)-3] . "." . $localIfIndex . "." . $parts[count($parts)-1]);
                        
                        $stmtTopo = $pdo->prepare("INSERT INTO plugin_dflow_topology 
                            (local_device_ip, local_port_index, remote_device_name, remote_port_id, protocol) 
                            VALUES (?, ?, ?, ?, 'LLDP') 
                            ON DUPLICATE KEY UPDATE remote_device_name = VALUES(remote_device_name), remote_port_id = VALUES(remote_port_id)");
                        $stmtTopo->execute([$ip, $iface['index'], $remoteSysName, trim((string)$remotePortId, '" ')]);
                    }
                }
            }

            // CDP-MIB: cdpCacheDeviceId (.1.3.6.1.4.1.9.9.23.1.2.1.1.6)
            $cdpCacheDeviceId = @snmp2_real_walk($ip, $community, ".1.3.6.1.4.1.9.9.23.1.2.1.1.6");
            if ($cdpCacheDeviceId) {
                foreach ($cdpCacheDeviceId as $oid => $val) {
                    $parts = explode('.', $oid);
                    // OID format: .1.3.6.1.4.1.9.9.23.1.2.1.1.6.localIfIndex.remDeviceIndex
                    $localIfIndex = $parts[count($parts)-2];
                    if ($localIfIndex == $iface['index']) {
                        $remoteDeviceId = trim((string)$val, '" ');
                        $remotePortId = @snmp2_get($ip, $community, ".1.3.6.1.4.1.9.9.23.1.2.1.1.7." . $localIfIndex . "." . $parts[count($parts)-1]);
                        
                        $stmtTopo = $pdo->prepare("INSERT INTO plugin_dflow_topology 
                            (local_device_ip, local_port_index, remote_device_name, remote_port_id, protocol) 
                            VALUES (?, ?, ?, ?, 'CDP') 
                            ON DUPLICATE KEY UPDATE remote_device_name = VALUES(remote_device_name), remote_port_id = VALUES(remote_port_id)");
                        $stmtTopo->execute([$ip, $iface['index'], $remoteDeviceId, trim((string)$remotePortId, '" ')]);
                    }
                }
            }
        }
        // --- END LLDP/CDP ---

        // If we have a MAC and VLAN, try to find IPs for this MAC in flows and update hosts
        if ($iface['mac'] !== '00:00:00:00:00:00') {
            $stmtFlowIPs = $pdo->prepare("SELECT DISTINCT src_ip as ip FROM plugin_dflow_flows WHERE vlan = ?");
            $stmtFlowIPs->execute([$iface['vlan']]);
            $ips = $stmtFlowIPs->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($ips as $ipAddr) {
                $stmtHost = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, mac_address, vlan) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE mac_address = VALUES(mac_address), vlan = VALUES(vlan)");
                $stmtHost->execute([$ipAddr, $iface['mac'], $iface['vlan']]);
            }
        }
    }

    // 2. Correlation logic: Update flows with interface info if MAC matches
    echo "Correlating flows for $ip...\n";
    
    // Correlate by MAC via Host table
    $stmtUpdate = $pdo->prepare("UPDATE plugin_dflow_flows f
        JOIN plugin_dflow_hosts h ON (f.src_ip = h.ip_address OR f.dst_ip = h.ip_address)
        JOIN plugin_dflow_interfaces i ON h.mac_address = i.mac_address
        SET f.application = IF(f.application IS NULL OR f.application = '', 'SNMP/MAC Correlated', f.application),
            f.vlan = IF(f.vlan IS NULL OR f.vlan = 0, i.vlan, f.vlan)
        WHERE (f.application IS NULL OR f.application = '' OR f.vlan = 0 OR f.vlan IS NULL)");
    $stmtUpdate->execute();
    
    // Also correlate based on VLAN if available in flows
    $stmtUpdateVlan = $pdo->prepare("UPDATE plugin_dflow_flows f
        SET f.application = 'VLAN Correlated'
        WHERE f.vlan IN (SELECT DISTINCT vlan FROM plugin_dflow_interfaces WHERE vlan > 0)
        AND (f.application IS NULL OR f.application = '')");
    $stmtUpdateVlan->execute();
    
    echo "Done with $ip.\n";
}

echo "SNMP Collector finished.\n";
