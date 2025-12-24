<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

header('Content-Type: application/json');

$force = isset($_GET['force']) && $_GET['force'] == '1';

// 1. Load Threat Intel from Database
$stmt = $pdo->prepare("SELECT data FROM plugin_bgp_data WHERE type = 'threat_intel'");
$stmt->execute();
$row = $stmt->fetch();

$threatData = $row ? json_decode($row['data'], true) : [];

// Default structure if missing
$threatData = array_merge([
    'active_ips' => [],
    'vulnerable_ips' => [],
    'malicious_ips' => [],
    'tor_nodes' => [],
    'bgp_peers' => [],
    'attacks' => [],
    'infrastructure' => [],
    'snmp_data' => [],
    'wazuh_alerts' => [],
    'nuclei_findings' => [],
    'security_incidents' => [],
    'security_interfaces' => [],
    'security_mitre' => [],
    'top_stats' => [
        'talkers' => [],
        'services' => [],
        'as_traffic' => [],
        'locality' => ['internal' => 0, 'external' => 0]
    ],
    'stats' => ['active' => 0, 'vulnerable' => 0, 'malicious' => 0, 'attacks' => 0, 'tor' => 0]
], $threatData);

// BGP and Netflow Configs
$myAsn = $bgpPlugin['config']['my_asn'] ?? "262978";
$ipBlocksRaw = $bgpPlugin['config']['ip_blocks'] ?? "132.255.220.0/22, 186.250.184.0/22, 143.0.120.0/22";
$targetBlocks = array_filter(array_map('trim', explode(',', $ipBlocksRaw)));

// 2. Format for GeoJSON (Correlation Logic)
$features = [];

// Helper to convert loc string to coordinates
$toCoords = function($loc) {
    if (!$loc) return [0, 0];
    $parts = explode(',', $loc);
    return [floatval($parts[1] ?? 0), floatval($parts[0] ?? 0)]; // [lon, lat]
};

// Helper for Geolocation with IPinfo
function get_ip_geo($ip, $pdo, $token, $force = false) {
    if (!$ip || $ip === '127.0.0.1') return null;
    $cacheKey = "geo_ipinfo_$ip";
    $geo = $force ? null : plugin_cache_get($pdo, $cacheKey);
    if ($geo === null) {
        $url = "https://ipinfo.io/$ip" . ($token ? "?token=$token" : "");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $res = curl_exec($ch);
        $geo = json_decode($res, true);
        if ($geo && isset($geo['loc'])) {
            plugin_cache_set($pdo, $cacheKey, $geo, 86400 * 7); // 1 week cache for geo
        }
    }
    return $geo;
}

function ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) $range .= '/32';
    list($range, $netmask) = explode('/', $range, 2);
    
    // Se o IP for um bloco (ex: 132.255.220.0/22), extraímos o primeiro IP
    if (strpos($ip, '/') !== false) {
        list($ip, ) = explode('/', $ip, 2);
    }
    
    $range_dec = ip2long($range);
    $ip_dec = ip2long($ip);
    if ($ip_dec === false || $range_dec === false) return false;
    
    $wildcard_dec = pow(2, (32 - (int)$netmask)) - 1;
    $netmask_dec = ~ $wildcard_dec;
    return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
}

// --- ETAPA 0: Infraestrutura (Routers/Switches) ---
foreach ($threatData['infrastructure'] ?? [] as $infra) {
    if (!isset($infra['loc'])) continue;
    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'type' => 'infra',
            'ip' => $infra['ip'],
            'name' => "Infrastructure: " . $infra['name'],
            'infra_type' => $infra['type'],
            'status' => $infra['status'],
            'snmp' => $infra['snmp'] ?? null
        ],
        'geometry' => [
            'type' => 'Point',
            'coordinates' => $toCoords($infra['loc'])
        ]
    ];
}

// --- ETAPA 1: Seus IPs (Targets) ---
$targetIps = array_merge($threatData['active_ips'], $threatData['vulnerable_ips']);
foreach ($targetIps as $ip => $info) {
    if (!isset($info['geo']['loc'])) continue;
    
    $risk = $info['risk'] ?? 'low';
    
    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'type' => 'target',
            'ip' => $ip,
            'name' => "My Asset: $ip",
            'ports' => $info['ports'] ?? [],
            'risk' => $risk,
            'details' => $info['org'] ?? 'Internal Network',
            'country' => $info['geo']['country'] ?? 'BR',
            'city' => $info['geo']['city'] ?? 'Unknown',
            'asn' => $info['geo']['asn'] ?? 'AS262978',
            'is_sample' => $info['is_sample'] ?? false,
            'nuclei' => $threatData['nuclei_findings'][$ip] ?? []
        ],
        'geometry' => [
            'type' => 'Point',
            'coordinates' => $toCoords($info['geo']['loc'])
        ]
    ];
}

