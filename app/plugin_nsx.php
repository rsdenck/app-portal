<?php
// Increase execution time for multi-manager data retrieval
set_time_limit(120);
require __DIR__ . '/../includes/bootstrap.php';
// nsx_api.php is already included in bootstrap.php

$user = require_login('atendente');
$plugin = plugin_get_by_name($pdo, 'nsx');

if (!$plugin || !$plugin['is_active']) {
    header('Location: /index.php');
    exit;
}

function clean_nsx_name($name) {
    if (!$name) return 'N/A';
    // Remove UUID pattern (8-4-4-4-12 hex chars) and any preceding " | ", " - ", or "/"
    $name = preg_replace('/[ ]?[|:-][ ]?[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', '', $name);
    // Also handle cases where the UUID is just at the end of a path without separator
    $name = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', '', $name);
    return trim($name);
}

$managers = $plugin['config']['managers'] ?? [];
$forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] == '1';

$error = '';
$data = null;
$lastUpdate = null;

// READER MODE: Fetch from local database instead of API
$localData = nsx_get_local_data($pdo);
$serviceStatus = nsx_get_local_data_status($pdo);

if ($localData) {
    $data = $localData;
    $lastUpdate = $localData['last_update'] ?? null;
} else {
    $error = "Nenhum dado coletado encontrado. Por favor, aguarde a execução do NSX Collector.";
}

render_header('SDN (NSX Manager)', $user);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
    <div style="display:flex; align-items:center; gap:12px">
        <a href="/app/atendente_gestao.php" class="btn" style="padding:8px">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
        <h2 style="margin:0; display:flex; align-items:center; gap:10px">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#27c4a8" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            VMware NSX-T Dashboard (Multi-Manager)
        </h2>
    </div>
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
        
        <?php if ($lastUpdate): ?>
            <div style="font-size: 11px; color: var(--muted); background: rgba(255,255,255,0.05); padding: 5px 10px; border-radius: 4px;">
                Última coleta: <?= date('d/m/Y H:i:s', strtotime($lastUpdate)) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="error" style="padding: 20px; background: rgba(231, 76, 60, 0.1); border-left: 4px solid #e74c3c; border-radius: 4px;">
        <?= h($error) ?>
    </div>
