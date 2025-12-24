<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}

// Force Refresh Logic
if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    $collectorScript = __DIR__ . '/scripts/vcenter_collector.php';
    $phpBinary = 'C:\php\php.exe'; // As seen in previous tool calls
    
    // Run collector in background or wait? Better to run and redirect.
    // On Windows, we can use start /B to run in background if it takes too long, 
    // but here we might want to wait a bit or just trigger it.
    exec("$phpBinary $collectorScript > nul 2>&1");
    
    header('Location: plugin_vcenter.php?refreshed=1');
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
$datastores = $localData['datastores'] ?? [];
$error_msgs = $localData['errors'] ?? [];
$lastUpdate = $localData['last_update'] ?? null;

// Sort VMs by Memory descending for "Top" logic
usort($vms, function($a, $b) {
    return ($b['memory_size_MiB'] ?? 0) <=> ($a['memory_size_MiB'] ?? 0);
});

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .search-input {
        padding: 5px 10px;
        border: 1px solid var(--border);
        border-radius: 4px 0 0 4px;
        background: var(--card-bg);
        color: var(--text);
        font-size: 12px;
        width: 160px;
        border-right: none;
    }
    .search-btn {
        padding: 5px 12px;
        background: var(--primary);
        color: white;
        border: 1px solid var(--primary);
        border-radius: 0 4px 4px 0;
        cursor: pointer;
        font-size: 11px;
        font-weight: 600;
    }
    .search-btn:hover { opacity: 0.9; }
    .refresh-btn {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        background: var(--primary);
        color: white;
        border-radius: 4px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: opacity 0.2s;
    }
    .refresh-btn:hover { opacity: 0.9; color: white; }
    .table-container {
        max-height: 500px;
        overflow-y: auto;
    }
    /* Hide table rows by default */
    #vmTable tbody tr, #hostTable tbody tr, #dsTable tbody tr {
        display: none !important;
    }
    /* Show rows when search is active and matches */
    #vmTable tbody tr.show-row, #hostTable tbody tr.show-row, #dsTable tbody tr.show-row {
        display: table-row !important;
    }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
    <h2 style="margin:0; display:flex; align-items:center; gap:10px">
        <svg class="sidebar-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 8l5 3 5-3-5-3-5 3z"/><path d="M7 12l5 3 5-3"/></svg>
        vCenter
    </h2>
    <div style="display:flex; align-items:center; gap:15px">
        <?php if (isset($_GET['refreshed'])): ?>
            <div id="refresh-success" class="badge success">Atualizado com sucesso!</div>
            <script>setTimeout(() => document.getElementById('refresh-success')?.remove(), 3000);</script>
        <?php endif; ?>

        <a href="?refresh=1" class="refresh-btn" onclick="this.innerHTML='Atualizando...'; this.style.pointerEvents='none';">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
            Atualizar Agora
        </a>

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
<div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:15px; margin-bottom:20px">
    <div class="card" style="padding:15px; border-left:4px solid var(--primary)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Total de VMs</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_vms'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid #2ecc71">
        <div class="muted" style="font-size:11px; text-transform:uppercase">VMs Ligadas</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['running_vms'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid #f39c12">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Total de Hosts</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_hosts'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid #9b59b6">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Datastores</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_datastores'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:15px; border-left:4px solid var(--danger)">
        <div class="muted" style="font-size:11px; text-transform:uppercase">Datacenters</div>
        <div style="font-size:24px; font-weight:700; margin-top:5px"><?= (int)($stats['total_datacenters'] ?? 0) ?></div>
    </div>
</div>

<div style="display:flex; flex-direction:column; gap:25px">
    <!-- 1. Hosts ESXi (Prioridade Máxima) -->
    <div class="card" style="margin:0">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px">
            <div style="font-weight:700; display:flex; align-items:center; gap:8px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6" y2="6"/><line x1="6" y1="18" x2="6" y2="18"/></svg>
                Hosts ESXi
            </div>
            <div style="display:flex">
                <input type="text" id="hostSearch" class="search-input" placeholder="Filtrar Hosts..." onkeydown="if(event.key==='Enter') filterTable('hostTable', this.value)">
                <button class="search-btn" onclick="filterTable('hostTable', document.getElementById('hostSearch').value)">Buscar</button>
            </div>
        </div>
        <div class="table-container" style="max-height:400px">
            <table class="table" id="hostTable">
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
                        <?php foreach ($hosts as $host): ?>
                            <tr>
                                <td><span class="badge" style="font-size:10px; opacity:0.7"><?= h($host['server_label']) ?></span></td>
                                <td style="font-weight:600"><?= h($host['name']) ?></td>
                                <td>
                                    <span class="badge <?= $host['connection_state'] === 'CONNECTED' ? 'success' : 'warning' ?>" style="font-size:10px">
                                        <?= h($host['connection_state']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $host['power_state'] === 'POWEREDON' ? 'success' : 'danger' ?>" style="font-size:10px">
                                        <?= h($host['power_state']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. Datastores (Capacidade) -->
    <div class="card" style="margin:0">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px">
            <div style="font-weight:700; display:flex; align-items:center; gap:8px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Datastores
            </div>
            <div style="display:flex">
                <input type="text" id="dsSearch" class="search-input" placeholder="Filtrar Datastores..." onkeydown="if(event.key==='Enter') filterTable('dsTable', this.value)">
                <button class="search-btn" onclick="filterTable('dsTable', document.getElementById('dsSearch').value)">Buscar</button>
            </div>
        </div>
        <div class="table-container" style="max-height:400px">
            <table class="table" id="dsTable">
                <thead>
                    <tr>
                        <th>Servidor</th>
                        <th>Nome</th>
                        <th>Capacidade</th>
                        <th>Livre</th>
                        <th>% Livre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($datastores)): ?>
                        <tr><td colspan="5" class="muted" style="text-align:center">Nenhum datastore encontrado.</td></tr>
                    <?php else: ?>
                        <?php 
                        foreach ($datastores as $ds): 
                            $capGB = $ds['capacity'] / (1024**3);
                            $freeGB = $ds['free_space'] / (1024**3);
                            $pctFree = $ds['capacity'] > 0 ? ($freeGB / $capGB) * 100 : 0;
                            
                            $capText = $capGB >= 1024 ? number_format($capGB/1024, 2, ',', '.') . ' TB' : number_format($capGB, 1, ',', '.') . ' GB';
                            $freeText = $freeGB >= 1024 ? number_format($freeGB/1024, 2, ',', '.') . ' TB' : number_format($freeGB, 1, ',', '.') . ' GB';
                        ?>
                            <tr>
                                <td><span class="badge" style="font-size:10px; opacity:0.7"><?= h($ds['server_label']) ?></span></td>
                                <td style="font-weight:600"><?= h($ds['name']) ?></td>
                                <td><?= $capText ?></td>
                                <td><?= $freeText ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px">
                                        <div style="flex:1; height:8px; background:rgba(255,255,255,0.05); border-radius:4px; overflow:hidden; min-width:150px; border:1px solid rgba(255,255,255,0.1)">
                                            <div style="height:100%; width:<?= 100 - $pctFree ?>%; background:<?= $pctFree < 10 ? '#e74c3c' : ($pctFree < 20 ? '#f39c12' : '#2ecc71') ?>; box-shadow: 0 0 10px <?= $pctFree < 10 ? 'rgba(231, 76, 60, 0.5)' : ($pctFree < 20 ? 'rgba(243, 156, 18, 0.5)' : 'rgba(46, 204, 113, 0.5)') ?>"></div>
                                        </div>
                                        <span style="font-size:12px; font-weight:700; min-width:40px; color:<?= $pctFree < 10 ? '#e74c3c' : ($pctFree < 20 ? '#f39c12' : '#2ecc71') ?>"><?= number_format($pctFree, 0) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Máquinas Virtuais -->
    <div class="card" style="margin:0">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px">
            <div style="font-weight:700; display:flex; align-items:center; gap:8px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Máquinas Virtuais
            </div>
            <div style="display:flex">
                <input type="text" id="vmSearch" class="search-input" placeholder="Filtrar VMs..." onkeydown="if(event.key==='Enter') filterTable('vmTable', this.value)">
                <button class="search-btn" onclick="filterTable('vmTable', document.getElementById('vmSearch').value)">Buscar</button>
            </div>
        </div>
        <div class="table-container" style="max-height:400px">
            <table class="table" id="vmTable">
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
                        <?php foreach ($vms as $vm): ?>
                            <tr>
                                <td><span class="badge" style="font-size:10px; opacity:0.7"><?= h($vm['server_label']) ?></span></td>
                                <td style="font-weight:600"><?= h($vm['name']) ?></td>
                                <td>
                                    <span class="badge <?= $vm['power_state'] === 'POWERED_ON' ? 'success' : 'danger' ?>" style="font-size:10px">
                                        <?= h($vm['power_state']) ?>
                                    </span>
                                </td>
                                <td><?= (int)$vm['cpu_count'] ?></td>
                                <td><?= number_format($vm['memory_size_MiB'] / 1, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterTable(tableId, query) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    const searchTerm = query.toLowerCase().trim();

    rows.forEach(row => {
        if (searchTerm === "") {
            row.classList.remove('show-row');
        } else {
            // Get text from all cells except the first one (if you want to exclude server label)
            // But for now, let's search everything for maximum flexibility
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.classList.add('show-row');
            } else {
                row.classList.remove('show-row');
            }
        }
    });
}
</script>

<?php render_footer(); ?>



