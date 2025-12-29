<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

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

render_header('Veeam · Jobs', $user);

$jobs = [];
$error_msg = '';

try {
    $jobs = veeam_get_all_jobs($plugin);
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="plugin_veeam.php" class="btn" style="padding:8px">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
        <div style="font-weight:700;font-size:18px">Jobs de Backup (Consolidado)</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <?php if ($error_msg): ?>
        <span class="badge danger" title="<?= h($error_msg) ?>">Erro de API</span>
      <?php endif; ?>
      <input type="text" placeholder="Filtrar jobs..." class="btn" style="background:var(--input-bg);cursor:text">
    </div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Servidor</th>
        <th>Nome do Job</th>
        <th>Status</th>
        <th>Última Execução</th>
        <th>Próxima Execução</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($jobs)): ?>
        <tr><td colspan="5" class="muted" style="text-align:center">Nenhum job encontrado ou erro na API.</td></tr>
      <?php else: ?>
        <?php foreach ($jobs as $job): 
            $status = $job['LastExecutionStatus'] ?? 'Unknown';
            $statusClass = 'secondary';
            if (stripos($status, 'Success') !== false) $statusClass = 'success';
            if (stripos($status, 'Warning') !== false) $statusClass = 'warning';
            if (stripos($status, 'Failed') !== false) $statusClass = 'danger';
        ?>
          <tr>
            <td><span class="badge secondary"><?= h($job['ServerLabel']) ?></span></td>
            <td><?= h($job['Name']) ?></td>
            <td><span class="badge <?= $statusClass ?>"><?= h($status) ?></span></td>
            <td><?= !empty($job['LastRun']) ? date('d/m/Y H:i', strtotime($job['LastRun'])) : 'N/A' ?></td>
            <td><?= !empty($job['NextRun']) ? date('d/m/Y H:i', strtotime($job['NextRun'])) : 'N/A' ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
render_footer();



