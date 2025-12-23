<?php

/**
 * NSX-T / NSX Manager API Client
 */

function nsx_get_client(array $config) {
    return new class($config) {
        private $url;
        private $username;
        private $password;
        private $lastError = '';

        public function __construct($config) {
            $url = rtrim($config['url'], '/');
            if (!str_starts_with($url, 'http')) {
                $url = 'https://' . $url;
            }
            $this->url = $url;
            $this->username = $config['username'];
            $this->password = $config['password'];
        }

        public function getLastError() {
            return $this->lastError;
        }

        private function request($endpoint, $method = 'GET', $data = null, $cacheTtl = 300, $force = false, $suppressLogs = false) {
            global $pdo;
            $this->lastError = '';
            
            // Reset script execution timer for each request to prevent global timeout
            if (!ini_get('safe_mode')) {
                @set_time_limit(60);
            }
            
            // Try cache for GET requests
            $cacheKey = '';
            if ($method === 'GET' && isset($pdo) && !$force) {
                $cacheKey = 'nsx_api_' . md5($this->url . $endpoint . $this->username);
                $cached = plugin_cache_get($pdo, $cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            } elseif ($method === 'GET' && isset($pdo) && $force) {
                $cacheKey = 'nsx_api_' . md5($this->url . $endpoint . $this->username);
            }

            $ch = curl_init($this->url . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            if ($curlError) {
                $this->lastError = "Connection Error: " . $curlError;
                if (!$suppressLogs) error_log("NSX API Error ($endpoint): " . $curlError);
            }
            
            if ($httpCode >= 400) {
                $this->lastError = "HTTP Error " . $httpCode;
                if (!$suppressLogs) error_log("NSX API HTTP Error ($endpoint): " . $httpCode . " - Response: " . $response);
            }

            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                // Save to cache if successful
                if ($cacheKey && isset($pdo) && $decoded) {
                    plugin_cache_set($pdo, $cacheKey, $decoded, $cacheTtl);
                }
                return $decoded;
            }
            return null;
        }

        /**
         * Get Tier-0 and Tier-1 Gateways (Edges)
         */
        public function getGateways($force = false) {
            $t0 = $this->request('/policy/api/v1/infra/tier-0s', 'GET', null, 300, $force);
            $t1 = $this->request('/policy/api/v1/infra/tier-1s', 'GET', null, 300, $force);
            
            $results = [
                'tier0' => $t0['results'] ?? [],
                'tier1' => $t1['results'] ?? []
            ];

            // Try to get stats for each T0
            foreach ($results['tier0'] as &$gw) {
                if (isset($gw['id'])) {
                    // Try different statistics endpoints
                    $stats = $this->request("/policy/api/v1/infra/tier-0s/{$gw['id']}/statistics", 'GET', null, 60, $force, true);
                    
                    if (!$stats || isset($stats['error_code'])) {
                        // Try via locale-services if top-level stats fail
                        $locales = $this->request("/policy/api/v1/infra/tier-0s/{$gw['id']}/locale-services", 'GET', null, 300, $force, true);
                        if (isset($locales['results'])) {
                            foreach ($locales['results'] as $locale) {
                                $lId = $locale['id'];
                                $lStats = $this->request("/policy/api/v1/infra/tier-0s/{$gw['id']}/locale-services/$lId/statistics", 'GET', null, 60, $force, true);
                                if ($lStats) {
                                    $stats = $lStats;
                                    break;
                                }
                            }
                        }
                    }

                    // Check if stats are zero or missing
                    $totalRx = 0;
                    $totalTx = 0;
                    if (isset($stats['results'][0]['per_node_statistics'])) {
                        foreach ($stats['results'][0]['per_node_statistics'] as $nodeStat) {
                            $totalRx += $nodeStat['rx']['total_bytes'] ?? 0;
                            $totalTx += $nodeStat['tx']['total_bytes'] ?? 0;
                        }
                    }

                    // If zero, try deeper lookup via locale-services interfaces (Uplinks)
                    if ($totalRx === 0 && $totalTx === 0) {
                        if (!isset($locales)) {
                            $locales = $this->request("/policy/api/v1/infra/tier-0s/{$gw['id']}/locale-services", 'GET', null, 300, $force);
                        }
                        if (isset($locales['results'])) {
                            foreach ($locales['results'] as $locale) {
                                $lId = $locale['id'];
                                $ifaces = $this->request("/policy/api/v1/infra/tier-0s/{$gw['id']}/locale-services/$lId/interfaces", 'GET', null, 300, $force);
                                if (isset($ifaces['results'])) {
                                    foreach ($ifaces['results'] as $iface) {
                                        $ifaceStats = $this->request("/policy/api/v1/infra/tier-0s/{$gw['id']}/locale-services/$lId/interfaces/{$iface['id']}/statistics", 'GET', null, 60, $force);
                                        if (isset($ifaceStats['results'][0])) {
                                            $totalRx += $ifaceStats['results'][0]['rx']['total_bytes'] ?? 0;
                                            $totalTx += $ifaceStats['results'][0]['tx']['total_bytes'] ?? 0;
                                        }
                                    }
                                }
                            }
                        }
                        
                        if ($totalRx > 0 || $totalTx > 0) {
                            $stats = [
                                'results' => [[
                                    'per_node_statistics' => [[
                                        'rx' => ['total_bytes' => $totalRx],
                                        'tx' => ['total_bytes' => $totalTx]
                                    ]]
                                ]]
                            ];
                        }
                    }
                    
                    $gw['stats'] = $stats;
                    $gw['rx_bytes'] = $totalRx;
                    $gw['tx_bytes'] = $totalTx;

                    if (isset($pdo)) {
                        $historyKey = "nsx_t0_history_" . md5($this->url . $gw['id']);
                        $history = plugin_cache_get($pdo, $historyKey) ?: [];
                        $history[] = ['rx' => $totalRx, 'tx' => $totalTx, 'time' => time()];
                        if (count($history) > 12) array_shift($history);
                        plugin_cache_set($pdo, $historyKey, $history, 3600);
                        $gw['history'] = $history;
                    }
                }
            }

            // Try to get status for each T1
            foreach ($results['tier1'] as &$gw) {
                if (isset($gw['id'])) {
                    // Try standard Policy status endpoint
                    $status = $this->request("/policy/api/v1/infra/tier-1s/{$gw['id']}/status", 'GET', null, 60, $force, true);
                    
                    if (!$status || isset($status['error_code'])) {
                        // Fallback 1: Realized State (More reliable for Policy objects)
                        $realized = $this->request("/policy/api/v1/infra/realized-state/status?intent_path=/infra/tier-1s/{$gw['id']}", 'GET', null, 60, $force, true);
                        if ($realized && isset($realized['publish_status'])) {
                            $status = [
                                'state' => $realized['publish_status'] === 'REALIZED' ? 'SUCCESS' : $realized['publish_status'],
                                'details' => $realized
                            ];
                        } else {
                            // Fallback 2: The object itself
                            $status = $this->request("/policy/api/v1/infra/tier-1s/{$gw['id']}", 'GET', null, 60, $force);
                        }
                    }
                    $gw['status'] = $status;
                }
            }
            
            return $results;
        }

        /**
         * Get All Interfaces and IP Allocations
         */
        public function getInterfaces() {
            global $pdo;
            $cacheKey = 'nsx_segments_stats_' . md5($this->url . $this->username);
            
            // Tenta pegar do cache primeiro para evitar lentidão
            if (isset($pdo)) {
                $cached = plugin_cache_get($pdo, $cacheKey);
                if ($cached !== null) return $cached;
            }

            $interfaces = $this->request('/policy/api/v1/infra/segments');
            $results = $interfaces['results'] ?? [];
            
            // Fetch stats for each segment
            foreach ($results as &$seg) {
                if (isset($seg['id'])) {
                    $stats = $this->request("/policy/api/v1/infra/segments/{$seg['id']}/statistics");
                    $seg['stats'] = $stats;
                    
                    $seg['rx_bytes'] = 0;
                    $seg['tx_bytes'] = 0;
                    if (isset($stats['results'][0])) {
                        $seg['rx_bytes'] = $stats['results'][0]['rx']['total_bytes'] ?? 0;
                        $seg['tx_bytes'] = $stats['results'][0]['tx']['total_bytes'] ?? 0;
                    }
                }
            }

            // Salva no cache por 10 minutos (mais agressivo para performance)
            if (isset($pdo)) {
                plugin_cache_set($pdo, $cacheKey, $results, 600);
            }

            return $results;
        }

        /**
         * Get Edge Cluster Status
         */
        public function getEdgeStatus() {
            // Fetch transport nodes
            $res = $this->request('/api/v1/transport-nodes?node_type=EdgeNode');
            if ($res && isset($res['results'])) {
                foreach ($res['results'] as &$node) {
                    // If IP is missing, try to find it in deployment info
                    if (empty($node['fqdn_or_ip_address']) && isset($node['node_deployment_info']['ip_addresses'][0])) {
                        $node['fqdn_or_ip_address'] = $node['node_deployment_info']['ip_addresses'][0];
                    }
                }
            }
            return $res;
        }

        /**
         * Get All Edge Nodes with their health status
         */
        public function getEdgeNodesWithStatus($force = false) {
            global $pdo;
            $cacheKey = 'nsx_edge_nodes_status_' . md5($this->url . $this->username);
            
            if (isset($pdo) && !$force) {
                $cached = plugin_cache_get($pdo, $cacheKey);
                if ($cached !== null) return $cached;
            }

            $allNodes = [];

            // 1. Try Policy API for Edge Clusters first (more reliable for Policy-based deployments)
            $clusters = $this->request('/policy/api/v1/infra/sites/default/enforcement-points/default/edge-clusters', 'GET', null, 300, $force, true);
            if ($clusters && isset($clusters['results'])) {
                foreach ($clusters['results'] as $cluster) {
                    $clusterId = $cluster['id'];
                    $state = $this->request("/policy/api/v1/infra/sites/default/enforcement-points/default/edge-clusters/$clusterId/state", 'GET', null, 300, $force, true);
                    if ($state && isset($state['member_states'])) {
                        foreach ($state['member_states'] as $mState) {
                            $allNodes[] = [
                                'id' => $mState['transport_node_id'] ?? $mState['member_index'],
                                'display_name' => $mState['node_display_name'] ?? "Edge Node",
                                'state' => strtoupper($mState['state'] ?? 'UNKNOWN'),
                                'transport_node_id' => $mState['transport_node_id'] ?? null
                            ];
                        }
                    }
                }
            }

            // 2. Fallback/Supplement with Manager API Transport Nodes
            $tnodes = $this->request('/api/v1/transport-nodes', 'GET', null, 300, $force, true);
            if ($tnodes && isset($tnodes['results'])) {
                foreach ($tnodes['results'] as $tn) {
                    // Filter for Edges only
                    $isEdge = false;
                    if (isset($tn['node_deployment_info']['resource_type']) && $tn['node_deployment_info']['resource_type'] === 'EdgeNode') {
                        $isEdge = true;
                    } elseif (str_contains(strtoupper($tn['display_name'] ?? ''), 'EDGE')) {
                        $isEdge = true;
                    }

                    if ($isEdge) {
                        // Check if already added via Policy API
                        $exists = false;
                        foreach ($allNodes as $existing) {
                            if (($existing['transport_node_id'] ?? $existing['id']) === $tn['id']) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $allNodes[] = $tn;
                        }
                    }
                }
            }

            if (empty($allNodes)) return ['results' => []];

            // 3. Fetch status for each Edge node found
            foreach ($allNodes as &$node) {
                $id = $node['transport_node_id'] ?? ($node['id'] ?? ($node['node_id'] ?? null));
                
                if ($id && (strtoupper($node['state'] ?? 'UNKNOWN') === 'UNKNOWN')) {
                    $status = $this->getNodeStatus($id, $force);
                    if ($status) {
                        $state = $status['state'] ?? ($status['status'] ?? 'UNKNOWN');
                        $node['state'] = strtoupper($state);
                        $node['transport_node_status'] = $status;
                    }
                }

                // Ensure IP address is present
                if (empty($node['fqdn_or_ip_address']) && isset($node['node_deployment_info']['ip_addresses'][0])) {
                    $node['fqdn_or_ip_address'] = $node['node_deployment_info']['ip_addresses'][0];
                }
            }

            $finalResult = ['results' => $allNodes];
            if (isset($pdo)) {
                plugin_cache_set($pdo, $cacheKey, $finalResult, 300);
            }

            return $finalResult;
        }

        /**
         * Get Node Status Detail
         */
        public function getNodeStatus($nodeId, $force = false) {
            return $this->request("/api/v1/transport-nodes/$nodeId/status", 'GET', null, 300, $force);
        }

        /**
         * Get BGP Neighbors from Tier-0 Gateways
         */
        public function getBgpNeighbors() {
            $t0s = $this->request('/policy/api/v1/infra/tier-0s');
            $neighbors = [];
            
            if ($t0s && isset($t0s['results'])) {
                foreach ($t0s['results'] as $t0) {
                    $id = $t0['id'];
                    // Get locales
                    $locales = $this->request("/policy/api/v1/infra/tier-0s/$id/locale-services");
                    if ($locales && isset($locales['results'])) {
                        foreach ($locales['results'] as $locale) {
                            $lId = $locale['id'];
                            $bgp = $this->request("/policy/api/v1/infra/tier-0s/$id/locale-services/$lId/bgp/neighbors");
                            if ($bgp && isset($bgp['results'])) {
                                foreach ($bgp['results'] as $neighbor) {
                                    $neighbors[] = [
                                        'neighbor_address' => $neighbor['neighbor_address'],
                                        'remote_as' => $neighbor['remote_as_num'],
                                        'display_name' => $neighbor['display_name'] ?? $neighbor['neighbor_address'],
                                        'source_t0' => $t0['display_name']
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            return $neighbors;
        }
        
        /**
         * Get NAT Rules (WAN/LAN mapping)
         */
        public function getNatRules() {
            // Simplified: usually requires tier-0 or tier-1 context
            return []; 
        }
    };
}

/**
 * Aggregates statistics from all configured NSX managers
 */
function nsx_get_aggregated_stats(PDO $pdo) {
    $plugin = plugin_get_by_name($pdo, 'nsx');
    if (!$plugin || !$plugin['is_active']) return null;

    $managers = $plugin['config']['managers'] ?? [];
    $stats = [
        'total_edges' => 0,
        'total_segments' => 0,
        'total_ips' => 0,
        'gateways' => [],
        'nodes' => []
    ];

    foreach ($managers as $mConfig) {
        $client = nsx_get_client($mConfig);
        
        $gws = $client->getGateways();
        $stats['total_edges'] += count($gws['tier0']) + count($gws['tier1']);
        
        $segments = $client->getInterfaces();
        $stats['total_segments'] += count($segments);
        
        foreach ($segments as $seg) {
            if (isset($seg['subnets'])) {
                foreach ($seg['subnets'] as $subnet) {
                    if (isset($subnet['gateway_address'])) {
                        // Extract CIDR to estimate IP capacity or just count configured gateways
                        $stats['total_ips'] += 1;
                    }
                }
            }
        }

        $nodes = $client->getEdgeStatus();
        if (isset($nodes['results'])) {
            foreach ($nodes['results'] as $node) {
                $stats['nodes'][] = [
                    'name' => $node['display_name'],
                    'status' => $node['state'] ?? 'UNKNOWN',
                    'ip' => $node['fqdn_or_ip_address'] ?? ''
                ];
            }
        }
    }

    return $stats;
}

/**
 * Get local NSX data status (Service Health)
 */
function nsx_get_local_data_status($pdo) {
    try {
        $stmt = $pdo->query("SELECT updated_at FROM plugin_nsx_data WHERE data_type = 'aggregated_full'");
        $row = $stmt->fetch();
        
        if (!$row) return ['status' => 'DOWN', 'last_update' => null, 'message' => 'Nenhum dado coletado ainda.'];
        
        $lastUpdate = strtotime($row['updated_at']);
        $diff = time() - $lastUpdate;
        
        if ($diff < 600) { // 10 minutes
            return ['status' => 'UP', 'last_update' => $row['updated_at'], 'diff' => $diff];
        } elseif ($diff < 1800) { // 30 minutes
            return ['status' => 'WARNING', 'last_update' => $row['updated_at'], 'diff' => $diff, 'message' => 'Dados começando a ficar desatualizados.'];
        } else {
            return ['status' => 'DOWN', 'last_update' => $row['updated_at'], 'diff' => $diff, 'message' => 'Coletor parece estar parado.'];
        }
    } catch (Exception $e) {
        return ['status' => 'ERROR', 'message' => $e->getMessage()];
    }
}

/**
 * Fetches the latest collected data from the local database (Reader mode)
 */
function nsx_get_local_data($pdo) {
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT data_content, updated_at FROM plugin_nsx_data WHERE data_type = 'aggregated_full'");
    $stmt->execute();
    $row = $stmt->fetch();
    
    if ($row) {
        $data = json_decode($row['data_content'], true);
        if ($data) {
            $data['last_update'] = $row['updated_at'];
            return $data;
        }
    }
    return null;
}
