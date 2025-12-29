<?php

/**
 * NSX Collector Script
 * Runs in background to fetch data from NSX Managers and store locally.
 * Prevents API overhead during user dashboard access.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
// nsx_api.php is already included in bootstrap.php

echo "[" . date('Y-m-d H:i:s') . "] Starting NSX Data Collection...\n";

// Get active NSX plugin configuration
$plugin = plugin_get_by_name($pdo, 'nsx');

if (!$plugin || !$plugin['is_active']) {
    echo "[" . date('Y-m-d H:i:s') . "] NSX Plugin is not active. Exiting.\n";
    exit(0);
}

$managers = $plugin['config']['managers'] ?? [];
if (empty($managers)) {
    echo "[" . date('Y-m-d H:i:s') . "] No NSX Managers configured. Exiting.\n";
    exit(0);
}

$aggregatedData = [
    'gateways' => ['tier0' => [], 'tier1' => []],
    'segments' => [],
    'nodes' => [],
    'bgp_neighbors' => [],
    'collected_at' => date('c')
];

foreach ($managers as $mIdx => $mConfig) {
    echo "[" . date('Y-m-d H:i:s') . "] Collecting from Manager #" . ($mIdx + 1) . " (" . ($mConfig['url'] ?? 'unknown') . ")...\n";
    
    try {
        $client = nsx_get_client($mConfig);
        
        // 1. Gateways (Force refresh to bypass cache in collector)
        $gws = $client->getGateways(true);
        if ($gws) {
            // Keep history for charts
            foreach ($gws['tier0'] as &$gw) {
                if (isset($gw['id'])) {
                    $historyKey = "nsx_t0_history_" . md5($mConfig['url'] . $gw['id']);
                    $gw['history'] = plugin_cache_get($pdo, $historyKey) ?: [];
                }
            }
            $aggregatedData['gateways']['tier0'] = array_merge($aggregatedData['gateways']['tier0'], $gws['tier0'] ?? []);
            $aggregatedData['gateways']['tier1'] = array_merge($aggregatedData['gateways']['tier1'], $gws['tier1'] ?? []);
            echo "   - Collected " . count($gws['tier0'] ?? []) . " T0 and " . count($gws['tier1'] ?? []) . " T1 gateways.\n";
        }

        // 2. Segments
        $segments = $client->getInterfaces();
        if ($segments) {
            $aggregatedData['segments'] = array_merge($aggregatedData['segments'], $segments);
            echo "   - Collected " . count($segments) . " segments.\n";
        }

        // 3. Nodes
        $nodesResult = $client->getEdgeNodesWithStatus(true);
        if (isset($nodesResult['results'])) {
            foreach ($nodesResult['results'] as $node) {
                $aggregatedData['nodes'][] = $node;
            }
            echo "   - Collected " . count($nodesResult['results']) . " edge nodes.\n";
        }

        // 4. BGP Neighbors
        $bgpNeighbors = $client->getBgpNeighbors();
        if ($bgpNeighbors) {
            $aggregatedData['bgp_neighbors'] = array_merge($aggregatedData['bgp_neighbors'], $bgpNeighbors);
            echo "   - Collected " . count($bgpNeighbors) . " BGP neighbors.\n";
        }

    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR on Manager #" . ($mIdx + 1) . ": " . $e->getMessage() . "\n";
    }
}

// Store in database
try {
    $stmt = $pdo->prepare("INSERT INTO plugin_nsx_data (data_type, data_content) 
                           VALUES ('aggregated_full', ?) 
                           ON DUPLICATE KEY UPDATE data_content = VALUES(data_content)");
    $stmt->execute([json_encode($aggregatedData)]);
    
    echo "[" . date('Y-m-d H:i:s') . "] Collection complete. Data stored in plugin_nsx_data.\n";
} catch (PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] DATABASE ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
