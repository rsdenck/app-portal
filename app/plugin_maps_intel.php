<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
$user = require_login();

$ip = $_GET['ip'] ?? '';
if (!$ip) {
    echo json_encode(['error' => 'No IP provided']);
    exit;
}

// Try cache first
$cacheKey = "intel_$ip";
$cached = plugin_cache_get($pdo, $cacheKey);
if ($cached) {
    echo json_encode($cached);
    exit;
}

$results = ['abuse' => null, 'shodan' => null];

// AbuseIPDB
$abusePlugin = plugin_get_by_name($pdo, 'abuseipdb');
if ($abusePlugin && $abusePlugin['is_active'] && !empty($abusePlugin['config']['password'])) {
    $apiKey = $abusePlugin['config']['password'];
    $ch = curl_init("https://api.abuseipdb.com/api/v2/check?ipAddress=" . urlencode($ip));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Key: $apiKey", "Accept: application/json"]);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    if (isset($data['data'])) {
        $results['abuse'] = $data['data'];
    }
}

// Shodan
$shodanPlugin = plugin_get_by_name($pdo, 'shodan');
if ($shodanPlugin && $shodanPlugin['is_active'] && !empty($shodanPlugin['config']['password'])) {
    $apiKey = $shodanPlugin['config']['password'];
    $ch = curl_init("https://api.shodan.io/shodan/host/" . urlencode($ip) . "?key=" . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    if (isset($data['ports'])) {
        $results['shodan'] = ['ports' => $data['ports'], 'os' => $data['os'] ?? null];
    }
}

// Cache results for 24 hours
plugin_cache_set($pdo, $cacheKey, $results, 86400);

echo json_encode($results);



