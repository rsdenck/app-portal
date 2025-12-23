<?php

require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$esPlugin = plugin_get_by_name($pdo, 'elasticsearch');
if (!$esPlugin || !$esPlugin['is_active']) {
    header('Location: /atendente_plugins.php');
    exit;
}

$health = null;
$error = '';
try {
    $client = elastic_get_client($esPlugin['config']);
    $health = $client->health();
} catch (Exception $e) {
    $error = $e->getMessage();
}

render_header('Elasticsearch Explorer', $user);
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <h2 style="margin:0">Elasticsearch Status</h2>
        <div class="badge" style="background: <?= ($health['status'] ?? '') === 'green' ? '#27c4a8' : (($health['status'] ?? '') === 'yellow' ? '#f1c40f' : '#e74c3c') ?>">
            <?= h(strtoupper($health['status'] ?? 'UNKNOWN')) ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col">
            <div class="stat-card">
                <div class="stat-label">Cluster Name</div>
                <div class="stat-value"><?= h($health['cluster_name'] ?? '---') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="stat-card">
                <div class="stat-label">Nodes</div>
                <div class="stat-value"><?= h($health['number_of_nodes'] ?? '0') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="stat-card">
                <div class="stat-label">Active Shards</div>
                <div class="stat-value"><?= h($health['active_primary_shards'] ?? '0') ?></div>
            </div>
        </div>
    </div>

    <div style="margin-top:30px">
        <h3>Sample Search</h3>
        <form method="get" style="display:flex; gap:10px; margin-bottom:20px">
            <input type="text" name="index" placeholder="Index name (e.g. logs-*)" value="<?= h($_GET['index'] ?? '*') ?>" style="flex:1">
            <input type="text" name="q" placeholder="Search query..." value="<?= h($_GET['q'] ?? '') ?>" style="flex:2">
            <button class="btn primary">Search</button>
        </form>

        <?php
        if (!empty($_GET['index'])) {
            $query = [
                'size' => 10,
                'query' => [
                    'multi_match' => [
                        'query' => $_GET['q'] ?? '',
                        'fields' => ['*']
                    ]
                ]
            ];
            if (empty($_GET['q'])) {
                $query['query'] = ['match_all' => (object)[]];
            }

            try {
                $results = $client->query($_GET['index'], $query);
                if (isset($results['hits']['hits'])) {
                    echo '<div class="table-container"><table>';
                    echo '<thead><tr><th>ID</th><th>Source</th></tr></thead><tbody>';
                    foreach ($results['hits']['hits'] as $hit) {
                        echo '<tr>';
                        echo '<td>' . h($hit['_id']) . '</td>';
                        echo '<td><pre style="font-size:10px">' . h(json_encode($hit['_source'], JSON_PRETTY_PRINT)) . '</pre></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="muted">No results found or error in query.</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">' . h($e->getMessage()) . '</div>';
            }
        }
        ?>
    </div>
</div>

<style>
.stat-card {
    background: rgba(255,255,255,0.05);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; margin-bottom: 5px; }
.stat-value { font-size: 18px; font-weight: bold; }
</style>

<?php
render_footer();
