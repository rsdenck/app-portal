<?php

/**
 * vCenter Collector Script
 * Runs in background to fetch data from vCenter Servers and store locally.
 * Supports SOAP (/sdk) as primary and REST (/api) as fallback.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting vCenter Data Collection...\n";

// Get active vCenter plugin configuration
$plugin = plugin_get_by_name($pdo, 'vcenter');

if (!$plugin || !$plugin['is_active']) {
    echo "[" . date('Y-m-d H:i:s') . "] vCenter Plugin is not active. Exiting.\n";
    exit(0);
}

$servers = $plugin['config']['servers'] ?? [];
if (empty($servers)) {
    echo "[" . date('Y-m-d H:i:s') . "] No vCenter Servers configured. Exiting.\n";
    exit(0);
}

$aggregatedData = [
    'stats' => [
        'total_vms' => 0,
        'running_vms' => 0,
        'total_hosts' => 0,
        'total_clusters' => 0,
        'total_datacenters' => 0,
        'total_datastores' => 0,
        'server_count' => count($servers),
    ],
    'vms' => [],
    'hosts' => [],
    'clusters' => [],
    'datacenters' => [],
    'datastores' => [],
    'errors' => [],
    'collected_at' => date('c')
];

foreach ($servers as $sIdx => $srv) {
    $serverLabel = $srv['label'] ?: $srv['url'];
    echo "[" . date('Y-m-d H:i:s') . "] Collecting from vCenter #" . ($sIdx + 1) . " ($serverLabel)...\n";
    
    try {
        // --- 1. TRY SOAP (User preference) ---
        echo "   - Trying SOAP API (/sdk)...\n";
        $data = vcenter_get_data_soap($srv);
        
        if ($data) {
            $aggregatedData['stats']['total_vms'] += $data['stats']['total_vms'];
            $aggregatedData['stats']['running_vms'] += $data['stats']['running_vms'];
            $aggregatedData['stats']['total_hosts'] += $data['stats']['total_hosts'];
            $aggregatedData['stats']['total_datacenters'] += $data['stats']['total_datacenters'];
            $aggregatedData['stats']['total_clusters'] += $data['stats']['total_clusters'];
            $aggregatedData['stats']['total_datastores'] += $data['stats']['total_datastores'];
            
            foreach ($data['vms'] as $vm) {
                $aggregatedData['vms'][] = array_merge($vm, ['server_label' => $serverLabel]);
            }
            foreach ($data['hosts'] as $host) {
                $aggregatedData['hosts'][] = array_merge($host, ['server_label' => $serverLabel]);
            }
            foreach ($data['clusters'] as $cluster) {
                $aggregatedData['clusters'][] = array_merge($cluster, ['server_label' => $serverLabel]);
            }
            foreach ($data['datacenters'] as $dc) {
                $aggregatedData['datacenters'][] = array_merge($dc, ['server_label' => $serverLabel]);
            }
            foreach ($data['datastores'] as $ds) {
                $aggregatedData['datastores'][] = array_merge($ds, ['server_label' => $serverLabel]);
            }
            
            echo "   - Collected via SOAP: " . count($data['vms']) . " VMs, " . count($data['hosts']) . " hosts, " . count($data['datastores']) . " datastores.\n";
            continue; // Success with SOAP, proceed to next server
        }

    } catch (Exception $soapEx) {
        echo "   - SOAP failed: " . $soapEx->getMessage() . "\n";
        echo "   - Falling back to REST API (/api)...\n";
        
        try {
            // --- 2. TRY REST FALLBACK ---
            // VMs
            $vms = vcenter_api_request($srv, '/vcenter/vm', 'GET', null, 0); 
            if (is_array($vms)) {
                $aggregatedData['stats']['total_vms'] += count($vms);
                foreach ($vms as $vm) {
                    if (($vm['power_state'] ?? '') === 'POWERED_ON') {
                        $aggregatedData['stats']['running_vms']++;
                    }
                    $aggregatedData['vms'][] = array_merge($vm, ['server_label' => $serverLabel]);
                }
            }

            // Hosts
            $hosts = vcenter_api_request($srv, '/vcenter/host', 'GET', null, 0);
            if (is_array($hosts)) {
                $aggregatedData['stats']['total_hosts'] += count($hosts);
                foreach ($hosts as $host) {
                    $aggregatedData['hosts'][] = array_merge($host, ['server_label' => $serverLabel]);
                }
            }

            // Clusters
            $clusters = vcenter_api_request($srv, '/vcenter/cluster', 'GET', null, 0);
            if (is_array($clusters)) {
                $aggregatedData['stats']['total_clusters'] += count($clusters);
                foreach ($clusters as $cluster) {
                    $aggregatedData['clusters'][] = array_merge($cluster, ['server_label' => $serverLabel]);
                }
            }

            // Datacenters
            $dcs = vcenter_api_request($srv, '/vcenter/datacenter', 'GET', null, 0);
            if (is_array($dcs)) {
                $aggregatedData['stats']['total_datacenters'] += count($dcs);
                foreach ($dcs as $dc) {
                    $aggregatedData['datacenters'][] = array_merge($dc, ['server_label' => $serverLabel]);
                }
            }

            // Datastores
            $datastores = vcenter_api_request($srv, '/vcenter/datastore', 'GET', null, 0);
            if (is_array($datastores)) {
                $aggregatedData['stats']['total_datastores'] += count($datastores);
                foreach ($datastores as $ds) {
                    $aggregatedData['datastores'][] = array_merge($ds, ['server_label' => $serverLabel]);
                }
            }

            echo "   - Collected via REST fallback.\n";

        } catch (Exception $restEx) {
            $errorMsg = "SOAP: " . $soapEx->getMessage() . " | REST: " . $restEx->getMessage();
            $aggregatedData['errors'][] = "$serverLabel: $errorMsg";
            echo "[" . date('Y-m-d H:i:s') . "] ERROR on vCenter #" . ($sIdx + 1) . ": $errorMsg\n";
        }
    }
}

// Global Summary
echo "--------------------------------------------------\n";
echo "[" . date('Y-m-d H:i:s') . "] GLOBAL CONSOLIDATION SUMMARY:\n";
echo "   - Total vCenters: " . $aggregatedData['stats']['server_count'] . "\n";
echo "   - Total VMs:      " . $aggregatedData['stats']['total_vms'] . " (" . $aggregatedData['stats']['running_vms'] . " running)\n";
echo "   - Total Hosts:    " . $aggregatedData['stats']['total_hosts'] . "\n";
echo "   - Total Clusters: " . $aggregatedData['stats']['total_clusters'] . "\n";
echo "   - Total DCs:      " . $aggregatedData['stats']['total_datacenters'] . "\n";
echo "   - Total DSs:      " . $aggregatedData['stats']['total_datastores'] . "\n";
echo "--------------------------------------------------\n";

// Store in database
try {
    $stmt = $pdo->prepare("INSERT INTO plugin_vcenter_data (data_type, data_content) 
                           VALUES ('aggregated_full', ?) 
                           ON DUPLICATE KEY UPDATE data_content = VALUES(data_content)");
    $stmt->execute([json_encode($aggregatedData)]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Collection complete. Data stored in plugin_vcenter_data.\n";
} catch (PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] DATABASE ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
