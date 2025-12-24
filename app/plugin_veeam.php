<?php

require __DIR__ . '/../includes/bootstrap.php';

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

render_header('Veeam · Dashboard', $user);

$stats = veeam_get_consolidated_stats($plugin);
$repos = veeam_get_all_repositories($plugin);
// For the dashboard, we only show 10 jobs anyway, so limit the fetch to avoid performance issues
$jobs = veeam_get_all_jobs($plugin, 50);
$error_msgs = $stats['Errors'] ?? [];

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
    <h2 style="margin:0; display:flex; align-items:center; gap:10px">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#27c4a8" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        Veeam Consolidação (VBR & VCSP)
    </h2>
    <div style="display:flex; align-items:center; gap:15px">
        <div class="badge primary"><?= $stats['ServerCount'] ?> Servidores Conectados</div>
        <?php if (!empty($error_msgs)): ?>
            <div class="badge danger" title="<?= h(implode("\n", $error_msgs)) ?>"><?= count($error_msgs) ?> Erros de Conexão</div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Summary -->
<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:20px">
    <div class="card" style="padding:15px; border-left:4px solid var(--primary)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Total de Jobs</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['TotalJobCount'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid #2ecc71">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Sucesso</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['SuccessfulJobCount'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid var(--warning)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Avisos</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['WarningJobCount'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid var(--danger)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Falhas</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['FailedJobCount'] ?? 0) ?></div>
    </div>
</div>

<!-- Charts Section -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px">
    <div class="card" style="height:300px">
        <div style="font-weight:700; margin-bottom:15px; font-size:14px; color:var(--primary)">DISTRIBUIÇÃO DE STATUS (JOBS)</div>
        <div style="height:220px; display:flex; justify-content:center">
            <canvas id="jobsChart"></canvas>
        </div>
    </div>
    <div class="card" style="height:300px">
        <div style="font-weight:700; margin-bottom:15px; font-size:14px; color:var(--primary)">SAÚDE DO AMBIENTE (REPOSITÓRIOS)</div>
        <div style="height:220px; display:flex; justify-content:center">
            <canvas id="healthChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Jobs Distribution Chart
    new Chart(document.getElementById('jobsChart'), {
        type: 'doughnut',
        data: {
            labels: ['Sucesso', 'Aviso', 'Falha', 'Em Execução'],
            datasets: [{
                data: [
                    <?= (int)($stats['SuccessfulJobCount'] ?? 0) ?>, 
                    <?= (int)($stats['WarningJobCount'] ?? 0) ?>, 
                    <?= (int)($stats['FailedJobCount'] ?? 0) ?>,
                    <?= (int)($stats['RunningJobCount'] ?? 0) ?>
                ],
                backgroundColor: ['#2ecc71', '#f1c40f', '#e74c3c', '#3498db'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { color: '#aaa', font: { size: 10 } } }
            },
            cutout: '70%'
        }
    });

    // Storage Health Chart
    <?php 
        $totalCap = 0;
        $totalFree = 0;
        foreach($repos as $r) {
            $totalCap += ($r['Capacity'] ?? 0);
            $totalFree += ($r['FreeSpace'] ?? 0);
        }
        $totalUsed = $totalCap - $totalFree;
    ?>
    new Chart(document.getElementById('healthChart'), {
        type: 'pie',
        data: {
            labels: ['Espaço Usado', 'Espaço Livre'],
            datasets: [{
                data: [<?= $totalUsed ?>, <?= $totalFree ?>],
                backgroundColor: ['#e74c3c', '#2ecc71'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { color: '#aaa', font: { size: 10 } } }
            }
        }
    });
});
</script>

<div class="row">
  <div class="col">
    <div class="card">
      <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px; display:flex; justify-content:space-between; align-items:center">
        <div style="display:flex; align-items:center; gap:10px">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <span>Status dos Jobs (Consolidado)</span>
        </div>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th>Servidor</th>
            <th>Job Name</th>
            <th>Status</th>
            <th>Última Execução</th>
            <th>Próxima Execução</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($jobs)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center">Nenhum job encontrado.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($jobs, 0, 10) as $job): 
                $status = $job['LastExecutionStatus'] ?? 'Unknown';
                $badgeClass = 'secondary';
                if (stripos($status, 'Success') !== false) $badgeClass = 'success';
                if (stripos($status, 'Warning') !== false) $badgeClass = 'warning';
                if (stripos($status, 'Failed') !== false) $badgeClass = 'danger';
            ?>
              <tr>
                <td><span class="badge secondary"><?= h($job['ServerLabel']) ?></span></td>
                <td><?= h($job['Name'] ?? 'N/A') ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= h($status) ?></span></td>
                <td><?= !empty($job['LastRun']) ? date('d/m/Y H:i', strtotime($job['LastRun'])) : 'N/A' ?></td>
                <td><?= !empty($job['NextRun']) ? date('d/m/Y H:i', strtotime($job['NextRun'])) : 'N/A' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (count($jobs) > 10): ?>
                <tr><td colspan="5" style="text-align:center"><a href="plugin_veeam_jobs.php" class="btn small secondary">Ver todos os <?= count($jobs) ?> jobs</a></td></tr>
            <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="row" style="margin-top:20px">
  <div class="col">
    <div class="card">
      <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px">Repositórios de Armazenamento Consolidado</div>
      <table class="table">
        <thead>
          <tr>
            <th>Servidor</th>
            <th>Nome</th>
            <th>Capacidade</th>
            <th>Livre</th>
            <th>Uso</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($repos)): ?>
            <tr><td colspan="5" class="muted" style="text-align:center">Nenhum repositório encontrado.</td></tr>
          <?php else: ?>
            <?php foreach ($repos as $repo): 
                $used = ($repo['Capacity'] ?? 0) - ($repo['FreeSpace'] ?? 0);
                $percent = ($repo['Capacity'] ?? 0) > 0 ? ($used / $repo['Capacity']) * 100 : 0;
            ?>
              <tr>
                <td><span class="badge secondary"><?= h($repo['ServerLabel']) ?></span></td>
                <td><?= h($repo['Name'] ?? 'N/A') ?></td>
                <td><?= formatBytes($repo['Capacity'] ?? 0) ?></td>
                <td><?= formatBytes($repo['FreeSpace'] ?? 0) ?></td>
                <td>
                  <div style="width:100%;background:var(--border);height:8px;border-radius:4px" title="<?= round($percent, 1) ?>%">
                    <div style="width:<?= min(100, $percent) ?>%;background:<?= $percent > 90 ? 'var(--danger)' : 'var(--primary)' ?>;height:100%;border-radius:4px"></div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
.no-hover {
  cursor: default !important;
}
.no-hover:hover {
  border-color: var(--border) !important;
  box-shadow: none !important;
}
.plugin-category {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: var(--border);
  padding: 4px 10px;
  border-radius: 6px;
  color: var(--muted);
  font-weight: 600;
}
</style>

<?php
render_footer();



