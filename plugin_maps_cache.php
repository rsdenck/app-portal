<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ip = $input['ip'] ?? '';
    $data = $input['data'] ?? null;
    
    if ($ip && $data) {
        plugin_cache_set($pdo, "geo_$ip", $data, 86400 * 30); // 30 days cache for GeoIP
        echo json_encode(['success' => true]);
    } elseif ($asn && $data) {
        plugin_cache_set($pdo, "asn_$asn", $data, 86400 * 7); // 7 days cache for ASN
        echo json_encode(['success' => true]);
    }
    exit;
}

$ip = $_GET['ip'] ?? '';
$asn = $_GET['asn'] ?? '';

if ($ip) {
    $cached = plugin_cache_get($pdo, "geo_$ip");
    if ($cached) {
        echo json_encode($cached);
    } else {
        echo json_encode(['error' => 'not_found']);
    }
    exit;
}

if ($asn) {
    $cached = plugin_cache_get($pdo, "asn_$asn");
    if ($cached) {
        echo json_encode($cached);
    } else {
        echo json_encode(['error' => 'not_found']);
    }
    exit;
}
