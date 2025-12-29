<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/dflow_deep_lib.php';
/** @var PDO $pdo */

/**
 * DFlow Deep Analyzer - CLI Wrapper
 */

// Check if run from command line with arguments
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $targetIp = $argv[1];
    $duration = isset($argv[2]) ? (int)$argv[2] : 10;
    
    echo "Starting Deep Analysis for $targetIp ($duration seconds)...\n";
    $result = deep_analyze_ip($pdo, $targetIp, $duration);
    
    if ($result['success']) {
        echo "Deep Analysis completed for $targetIp. {$result['count']} insights stored in database.\n";
    } else {
        echo "ERROR: {$result['error']}\n";
    }
} else {
    echo "Usage: php dflow_deep_analyzer.php <IP> [duration]\n";
}
