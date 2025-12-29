<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ipinfo_api.php';
require __DIR__ . '/../includes/abuseipdb_api.php';
require __DIR__ . '/../includes/shodan_api.php';
/** @var PDO $pdo */

if (php_sapi_name() !== 'cli') {
    $user = require_login('atendente');
}

// Get plugins config
$ipinfoPlugin = plugin_get_by_name($pdo, 'ipinfo');
$abusePlugin = plugin_get_by_name($pdo, 'abuseipdb');
$shodanPlugin = plugin_get_by_name($pdo, 'shodan');

$hosts = $pdo->query("SELECT h.*, r.lat, r.lon, r.city, r.country 
                    FROM plugin_dflow_hosts h 
                    LEFT JOIN plugin_dflow_recon r ON h.ip_address = r.ip")->fetchAll(PDO::FETCH_ASSOC);
$results = [];

foreach ($hosts as $host) {
    $ip = $host['ip_address'];
    
    // 1. Ensure Geo-coordinates
    if (empty($host['lat']) || empty($host['lon'])) {
        $cacheKey = "geo_$ip";
        $geo = plugin_cache_get($pdo, $cacheKey);
        
        if (!$geo && $ipinfoPlugin && $ipinfoPlugin['is_active']) {
            $client = ipinfo_get_client($ipinfoPlugin['config']);
            $details = $client->getDetails($ip);
            if ($details && !empty($details['loc'])) {
                list($lat, $lon) = explode(',', $details['loc']);
                $geo = [
                    'lat' => (float)$lat,
                    'lon' => (float)$lon,
                    'city' => $details['city'] ?? '',
                    'country' => $details['country'] ?? ''
                ];
                plugin_cache_set($pdo, $cacheKey, $geo, 86400 * 7); // Cache geo for 7 days
                
                // Update recon table for future use
                $stmt = $pdo->prepare("INSERT INTO plugin_dflow_recon (ip, lat, lon, city, country) 
                                       VALUES (?, ?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE lat=VALUES(lat), lon=VALUES(lon), city=VALUES(city), country=VALUES(country)");
                $stmt->execute([$ip, $geo['lat'], $geo['lon'], $geo['city'], $geo['country']]);
            }
        }
        
        if ($geo) {
            $host['lat'] = $geo['lat'];
            $host['lon'] = $geo['lon'];
            $host['city'] = $geo['city'];
            $host['country'] = $geo['country'];
        }
    }

    if (empty($host['lat'])) continue; // Skip if no geo data available

    // 2. Fetch Threat Intel (via existing intel endpoint logic but direct to avoid HTTP overhead)
    $intelCacheKey = "intel_$ip";
    $intel = plugin_cache_get($pdo, $intelCacheKey);
    
    if (!$intel) {
        $intel = ['abuse' => null, 'shodan' => null];
        
        // AbuseIPDB
        if ($abusePlugin && $abusePlugin['is_active'] && !empty($abusePlugin['config']['password'])) {
            $client = abuseipdb_get_client($abusePlugin['config']);
            $data = $client->checkIp($ip);
            if (isset($data['data'])) {
                $intel['abuse'] = $data['data'];
            }
        }

        // Shodan
        if ($shodanPlugin && $shodanPlugin['is_active'] && !empty($shodanPlugin['config']['password'])) {
            $client = shodan_get_client($shodanPlugin['config']);
            $data = $client->getHost($ip);
            if (isset($data['ports'])) {
                $intel['shodan'] = ['ports' => $data['ports'], 'os' => $data['os'] ?? null];
            }
        }
        
        plugin_cache_set($pdo, $intelCacheKey, $intel, 86400);
    }

    // 3. Determine Risk Color & Type
    $riskScore = (int)($intel['abuse']['abuseConfidenceScore'] ?? 0);
    $shodanPorts = $intel['shodan']['ports'] ?? [];
    
    // 4. Get latest flow activity for this IP to identify protocols/traffic
    $stmtFlow = $pdo->prepare("SELECT proto, dst_port, app_proto, SUM(bytes) as total_bytes 
                               FROM plugin_dflow_flows 
                               WHERE src_ip = ? OR dst_ip = ? 
                               GROUP BY proto, dst_port, app_proto 
                               ORDER BY total_bytes DESC LIMIT 3");
    $stmtFlow->execute([$ip, $ip]);
    $topFlows = $stmtFlow->fetchAll(PDO::FETCH_ASSOC);
    
    $trafficTypes = [];
    foreach ($topFlows as $f) {
        $trafficTypes[] = ($f['app_proto'] ?: $f['proto']) . ":" . $f['dst_port'];
    }
    
    $color = '#27c4a8'; // Clean Green
    $threatType = 'Clean / Trustworthy';
    
    if ($riskScore > 80) {
        $color = '#ff4d4f'; // Malicious Red
        $threatType = 'High Risk: Malicious Activity';
    } elseif ($riskScore > 40) {
        $color = '#ffa940'; // Suspicious Orange
        $threatType = 'Suspicious: Potential Threat';
    } elseif (!empty($shodanPorts)) {
        $color = '#ffec3d'; // Vulnerable Yellow
        $threatType = 'Vulnerable: Open Ports Detected (' . implode(', ', array_slice($shodanPorts, 0, 3)) . ')';
    }

    $results[] = [
        'ip' => $ip,
        'lat' => (float)$host['lat'],
        'lng' => (float)$host['lon'],
        'city' => $host['city'] ?? 'Unknown',
        'country' => $host['country'] ?? 'Unknown',
        'color' => $color,
        'threat' => $threatType,
        'score' => $riskScore,
        'ports' => $shodanPorts,
        'traffic' => $trafficTypes,
        'last_seen' => $host['last_seen']
    ];
}

header('Content-Type: application/json');
echo json_encode($results);