// --- ETAPA 2: IPs Externos (Attackers) ---
foreach ($threatData['malicious_ips'] as $ip => $info) {
    if (!isset($info['geo']['loc'])) continue;

    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'type' => 'attacker',
            'ip' => $ip,
            'name' => "Attacker: $ip",
            'abuseScore' => $info['abuse_score'] ?? 0,
            'reports' => $info['abuse_reports'] ?? 0,
            'country' => $info['geo']['country'] ?? 'Unknown',
            'city' => $info['geo']['city'] ?? 'Unknown',
            'asn' => $info['geo']['asn'] ?? 'Unknown',
            'details' => "Abuse Score: " . ($info['abuse_score'] ?? 0) . "%",
            'is_real_flow' => $info['is_real_flow'] ?? false,
            'is_tor' => $info['is_tor'] ?? false,
            'tor_nickname' => $info['tor_info']['nickname'] ?? null,
            'is_wazuh' => $info['is_wazuh'] ?? false
        ],
        'geometry' => [
            'type' => 'Point',
            'coordinates' => $toCoords($info['geo']['loc'])
        ]
    ];
}

// --- ETAPA 2.5: Tor Exit Nodes (Não necessariamente maliciosos ainda) ---
foreach ($threatData['tor_nodes'] ?? [] as $ip => $info) {
    if (!isset($info['geo']['loc'])) continue;

    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'type' => 'attacker', // Usamos attacker para estilo similar, mas com is_tor=true
            'ip' => $ip,
            'name' => "Tor Exit Node: $ip",
            'abuseScore' => 0,
            'reports' => 0,
            'country' => $info['geo']['country'] ?? 'Unknown',
            'city' => $info['geo']['city'] ?? 'Unknown',
            'asn' => $info['geo']['asn'] ?? 'Unknown',
            'details' => "Tor Relay: " . ($info['nickname'] ?? 'Unnamed'),
            'is_real_flow' => false,
            'is_tor' => true,
            'tor_nickname' => $info['nickname'] ?? null
        ],
        'geometry' => [
            'type' => 'Point',
            'coordinates' => $toCoords($info['geo']['loc'])
        ]
    ];
}

// --- ETAPA 2.7: BGP Peers ---
$targetLoc = null;
foreach ($targetIps as $ip => $info) {
    if (isset($info['geo']['loc'])) {
        $targetLoc = $info['geo']['loc'];
        break;
    }
}

foreach ($threatData['bgp_peers'] ?? [] as $asn => $peer) {
    if (!isset($peer['geo']['loc'])) continue;

    $peerLoc = $peer['geo']['loc'];
    
    // Adicionar Ponto do Peer
    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'type' => 'bgp_peer',
            'asn' => $asn,
            'name' => "BGP Peer: $asn",
            'peer_type' => $peer['type'] ?? 'uncertain',
            'neighbor_of' => $peer['neighbor_of'] ?? 'Unknown',
            'country' => $peer['geo']['country'] ?? 'Unknown',
            'city' => $peer['geo']['city'] ?? 'Unknown',
            'power' => $peer['power'] ?? 1,
            'holder' => $peer['holder'] ?? 'Unknown',
            'ix_count' => $peer['ix_count'] ?? 0,
            'status' => $peer['status'] ?? 'up',
            'is_down' => ($peer['status'] ?? 'up') === 'down'
        ],
        'geometry' => [
            'type' => 'Point',
            'coordinates' => $toCoords($peerLoc)
        ]
    ];

    // Adicionar Linha de Interconexão (do Peer para quem ele é vizinho)
    $neighborAsn = $peer['neighbor_of'] ?? null;
    $neighborLoc = null;

    if ($neighborAsn === 'AS262978' || $neighborAsn === '262978') {
        $neighborLoc = $targetLoc;
    } elseif (isset($threatData['bgp_peers'][$neighborAsn]['geo']['loc'])) {
        $neighborLoc = $threatData['bgp_peers'][$neighborAsn]['geo']['loc'];
    }

    if ($neighborLoc && $peerLoc) {
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'type' => 'bgp_link',
                'from_asn' => $asn,
                'to_asn' => $neighborAsn,
                'peer_type' => $peer['type'] ?? 'uncertain',
                'status' => $peer['status'] ?? 'up',
                'is_down' => ($peer['status'] ?? 'up') === 'down'
            ],
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    $toCoords($peerLoc),
                    $toCoords($neighborLoc)
                ]
            ]
        ];
    }
}

