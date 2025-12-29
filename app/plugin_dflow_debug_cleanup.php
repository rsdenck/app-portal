<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$tables = [
    'plugin_dflow_flows',
    'plugin_dflow_hosts',
    'plugin_dflow_topology',
    'plugin_dflow_system_metrics',
    'plugin_dflow_blocked_ips',
    'plugin_dflow_bgp_prefixes',
    'plugin_dflow_threat_intel',
    'plugin_dflow_alerts'
];

$results = [];
foreach ($tables as $table) {
    try {
        $pdo->exec("TRUNCATE TABLE $table");
        $results[] = "Table $table truncated successfully.";
    } catch (PDOException $e) {
        $results[] = "Error truncating $table: " . $e->getMessage();
    }
}

render_header('DFlow Cleanup', $user);
?>

<div class="card">
    <h3>DFlow Data Cleanup</h3>
    <ul>
        <?php foreach ($results as $res): ?>
            <li><?= h($res) ?></li>
        <?php endforeach; ?>
    </ul>
    <p>All temporary/fake data has been cleared. The engine should now populate these tables with real data.</p>
    <a href="/app/plugin_dflow.php" class="btn btn-primary">Back to Dashboard</a>
</div>
