<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}
$plugin = plugin_get_by_name($pdo, 'veeam');

if (!$plugin || !$plugin['is_active']) {
    header('Location: /');
    exit;
}

render_header('Veeam · Repositórios', $user);

$repos = [];
$error_msg = '';

try {
    $repos = veeam_get_all_repositories($plugin);
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="font-weight:700;font-size:18px">Repositórios de Armazenamento (Consolidado)</div>
    <?php if ($error_msg): ?>
        <span class="badge danger" title="<?= h($error_msg) ?>">Erro de API</span>
    <?php endif; ?>
  </div>

  <div class="config-grid">
    <?php if (empty($repos)): ?>
        <div class="muted" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
            Nenhum repositório encontrado ou erro na comunicação com a API Veeam.
        </div>
    <?php else: ?>
        <?php foreach ($repos as $repo): 
            $capacity = $repo['Capacity'] ?? 0;
            $freeSpace = $repo['FreeSpace'] ?? 0;
            $used = $capacity - $freeSpace;
            $percent = $capacity > 0 ? ($used / $capacity) * 100 : 0;
        ?>
            <div class="card" style="border:1px solid var(--border);background:rgba(255,255,255,0.01)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <div style="font-weight:600"><?= h($repo['Name']) ?></div>
                <span class="badge secondary"><?= h($repo['ServerLabel']) ?></span>
              </div>
              <div style="font-size:20px;font-weight:700;margin-bottom:4px">
                <?= formatBytes($used) ?> / <?= formatBytes($capacity) ?>
              </div>
              <div style="width:100%;background:var(--border);height:10px;border-radius:5px;margin-bottom:8px">
                <div style="width:<?= min(100, $percent) ?>%;background:<?= $percent > 90 ? 'var(--danger)' : 'var(--primary)' ?>;height:100%;border-radius:5px"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:12px">
                <span class="muted">Em uso: <?= round($percent, 1) ?>%</span>
                <span style="color:var(--primary)"><?= formatBytes($freeSpace) ?> Livres</span>
              </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php
render_footer();
