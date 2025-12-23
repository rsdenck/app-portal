<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}
$plugin = plugin_get_by_name($pdo, 'vcenter');

if (!$plugin || !$plugin['is_active']) {
    header('Location: /');
    exit;
}

render_header('vCenter · Consolidação', $user);

// READER MODE: Fetch from local database instead of direct API
$localData = vcenter_get_local_data($pdo);
$serviceStatus = vcenter_get_local_data_status($pdo);

if (!$localData) {
    echo '<div class="card" style="padding:40px; text-align:center; margin-top:20px">';
    echo '  <h3 class="muted">Aguardando Coleta de Dados</h3>';
    echo '  <p>O vCenter Collector ainda não realizou a primeira coleta. Por favor, aguarde alguns minutos.</p>';
    echo '  <div style="margin-top:20px"><div class="badge warning">Status: ' . h($serviceStatus['status'] ?? 'UNKNOWN') . '</div></div>';
    echo '</div>';
    render_footer();
    exit;
}

$stats = $localData['stats'] ?? [];
$vms = $localData['vms'] ?? [];
$hosts = $localData['hosts'] ?? [];
$error_msgs = $localData['errors'] ?? [];
$lastUpdate = $localData['last_update'] ?? null;

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
    <h2 style="margin:0; display:flex; align-items:center; gap:10px">
        <svg class="sidebar-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 8l5 3 5-3-5-3-5 3z"/><path d="M7 12l5 3 5-3"/></svg>
        vCenter Consolidação
    </h2>
    <div style="display:flex; align-items:center; gap:15px">
        <?php if ($serviceStatus): ?>
            <div style="display:flex; align-items:center; gap:8px; font-size:11px; padding:5px 12px; border-radius:20px; background:<?= 
                $serviceStatus['status'] === 'UP' ? 'rgba(46, 204, 113, 0.1)' : (
                $serviceStatus['status'] === 'WARNING' ? 'rgba(241, 196, 15, 0.1)' : 'rgba(231, 76, 60, 0.1)') ?>; color:<?= 
                $serviceStatus['status'] === 'UP' ? '#2ecc71' : (
                $serviceStatus['status'] === 'WARNING' ? '#f1c40f' : '#e74c3c') ?>; border:1px solid currentColor">
                <span style="width:8px; height:8px; border-radius:50%; background:currentColor"></span>
                <strong>COLETOR: <?= $serviceStatus['status'] ?></strong>
                <?php if ($lastUpdate): ?>
                    <span style="opacity:0.7; margin-left:5px">v<?= date('H:i:s', strtotime($lastUpdate)) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="badge primary"><?= $stats['server_count'] ?? 0 ?> Servidores</div>
        <?php if (!empty($error_msgs)): ?>
            <div class="badge danger" title="<?= h(implode("\n", $error_msgs)) ?>"><?= count($error_msgs) ?> Erros</div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Summary -->
<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:20px">
    <div class="card" style="padding:15px; border-left:4px solid var(--primary)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Total de VMs</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_vms'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid #2ecc71">
        <div class="muted" style="font-size:11px; text-transform:uppercase">VMs Ligadas</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['running_vms'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid var(--warning)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Total de Hosts</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_hosts'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid var(--danger)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Datacenters</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_datacenters'] ?? 0) ?></div>
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card">
            <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px">Máquinas Virtuais (Top 10)</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Servidor</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th>vCPU</th>
                        <th>Memória (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vms)): ?>
                        <tr><td colspan="5" class="muted" style="text-align:center">Nenhuma VM encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($vms, 0, 10) as $vm): ?>
                            <tr>
                                <td><span class="badge secondary"><?= h($vm['server_label']) ?></span></td>
                                <td><?= h($vm['name']) ?></td>
                                <td>
                                    <span class="badge <?= $vm['power_state'] === 'POWERED_ON' ? 'success' : 'secondary' ?>">
                                        <?= h($vm['power_state']) ?>
                                    </span>
                                </td>
                                <td><?= (int)($vm['cpu_count'] ?? 0) ?></td>
                                <td><?= number_format($vm['memory_size_MiB'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="col">
        <div class="card">
            <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px">Hosts ESXi</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Servidor</th>
                        <th>Host</th>
                        <th>Status Conexão</th>
                        <th>Power</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hosts)): ?>
                        <tr><td colspan="4" class="muted" style="text-align:center">Nenhum host encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($hosts, 0, 10) as $host): ?>
                            <tr>
                                <td><span class="badge secondary"><?= h($host['server_label']) ?></span></td>
                                <td><?= h($host['name']) ?></td>
                                <td>
                                    <span class="badge <?= $host['connection_state'] === 'CONNECTED' ? 'success' : 'danger' ?>">
                                        <?= h($host['connection_state']) ?>
                                    </span>
                                </td>
                                <td><?= h($host['power_state']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