// --- ETAPA 3: Correlação (Attack Lines) ---
// Usamos as correlações já feitas pelo coletor
$addedPoints = [];
if (!empty($threatData['attacks'])) {
    foreach ($threatData['attacks'] as $attack) {
        $attackerIp = $attack['attacker'] ?? null;
        $targetIp = $attack['target'] ?? null;
        if (!$attackerIp || !$targetIp) continue;
        
        $attackerLoc = null;
        if ($attackerIp === 'shodan.io') {
            $attackerLoc = '38.8977,-77.0365'; // Washington DC (Representative)
        } elseif (isset($threatData['malicious_ips'][$attackerIp]['geo']['loc'])) {
            $attackerLoc = $threatData['malicious_ips'][$attackerIp]['geo']['loc'];
        }
        
        $targetLoc = null;
        // 1. Try Shodan Assets
        if (isset($threatData['active_ips'][$targetIp]['geo']['loc'])) {
            $targetLoc = $threatData['active_ips'][$targetIp]['geo']['loc'];
        } elseif (isset($threatData['vulnerable_ips'][$targetIp]['geo']['loc'])) {
            $targetLoc = $threatData['vulnerable_ips'][$targetIp]['geo']['loc'];
        }
        
        // 2. Fallback: Check if target is in our IP blocks
        if (!$targetLoc && $targetIp) {
            foreach ($targetBlocks as $block) {
                if (ip_in_range($targetIp, $block)) {
                    $targetLoc = $threatData['infrastructure'][0]['loc'] ?? '-26.2309,-48.8497';
                    break;
                }
            }
        }

        if ($targetLoc && $attackerLoc) {
            // Add attacker point if not added
            if (!isset($addedPoints[$attackerIp])) {
                $info = $threatData['malicious_ips'][$attackerIp] ?? null;
                if ($info && isset($info['geo']['loc'])) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'type' => 'attacker',
                            'ip' => $attackerIp,
                            'abuseScore' => $info['abuse_score'] ?? 0,
                            'reports' => $info['abuse_reports'] ?? 0,
                            'country' => $info['geo']['country'] ?? 'Unknown',
                            'city' => $info['geo']['city'] ?? 'Unknown',
                            'asn' => $info['geo']['asn'] ?? 'Unknown',
                            'is_sec_logs' => $info['is_sec_logs'] ?? false,
                            'is_shodan' => isset($info['shodan']),
                            'is_abuse' => ($info['abuse_score'] ?? 0) > 0,
                            'is_corgea' => isset($info['corgea']),
                            'corgea' => $info['corgea'] ?? null,
                            'is_tor' => $info['is_tor'] ?? false,
                            'is_elastic' => $info['is_elastic'] ?? false
                        ],
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => $toCoords($info['geo']['loc'])
                        ]
                    ];
                    $addedPoints[$attackerIp] = true;
                }
            }

            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'type' => 'attack',
                    'severity' => $attack['severity'] ?? 'medium',
                    'attacker_ip' => $attackerIp,
                    'target_ip' => $targetIp,
                    'is_real_flow' => $attack['is_real_flow'] ?? false,
                    'is_tor' => $attack['is_tor'] ?? false,
                    'is_sec_logs' => $attack['is_sec_logs'] ?? false,
                    'is_shodan' => $attack['is_shodan'] ?? false,
                    'is_abuse' => $attack['is_abuse'] ?? false,
                    'is_corgea' => $attack['is_corgea'] ?? false,
                    'is_elastic' => $attack['is_elastic'] ?? false,
                    'corgea' => $attack['corgea'] ?? null,
                    'name' => $attack['name'] ?? '',
                    'abuse_score' => $attack['abuse_score'] ?? 0,
                    'cves' => $attack['cves'] ?? []
                ],
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        $toCoords($attackerLoc),
                        $toCoords($targetLoc)
                    ]
                ]
            ];
        }
    }
} else {
    // Fallback: Correlação randômica básica se não houver ataques pré-calculados
    if (!empty($targetIps)) {
        $allTargets = array_keys($targetIps);
        foreach ($threatData['malicious_ips'] as $ip => $info) {
            if (!isset($info['geo']['loc'])) continue;
            $randomTargetIp = $allTargets[array_rand($allTargets)];
            if (isset($targetIps[$randomTargetIp]['geo']['loc'])) {
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'type' => 'attack',
                        'severity' => ($info['abuse_score'] ?? 0) > 80 ? 'high' : 'medium',
                        'attacker_ip' => $ip,
                        'target_ip' => $randomTargetIp,
                        'is_real_flow' => $info['is_real_flow'] ?? false,
                        'is_tor' => $info['is_tor'] ?? false,
                        'is_sec_logs' => $info['is_sec_logs'] ?? false,
                        'is_shodan' => isset($info['shodan']),
                        'is_abuse' => ($info['abuse_score'] ?? 0) > 0,
                        'is_corgea' => isset($info['corgea']),
                        'abuse_score' => $info['abuse_score'] ?? 0,
                        'cves' => array_keys($info['corgea'] ?? [])
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            $toCoords($info['geo']['loc']),
                            $toCoords($targetIps[$randomTargetIp]['geo']['loc'])
                        ]
                    ]
                ];
            }
        }
    }
}

echo json_encode([
    'type' => 'FeatureCollection',
    'features' => $features,
    'stats' => $threatData['stats'],
    'top_stats' => $threatData['top_stats'],
    'security_incidents' => $threatData['security_incidents'] ?? [],
    'security_mitre' => $threatData['security_mitre'] ?? [],
    'security_interfaces' => $threatData['security_interfaces'] ?? [],
    'wazuh_alerts' => $threatData['wazuh_alerts'] ?? []
]);