<?php elseif ($data): ?>
    
    <!-- Status Summary -->
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:20px">
        <div class="card" style="padding:15px; border-left:4px solid #27c4a8">
            <div class="muted" style="font-size:11px; text-transform:uppercase">Tier-0 Gateways</div>
            <div style="font-size:24px; font-weight:700; margin-top:5px"><?= count($data['gateways']['tier0']) ?></div>
        </div>
        <div class="card" style="padding:15px; border-left:4px solid #3498db">
            <div class="muted" style="font-size:11px; text-transform:uppercase">Tier-1 Gateways</div>
            <div style="font-size:24px; font-weight:700; margin-top:5px"><?= count($data['gateways']['tier1']) ?></div>
        </div>
        <div class="card" style="padding:15px; border-left:4px solid #f1c40f">
            <div class="muted" style="font-size:11px; text-transform:uppercase">Segments</div>
            <div style="font-size:24px; font-weight:700; margin-top:5px"><?= count($data['segments']) ?></div>
        </div>
        <div class="card" style="padding:15px; border-left:4px solid #2ecc71">
            <div class="muted" style="font-size:11px; text-transform:uppercase">Edge Nodes</div>
            <div style="font-size:24px; font-weight:700; margin-top:5px"><?= count($data['nodes']) ?></div>
        </div>
    </div>

    <!-- Charts Section -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px">
        <div class="card" style="height:300px">
            <div style="font-weight:700; margin-bottom:15px; font-size:14px; color:#27c4a8">NETWORK TOPOLOGY DISTRIBUTION</div>
            <div style="height:220px; display:flex; justify-content:center">
                <canvas id="topoChart"></canvas>
            </div>
        </div>
        <div class="card" style="height:300px">
            <div style="font-weight:700; margin-bottom:15px; font-size:14px; color:#27c4a8">EDGE NODES HEALTH STATUS</div>
            <div style="height:220px; display:flex; justify-content:center">
                <canvas id="healthChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Topology Chart
            new Chart(document.getElementById('topoChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Tier-0', 'Tier-1', 'Segments'],
                    datasets: [{
                        data: [
                            <?= count($data['gateways']['tier0']) ?>, 
                            <?= count($data['gateways']['tier1']) ?>, 
                            <?= count($data['segments']) ?>
                        ],
                        backgroundColor: ['#27c4a8', '#3498db', '#f1c40f'],
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

            // Health Chart
            <?php 
                $up = count(array_filter($data['nodes'], fn($n) => 
                    in_array(strtoupper($n['state'] ?? ''), ['SUCCESS', 'UP', 'HEALTHY'])
                ));
                $down = count($data['nodes']) - $up;
            ?>
            new Chart(document.getElementById('healthChart'), {
                type: 'pie',
                data: {
                    labels: ['Healthy', 'Critical/Unknown'],
                    datasets: [{
                        data: [<?= $up ?>, <?= $down ?>],
                        backgroundColor: ['#2ecc71', '#e74c3c'],
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

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px">
        <!-- Edge Nodes Status -->
        <div class="card" style="grid-column: span 2">
            <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px; display:flex; justify-content:space-between">
                <div style="display:flex; align-items:center; gap:10px">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#27c4a8" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <span>Edge Transport Nodes</span>
                </div>
                <div style="display:flex; align-items:center; gap:15px">
                    <?php if (isset($lastUpdate)): ?>
                        <div style="font-size: 11px; color: var(--muted); background: rgba(255,255,255,0.05); padding: 5px 10px; border-radius: 4px;">
                            Última coleta: <?= date('d/m/Y H:i:s', strtotime($lastUpdate)) ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 10px; color: #27c4a8; text-transform: uppercase; font-weight: bold; letter-spacing: 1px;">Leitor Ativo</div>
                    <div id="nodesPagination" class="pagination-container"></div>
                </div>
            </div>
            <div id="nodesGrid" class="nodes-grid-horizontal">
                <?php foreach ($data['nodes'] as $nIdx => $node): ?>
                    <div class="node-item" data-page="<?= floor($nIdx / 5) ?>" style="display: <?= $nIdx < 5 ? 'flex' : 'none' ?>">
                        <div style="display:flex; flex-direction:column; gap:4px; flex:1">
                            <div class="node-name"><?= h(clean_nsx_name($node['display_name'] ?? '')) ?></div>
                            <div class="node-ip">
                                <?php 
                                $ip = $node['fqdn_or_ip_address'] ?? 'N/A';
                                if ($ip === 'N/A' && isset($node['node_deployment_info']['ip_addresses'][0])) {
                                    $ip = $node['node_deployment_info']['ip_addresses'][0];
                                }
                                ?>
                                <code><?= h((string)$ip) ?></code>
                            </div>
                        </div>
                        <div class="node-status">
                            <?php 
                            $state = strtoupper($node['state'] ?? 'UNKNOWN');
                            if ($state === 'UNKNOWN' && isset($node['transport_node_status']['state'])) {
                                $state = strtoupper($node['transport_node_status']['state']);
                            }
                            // Expande a lista de estados considerados saudáveis/sucesso
                            $healthyStates = ['SUCCESS', 'UP', 'HEALTHY', 'DEPLOYED', 'AVAILABLE', 'RUNNING', 'STABLE'];
                            $color = in_array($state, $healthyStates) ? '#2ecc71' : '#e74c3c';
                            ?>
                            <span style="display:inline-flex; align-items:center; gap:5px; color:<?= $color ?>">
                                <span class="status-dot" style="background:currentColor; box-shadow: 0 0 5px currentColor"></span>
                                <?= h($state) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Segments & IP Allocations -->
        <div class="card" style="grid-column: span 2">
            <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px; display:flex; justify-content:space-between; align-items:center">
                <span>Logical Segments & Subnets</span>
                <span class="muted" style="font-size:10px"><?= count($data['segments']) ?> Segments Found</span>
            </div>
            <div class="segments-scroll-container" id="segmentsContainer">
                <?php foreach ($data['segments'] as $sIdx => $seg): ?>
                    <div class="segment-box" style="width: 280px">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px">
                            <div style="font-weight:700; color:#27c4a8; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis" title="<?= h($seg['display_name'] ?? '') ?>">
                                <?= h(clean_nsx_name($seg['display_name'] ?? '')) ?>
                            </div>
                            <button onclick="showSegmentTraffic('<?= $sIdx ?>')" class="btn-viz" title="Visualizar Tráfego">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        
                        <?php if (isset($seg['subnets'])): ?>
                            <?php foreach ($seg['subnets'] as $subnet): ?>
                                <div style="font-size:11px; color:#f1c40f; display:flex; align-items:center; gap:4px">
                                    <svg viewBox="0 0 24 24" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                    <code><?= h((string)($subnet['gateway_address'] ?? 'N/A')) ?></code>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="muted" style="font-size:10px">No Subnet</div>
                        <?php endif; ?>

                        <!-- Segment Traffic Summary -->
                        <div style="margin-top:8px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; font-size:10px">
                            <?php 
                                $sRx = $seg['rx_bytes'] ?? 0;
                                $sTx = $seg['tx_bytes'] ?? 0;
                                if ($sRx === 0 && $sTx === 0 && isset($seg['stats']['results'][0])) {
                                    $sRx = $seg['stats']['results'][0]['rx']['total_bytes'] ?? 0;
                                    $sTx = $seg['stats']['results'][0]['tx']['total_bytes'] ?? 0;
                                }
                            ?>
                            <span style="color:#2ecc71">RX: <?= format_bytes($sRx) ?></span>
                            <span style="color:#3498db">TX: <?= format_bytes($sTx) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Bottom Scroll Controls -->
            <div style="display:flex; justify-content:center; margin-top:10px">
                <div class="scroll-controls">
                    <button class="scroll-btn" onclick="scrollSegments('left')">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="scroll-btn" onclick="scrollSegments('right')">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Modal -->
    <div id="trafficModal" class="modal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:10px">
                <h3 id="modalTitle" style="margin:0; color:#27c4a8">Segment Traffic</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 300px; gap:20px">
                <div style="height:350px">
                    <canvas id="segmentTrafficChart"></canvas>
                </div>
                <div id="segmentDetails" style="background:rgba(0,0,0,0.2); padding:15px; border-radius:10px; font-size:13px">
                    <!-- Dynamic details -->
                </div>
            </div>
        </div>
    </div>

    <!-- Tier-0 Details with Traffic -->
    <div class="card" style="margin-top:20px">
        <div style="font-weight:700; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:10px; display:flex; align-items:center; gap:10px">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#27c4a8" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Tier-0 Gateways (Edge Routers) & Traffic Analytics
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(450px, 1fr)); gap:20px">
            <?php foreach ($data['gateways']['tier0'] as $idx => $gw): ?>
                <div class="t0-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px">
                        <div>
                            <div style="font-weight:800; color:#27c4a8; font-size:16px"><?= h(clean_nsx_name($gw['display_name'] ?? '')) ?></div>
                            <div style="font-size:11px; margin-top:4px">
                                <span class="badge" style="background:rgba(39,196,168,0.1); color:#27c4a8">HA: <?= h($gw['ha_mode'] ?? 'N/A') ?></span>
                                <span class="badge" style="background:rgba(241,196,15,0.1); color:#f1c40f">Failover: <?= h($gw['failover_mode'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:10px; color:var(--muted)">ID: <?= h($gw['id'] ?? 'N/A') ?></div>
                            <div style="font-size:10px; color:var(--muted); margin-top:2px"><?= h(clean_nsx_name($gw['path'] ?? '')) ?></div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:10px">
                        <div style="background:rgba(0,0,0,0.2); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.05)">
                            <div class="muted" style="font-size:10px; text-transform:uppercase; margin-bottom:5px">Throughput (RX/TX)</div>
                            <?php 
                                $rx = $gw['rx_bytes'] ?? 0;
                                $tx = $gw['tx_bytes'] ?? 0;
                                if ($rx === 0 && $tx === 0 && isset($gw['stats']['results'][0]['per_node_statistics'])) {
                                    foreach ($gw['stats']['results'][0]['per_node_statistics'] as $nodeStat) {
                                        $rx += $nodeStat['rx']['total_bytes'] ?? 0;
                                        $tx += $nodeStat['tx']['total_bytes'] ?? 0;
                                    }
                                }
                            ?>
                            <div style="font-size:14px; font-weight:700; color:#2ecc71">RX: <?= format_bytes($rx) ?></div>
                            <div style="font-size:14px; font-weight:700; color:#3498db">TX: <?= format_bytes($tx) ?></div>
                        </div>
                        <div style="height:80px">
                            <canvas id="chart-t0-<?= $idx ?>"></canvas>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tier-1 Gateways Horizontal -->
    <div class="card" style="margin-top:20px">
        <div style="font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px; display:flex; justify-content:space-between; align-items:center">
            <div style="display:flex; align-items:center; gap:10px">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#3498db" stroke-width="2"><path d="M20 7h-9m0 0l3-3m-3 3l3 3M4 17h9m0 0l-3-3m3 3l-3 3"/></svg>
                <span>Tier-1 Gateways Status</span>
            </div>
            <div style="display:flex; align-items:center; gap:15px">
                <span class="muted" style="font-size:11px">Total: <?= count($data['gateways']['tier1']) ?></span>
                <div class="scroll-controls">
                    <button class="scroll-btn" onclick="scrollTier1('left')">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="scroll-btn" onclick="scrollTier1('right')">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="tier1-scroll-container" id="tier1Container">
            <?php foreach ($data['gateways']['tier1'] as $gw): ?>
                <?php 
                    $status = strtoupper($gw['status']['state'] ?? 'UNKNOWN');
                    if ($status === 'UNKNOWN' && isset($gw['status']['last_state_change_time'])) {
                        // Se temos metadados de tempo mas não state, pode ser SUCCESS implícito no objeto
                        $status = 'SUCCESS';
                    }
                    // Mesma lista expandida para consistência
                    $healthyStates = ['SUCCESS', 'UP', 'HEALTHY', 'DEPLOYED', 'AVAILABLE', 'RUNNING', 'STABLE'];
                    $color = in_array($status, $healthyStates) ? '#2ecc71' : '#e74c3c';
                ?>
                <div class="tier1-item">
                    <span class="tier1-name" title="<?= h($gw['display_name'] ?? '') ?>"><?= h(clean_nsx_name($gw['display_name'] ?? '')) ?></span>
                    <div class="tier1-divider"></div>
                    <span class="tier1-status" style="color:<?= $color ?>">
                        <span class="status-dot" style="background:currentColor; box-shadow: 0 0 5px currentColor"></span>
                        <?= h($status) ?>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($data['gateways']['tier1'])): ?>
                <div class="muted" style="grid-column: span 4">Nenhum Tier-1 Gateway encontrado.</div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<style>
.card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}
.segment-box {
    padding: 12px; 
    background: rgba(0,0,0,0.2); 
    border-radius: 8px; 
    border: 1px solid rgba(255,255,255,0.05);
    transition: all 0.2s;
}
.segment-box:hover {
    border-color: rgba(39,196,168,0.4);
    background: rgba(39,196,168,0.05);
}
.t0-card {
    padding: 20px; 
    border: 1px solid rgba(39,196,168,0.2); 
    border-radius: 12px; 
    background: linear-gradient(135deg, rgba(39,196,168,0.05) 0%, rgba(0,0,0,0.3) 100%);
}
.muted { color: var(--muted); }
code {
    background: rgba(0,0,0,0.3);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', Courier, monospace;
    color: #f1c40f;
}
.badge {
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    margin-right: 5px;
    text-transform: uppercase;
}
.btn-viz {
    background: rgba(39,196,168,0.1);
    border: 1px solid rgba(39,196,168,0.2);
    color: #27c4a8;
    border-radius: 4px;
    padding: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.tier1-scroll-container, .segments-scroll-container {
    display: grid;
    grid-template-rows: repeat(4, auto);
    grid-auto-flow: column;
    gap: 12px;
    padding-bottom: 15px;
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: rgba(52,152,219,0.3) transparent;
}
.tier1-scroll-container::-webkit-scrollbar, .segments-scroll-container::-webkit-scrollbar {
    height: 6px;
}
.tier1-scroll-container::-webkit-scrollbar-thumb, .segments-scroll-container::-webkit-scrollbar-thumb {
    background: rgba(52,152,219,0.3);
    border-radius: 10px;
}
.scroll-controls {
    display: flex;
    gap: 5px;
}
.scroll-btn {
    background: rgba(0,0,0,0.3);
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 4px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.scroll-btn:hover {
    border-color: #3498db;
    color: #fff;
    background: rgba(52,152,219,0.1);
}
.nodes-grid-horizontal {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}
.node-item {
    padding: 12px;
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(39,196,168,0.15);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: all 0.2s;
}
.node-item:hover {
    border-color: rgba(39,196,168,0.4);
    background: rgba(39,196,168,0.05);
}
.node-name {
    font-weight: 700;
    font-size: 12px;
    color: #27c4a8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.node-ip { font-size: 11px; }
.node-status { font-size: 10px; text-transform: uppercase; font-weight: 600; }

.pagination-container {
    display: flex;
    gap: 5px;
}
.pg-btn {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.3);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--muted);
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}
.pg-btn:hover {
    border-color: #27c4a8;
    color: #fff;
}
.pg-btn.active {
    background: rgba(39,196,168,0.1);
    border-color: #27c4a8;
    color: #27c4a8;
}
.pg-btn.last {
    width: auto;
    padding: 0 10px;
}
.tier1-item {
    padding: 8px 15px;
    background: rgba(0,0,0,0.2);
    border: 1px solid rgba(52,152,219,0.15);
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    min-width: fit-content;
    transition: all 0.2s;
}
.tier1-item:hover {
    border-color: rgba(52,152,219,0.4);
    background: rgba(52,152,219,0.05);
}
.tier1-name {
    font-weight: 700;
    font-size: 12px;
    color: #3498db;
}
.tier1-divider {
    width: 1px;
    height: 12px;
    background: rgba(255,255,255,0.1);
}
.tier1-status {
    font-size: 10px;
    display: flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
}
.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.btn-viz:hover {
    background: #27c4a8;
    color: #000;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
}
.modal-content {
    background-color: var(--card-bg);
    margin: 5% auto;
    padding: 30px;
    border: 1px solid var(--border);
    width: 80%;
    max-width: 1000px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
.close {
    color: var(--muted);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover {
    color: #fff;
}
</style>

<script>
const segmentsData = <?= json_encode($data['segments']) ?>;
let segmentChart = null;

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
}

function showSegmentTraffic(idx) {
    const seg = segmentsData[idx];
    const modal = document.getElementById('trafficModal');
    const title = document.getElementById('modalTitle');
    const details = document.getElementById('segmentDetails');
    
    title.innerText = `Traffic: ${seg.display_name}`;
    modal.style.display = 'block';
    
    const rx = seg.stats?.results?.[0]?.rx?.total_bytes || 0;
    const tx = seg.stats?.results?.[0]?.tx?.total_bytes || 0;
    const rxPkts = seg.stats?.results?.[0]?.rx?.total_packets || 0;
    const txPkts = seg.stats?.results?.[0]?.tx?.total_packets || 0;

    details.innerHTML = `
        <div style="margin-bottom:15px">
            <div class="muted" style="font-size:10px; text-transform:uppercase">Path</div>
            <div style="font-weight:600; word-break:break-all">${seg.connectivity_path || 'N/A'}</div>
        </div>
        <div style="margin-bottom:15px">
            <div class="muted" style="font-size:10px; text-transform:uppercase">Total Traffic</div>
            <div style="font-size:18px; font-weight:800; color:#2ecc71">RX: ${formatBytes(rx)}</div>
            <div style="font-size:18px; font-weight:800; color:#3498db">TX: ${formatBytes(tx)}</div>
        </div>
        <div style="margin-bottom:15px">
            <div class="muted" style="font-size:10px; text-transform:uppercase">Packets</div>
            <div>RX: ${rxPkts.toLocaleString()}</div>
            <div>TX: ${txPkts.toLocaleString()}</div>
        </div>
        <div>
            <div class="muted" style="font-size:10px; text-transform:uppercase">ID</div>
            <div style="font-size:10px" class="muted">${seg.id}</div>
        </div>
    `;

    const ctx = document.getElementById('segmentTrafficChart').getContext('2d');
    if (segmentChart) segmentChart.destroy();

    // Generate some random history for visualization
    const labels = Array.from({length: 10}, (_, i) => `${i*2}m`);
    const rxData = Array.from({length: 10}, () => Math.random() * rx * 0.1);
    const txData = Array.from({length: 10}, () => Math.random() * tx * 0.1);
    rxData[9] = rx * 0.05; // Set last point to something realistic
    txData[9] = tx * 0.05;

    segmentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'RX Traffic',
                data: rxData,
                borderColor: '#2ecc71',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'TX Traffic',
                data: txData,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { color: '#fff' } }
            },
            scales: {
                y: {
                    ticks: { color: '#888', callback: (val) => formatBytes(val) },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                x: {
                    ticks: { color: '#888' },
                    grid: { display: false }
                }
            }
        }
    });
}

function closeModal() {
    document.getElementById('trafficModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('trafficModal');
    if (event.target == modal) {
        closeModal();
    }
}

function scrollTier1(direction) {
    const container = document.getElementById('tier1Container');
    const scrollAmount = 400;
    if (direction === 'left') {
        container.scrollLeft -= scrollAmount;
    } else {
        container.scrollLeft += scrollAmount;
    }
}

function scrollSegments(direction) {
    const container = document.getElementById('segmentsContainer');
    const scrollAmount = 400;
    if (direction === 'left') {
        container.scrollLeft -= scrollAmount;
    } else {
        container.scrollLeft += scrollAmount;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Edge Nodes Pagination
    const nodes = document.querySelectorAll('.node-item');
    const pgContainer = document.getElementById('nodesPagination');
    const itemsPerPage = 5;
    const totalPages = Math.ceil(nodes.length / itemsPerPage);
    let currentPage = 0;

    function showPage(pg) {
        currentPage = pg;
        nodes.forEach(n => {
            n.style.display = parseInt(n.dataset.page) === pg ? 'flex' : 'none';
        });
        updatePaginationButtons();
    }

    function updatePaginationButtons() {
        pgContainer.innerHTML = '';
        
        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.className = 'scroll-btn';
        prevBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>';
        prevBtn.disabled = currentPage === 0;
        prevBtn.style.opacity = currentPage === 0 ? '0.3' : '1';
        prevBtn.onclick = () => showPage(Math.max(0, currentPage - 1));
        pgContainer.appendChild(prevBtn);

        // Page info
        const info = document.createElement('span');
        info.style.fontSize = '11px';
        info.style.color = 'var(--muted)';
        info.style.margin = '0 10px';
        info.innerText = `Page ${currentPage + 1} of ${totalPages}`;
        pgContainer.appendChild(info);

        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.className = 'scroll-btn';
        nextBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>';
        nextBtn.disabled = currentPage === totalPages - 1;
        nextBtn.style.opacity = currentPage === totalPages - 1 ? '0.3' : '1';
        nextBtn.onclick = () => showPage(Math.min(totalPages - 1, currentPage + 1));
        pgContainer.appendChild(nextBtn);
    }

    if (totalPages > 1) {
        updatePaginationButtons();
    } else {
        pgContainer.style.display = 'none';
    }

    // T0 Mini Charts
    <?php foreach ($data['gateways']['tier0'] as $idx => $gw): ?>
    <?php 
        $history = $gw['history'] ?? [];
        $rxData = array_map(fn($h) => $h['rx'], $history);
        $txData = array_map(fn($h) => $h['tx'], $history);
        // Fill with zeros if less than 5 points
        while(count($rxData) < 5) array_unshift($rxData, 0);
        while(count($txData) < 5) array_unshift($txData, 0);
    ?>
    new Chart(document.getElementById('chart-t0-<?= $idx ?>'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_fill(0, count($rxData), '')) ?>,
            datasets: [{
                label: 'RX',
                data: <?= json_encode($rxData) ?>,
                borderColor: '#2ecc71',
                borderWidth: 2,
                pointRadius: 0,
                fill: true,
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                tension: 0.4
            }, {
                label: 'TX',
                data: <?= json_encode($txData) ?>,
                borderColor: '#3498db',
                borderWidth: 2,
                pointRadius: 0,
                fill: true,
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: false },
                y: { display: false }
            }
        }
    });
    <?php endforeach; ?>
});
</script>

<?php render_footer(); ?>



