<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
/** @var PDO $pdo */

echo "Starting Threat Intelligence Collector...\n";

// Load Plugins
$bgpPlugin = plugin_get_by_name($pdo, 'bgpview');
$shodanPlugin = plugin_get_by_name($pdo, 'shodan');
$abusePlugin = plugin_get_by_name($pdo, 'abuseipdb');
$ipinfoPlugin = plugin_get_by_name($pdo, 'ipinfo');

$shodanToken = $shodanPlugin['config']['password'] ?? '';
$abuseToken = $abusePlugin['config']['password'] ?? '';
$ipinfoToken = $ipinfoPlugin['config']['password'] ?? '';
$ipBlocks = array_filter(array_map('trim', explode(',', $bgpPlugin['config']['ip_blocks'] ?? '')));

if (empty($ipBlocks)) {
    die("No IP blocks configured in BGPView plugin.\n");
}

function get_json_auth($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        echo "CURL Error: $err\n";
        return null;
    }
    if ($httpCode >= 400) {
        echo "HTTP Error: $httpCode for URL: $url\n";
    }
    return json_decode($res, true);
}

function get_geo($ip, $pdo, $token) {
    if (!$ip || $ip === '127.0.0.1') return null;
    $cacheKey = "geo_ipinfo_$ip";
    $stmt = $pdo->prepare("SELECT cache_value FROM plugin_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->execute([$cacheKey]);
    $cached = $stmt->fetch();
    
    if ($cached) return json_decode($cached['cache_value'], true);

    $url = "https://ipinfo.io/$ip" . ($token ? "?token=$token" : "");
    $geo = get_json_auth($url);
    
    if ($geo && isset($geo['loc'])) {
        $stmt = $pdo->prepare("INSERT INTO plugin_cache (cache_key, cache_value, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY)) ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)");
        $stmt->execute([$cacheKey, json_encode($geo)]);
    }
    return $geo;
}

$threatData = [
    'active_ips' => [], // Green
    'vulnerable_ips' => [], // Yellow (Shodan)
    'malicious_ips' => [], // Red (AbuseIPDB)
    'route_traces' => [], // Neon Cyan (BGP Trace)
    'stats' => [
        'total_scanned' => 0,
        'active' => 0,
        'vulnerable' => 0,
        'malicious' => 0
    ]
];

// 1. Scan IP Blocks using Shodan (Discovery & Vulnerability)
if ($shodanToken) {
    echo "Scanning blocks via Shodan...\n";
    foreach ($ipBlocks as $block) {
        $url = "https://api.shodan.io/shodan/host/search?key=$shodanToken&query=net:$block";
        $results = get_json_auth($url);
        
        if (isset($results['matches'])) {
            foreach ($results['matches'] as $match) {
                $ip = $match['ip_str'];
                $geo = get_geo($ip, $pdo, $ipinfoToken);
                
                $hostInfo = [
                    'ip' => $ip,
                    'ports' => $match['port'] ?? [],
                    'org' => $match['org'] ?? '',
                    'vulns' => $match['vulns'] ?? [],
                    'geo' => $geo,
                    'last_update' => $match['timestamp'] ?? date('Y-m-d H:i:s')
                ];

                if (!empty($hostInfo['vulns']) || !empty($hostInfo['ports'])) {
                    $threatData['vulnerable_ips'][$ip] = $hostInfo;
                    $threatData['stats']['vulnerable']++;
                } else {
                    $threatData['active_ips'][$ip] = $hostInfo;
                    $threatData['stats']['active']++;
                }
            }
        }
    }
}

// 2. Fetch ASN Prefixes from BGPView as "Active" (Green) if not already found
$asn = $bgpPlugin['config']['my_asn'] ?? '';
$prefixesFound = [];
if ($asn) {
    echo "Fetching prefixes for $asn...\n";
    $asnDigit = preg_replace('/[^0-9]/', '', $asn);
    $url = "https://api.bgpview.io/asn/$asnDigit/prefixes";
    $res = get_json_auth($url);
    
    if (isset($res['data']['ipv4_prefixes'])) {
        foreach ($res['data']['ipv4_prefixes'] as $p) {
            $prefix = $p['prefix'];
            $prefixesFound[] = $prefix;
            // Use the first IP of the prefix for geolocation
            $ip = explode('/', $prefix)[0];
            
            if (!isset($threatData['active_ips'][$ip]) && !isset($threatData['vulnerable_ips'][$ip])) {
                $geo = get_geo($ip, $pdo, $ipinfoToken);
                $threatData['active_ips'][$ip] = [
                    'ip' => $ip,
                    'prefix' => $prefix,
                    'org' => $p['name'] ?? '',
                    'geo' => $geo,
                    'last_update' => date('Y-m-d H:i:s')
                ];
                $threatData['stats']['active']++;
            }
        }
    }
}

// 3. Trace routes (Route-map simulation) for the 3 configured blocks
if (!empty($ipBlocks)) {
    echo "Tracing routes for configured blocks...\n";
    foreach ($ipBlocks as $block) {
        $ip = explode('/', $block)[0];
        $url = "https://api.bgpview.io/ip/$ip";
        $res = get_json_auth($url);
        
        if (isset($res['data']['prefixes'])) {
            foreach ($res['data']['prefixes'] as $p) {
                // Find peers/upstream/downstream to simulate route tracing
                $routeInfo = [
                    'ip' => $ip,
                    'block' => $block,
                    'asn' => $p['asn']['asn'] ?? '',
                    'name' => $p['asn']['name'] ?? '',
                    'geo' => get_geo($ip, $pdo, $ipinfoToken),
                    'trace' => []
                ];

                // Add peers as trace points
                if (isset($res['data']['rir_allocation']['country_code'])) {
                    $routeInfo['trace'][] = ['name' => 'RIR', 'country' => $res['data']['rir_allocation']['country_code']];
                }

                $threatData['route_traces'][$ip] = $routeInfo;
            }
        }
    }
}

// 4. Check for Malicious Reputation (AbuseIPDB)
// We check the same active/vulnerable IPs found, and also any recent connections if we had flow data.
// For now, we'll check the discovered IPs.
if ($abuseToken && (!empty($threatData['active_ips']) || !empty($threatData['vulnerable_ips']))) {
    echo "Checking reputation via AbuseIPDB...\n";
    $allDiscovered = array_merge(array_keys($threatData['active_ips']), array_keys($threatData['vulnerable_ips']));
    
    // Limit to 50 IPs per cycle to avoid hitting rate limits too hard
    foreach (array_slice($allDiscovered, 0, 50) as $ip) {
        $url = "https://api.abuseipdb.com/api/v2/check?ipAddress=$ip&maxAgeInDays=90";
        $res = get_json_auth($url, ["Key: $abuseToken", "Accept: application/json"]);
        
        if (isset($res['data']['abuseConfidenceScore']) && $res['data']['abuseConfidenceScore'] > 20) {
            $ipData = $threatData['active_ips'][$ip] ?? $threatData['vulnerable_ips'][$ip];
            $ipData['abuse_score'] = $res['data']['abuseConfidenceScore'];
            $ipData['abuse_reports'] = $res['data']['totalReports'];
            
            $threatData['malicious_ips'][$ip] = $ipData;
            $threatData['stats']['malicious']++;
            
            // Remove from other categories if malicious
            unset($threatData['active_ips'][$ip]);
            unset($threatData['vulnerable_ips'][$ip]);
        }
    }
}

// 5. Save to Database
// Using individual records for Flow-First Network Traffic Intelligence compliance
$stmtIntel = $pdo->prepare("INSERT INTO plugin_dflow_threat_intel (indicator, type, category, threat_score, source, last_seen) 
                            VALUES (?, 'ip', ?, ?, ?, NOW()) 
                            ON DUPLICATE KEY UPDATE last_seen = NOW(), threat_score = VALUES(threat_score)");

foreach ($threatData['active_ips'] as $ip => $data) {
    $stmtIntel->execute([$ip, 'Active Discovery', 0, 'BGP Collector']);
}
foreach ($threatData['vulnerable_ips'] as $ip => $data) {
    $stmtIntel->execute([$ip, 'Vulnerable (Shodan)', 50, 'Shodan']);
}
foreach ($threatData['malicious_ips'] as $ip => $data) {
    $stmtIntel->execute([$ip, 'Malicious (AbuseIPDB)', $data['abuse_score'] ?? 100, 'AbuseIPDB']);
}

// Also keep the summary blob if needed by legacy views, but update table name to be compliant if it exists
// For now, we prioritize the dflow_threat_intel table.

echo "Threat Intelligence Collection completed.\n";
echo "Stats: Active: {$threatData['stats']['active']}, Vulnerable: {$threatData['stats']['vulnerable']}, Malicious: {$threatData['stats']['malicious']}\n";
