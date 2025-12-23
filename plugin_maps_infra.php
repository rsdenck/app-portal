<?php
require __DIR__ . '/includes/bootstrap.php';
// nsx_api.php is already included in bootstrap.php
require __DIR__ . '/includes/faz_api.php';

$user = require_login();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'summary';

if ($action === 'summary') {
    // Try cache first
    $cacheKey = "infra_summary";
    $cached = plugin_cache_get($pdo, $cacheKey);
    if ($cached) {
        echo json_encode($cached);
        exit;
    }

    $results = [
        'nsx' => nsx_get_aggregated_stats($pdo),
        'faz' => faz_get_aggregated_stats($pdo),
        'timestamp' => time()
    ];

    // Cache for 5 minutes
    plugin_cache_set($pdo, $cacheKey, $results, 300);
    echo json_encode($results);
    exit;
}

if ($action === 'nodes') {
    $nsxStats = nsx_get_aggregated_stats($pdo);
    echo json_encode($nsxStats['nodes'] ?? []);
    exit;
}
