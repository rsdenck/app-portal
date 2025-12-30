<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Fetch Interfaces with filtering
$where = [];
$params = [];
if (isset($_GET['vlan']) && $_GET['vlan'] != '0') {
    $where[] = "vlan = ?";
    $params[] = $_GET['vlan'];
}

$sql = "SELECT * FROM plugin_dflow_interfaces";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY if_index ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
    $interfaces = $stmt->fetchAll();

    // Fetch VLAN Inventory
    $stmtVlans = $pdo->query("SELECT * FROM plugin_dflow_vlans ORDER BY vlan_id ASC");
    $vlans = $stmtVlans->fetchAll();

    // Fetch IP Blocks Status
    $stmtBlocks = $pdo->query("SELECT b.*, 
                               (SELECT COUNT(*) FROM plugin_dflow_ip_scanning s WHERE s.block_id = b.id AND s.status = 'active') as active_count,
                               (SELECT GROUP_CONCAT(ip_address ORDER BY ip_address ASC LIMIT 5) FROM plugin_dflow_ip_scanning s WHERE s.block_id = b.id AND s.status = 'active') as sample_ips
                               FROM plugin_dflow_ip_blocks b");
    $blocks = $stmtBlocks->fetchAll();

    if (!$isEmbed) {
        render_header('DFlow · Redes & Interfaces', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

    <style>
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .nav-tab { padding: 8px 16px; cursor: pointer; border-radius: 8px; color: var(--text); background: var(--bg-card); border: 1px solid var(--border); text-decoration: none; font-size: 14px; }
        .nav-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .block-card { background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 8px; padding: 15px; margin-bottom: 15px; }
    </style>

    <div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent;' : '' ?> color:var(--text);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h2 style="margin:0; color:var(--text);">Network Inventory & Interfaces</h2>
            <div style="display:flex;gap:10px">
                <span class="badge success">Active Interfaces: <?= count($interfaces) ?></span>
                <span class="badge info">VLANs: <?= count($vlans) ?></span>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="javascript:void(0)" class="nav-tab active" onclick="showTab(event, 'interfaces')">Physical/Virtual Interfaces</a>
            <a href="javascript:void(0)" class="nav-tab" onclick="showTab(event, 'vlans')">VLAN Inventory</a>
            <a href="javascript:void(0)" class="nav-tab" onclick="showTab(event, 'blocks')">IP Blocks & Scanning</a>
        </div>

        <!-- Interfaces Tab -->
        <div id="tab-interfaces" class="tab-content active">
            <div class="table-container">
                <table class="table" style="color:var(--text)">
                    <thead>
                        <tr>
                            <th style="color:var(--text)">Index</th>
                            <th style="color:var(--text)">Interface / MAC</th>
                            <th style="color:var(--text)">IP Address</th>
                            <th style="color:var(--text)">VLAN</th>
                            <th style="color:var(--text)">Status</th>
                            <th style="color:var(--text)">Traffic (30s)</th>
                            <th style="color:var(--text)">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($interfaces)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px; color:var(--text)">
                                    <div class="muted">No interfaces detected. Check collector status.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($interfaces as $if): 
                                // Enhanced: Get IP from hosts table if not in interfaces
                                $displayIp = $if['ip_address'];
                                if (!$displayIp && $if['mac_address']) {
                                    $stmtIp = $pdo->prepare("SELECT ip_address FROM plugin_dflow_hosts WHERE mac_address = ? LIMIT 1");
                                    $stmtIp->execute([$if['mac_address']]);
                                    $displayIp = $stmtIp->fetchColumn() ?: '---';
                                }
                            ?>
                                <tr>
                                    <td style="color:var(--text)">#<?= $if['if_index'] ?></td>
                                    <td>
                                        <div style="font-weight:700; color:var(--text)"><?= h($if['name']) ?></div>
                                        <div style="font-size:11px;color:var(--muted)"><?= h($if['description'] ?: 'No description') ?></div>
                                        <div style="font-size:10px; color:var(--primary); margin-top:4px;">MAC: <?= $if['mac_address'] ?></div>
                                    </td>
                                    <td>
                                        <div style="font-family:monospace; color:var(--warning)"><?= $displayIp ?></div>
                                    </td>
                                    <td>
                                        <span class="badge info">VLAN <?= $if['vlan'] ?: 'Native' ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $if['status'] === 'up' ? 'success' : 'danger' ?>">
                                            <?= strtoupper($if['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="color:var(--primary); font-weight:700; font-size:12px;">
                                            In: <?= format_bytes($if['in_bytes'] ?? 0) ?>
                                        </div>
                                        <div style="color:var(--warning); font-weight:700; font-size:12px;">
                                            Out: <?= format_bytes($if['out_bytes'] ?? 0) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="plugin_dflow_flows.php?mac=<?= urlencode($if['mac_address']) ?>&vlan=<?= $if['vlan'] ?>&embed=<?= $isEmbed ? '1' : '0' ?>" 
                                           class="btn secondary small flow-link" 
                                           data-mac="<?= h($if['mac_address']) ?>" 
                                           data-vlan="<?= $if['vlan'] ?>"
                                           title="Deep Flow Analysis">
                                            Flows
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VLANs Tab -->
        <div id="tab-vlans" class="tab-content">
            <div class="table-container">
                <table class="table" style="color:var(--text)">
                    <thead>
                        <tr>
                            <th style="color:var(--text)">VLAN ID</th>
                            <th style="color:var(--text)">VLAN Name</th>
                            <th style="color:var(--text)">Active IPs</th>
                            <th style="color:var(--text)">Device IP</th>
                            <th style="color:var(--text)">Status</th>
                            <th style="color:var(--text)">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vlans)): ?>
                            <tr><td colspan="6" style="text-align:center;padding:40px">No VLANs in inventory. Run SNMP Discovery.</td></tr>
                        <?php else: ?>
                            <?php foreach ($vlans as $v): 
                                // Fetch active IPs for this VLAN
                                $stmtActive = $pdo->prepare("SELECT ip_address FROM plugin_dflow_hosts WHERE vlan = ? AND is_active = 1");
                                $stmtActive->execute([$v['vlan_id']]);
                                $activeIps = $stmtActive->fetchAll(PDO::FETCH_COLUMN);
                            ?>
                                <tr>
                                    <td><span class="badge info">VLAN <?= $v['vlan_id'] ?></span></td>
                                    <td><strong><?= h($v['vlan_name']) ?></strong></td>
                                    <td>
                                        <div style="max-height:60px; overflow-y:auto; font-size:10px; font-family:monospace; color:var(--primary)">
                                            <?php if (empty($activeIps)): ?>
                                                <span class="muted">None active</span>
                                            <?php else: ?>
                                                <?= implode(', ', $activeIps) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= h($v['device_ip']) ?></td>
                                    <td><span class="badge success"><?= strtoupper($v['vlan_status']) ?></span></td>
                                    <td style="font-size:11px;"><?= $v['last_updated'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- IP Blocks Tab -->
        <div id="tab-blocks" class="tab-content">
            <div class="row">
                <?php foreach ($blocks as $b): ?>
                    <div class="col-md-4">
                        <div class="block-card" style="margin-bottom:20px">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <h3 style="margin:0; color:var(--primary);"><?= h($b['cidr']) ?></h3>
                                <span class="badge <?= $b['is_active'] ? 'success' : 'danger' ?>"><?= $b['is_active'] ? 'ACTIVE' : 'INACTIVE' ?></span>
                            </div>
                            <p class="muted" style="font-size:12px; margin-bottom:15px;"><?= h($b['description']) ?></p>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:13px;">
                                    <strong><?= $b['active_count'] ?></strong> Active IPs Found
                                </div>
                                <div style="font-size:11px;" class="muted">
                                    Last Scan: <?= $b['last_scan'] ?: 'Never' ?>
                                </div>
                            </div>
                            <div style="margin-top:15px; height:6px; background:rgba(255,255,255,0.1); border-radius:3px; overflow:hidden;">
                                <div style="width:<?= min(100, ($b['active_count']/1024)*100) ?>%; height:100%; background:var(--primary);"></div>
                            </div>

                            <!-- New: Active IPs Sample -->
                            <?php if ($b['active_count'] > 0): ?>
                                <div style="margin-top:15px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.05);">
                                    <div style="font-size:11px; color:var(--muted); margin-bottom:5px;">Amostra de IPs Ativos:</div>
                                    <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                        <?php 
                                        $stmtIps = $pdo->prepare("SELECT ip_address, open_ports FROM plugin_dflow_ip_scanning WHERE block_id = ? AND status = 'active' LIMIT 10");
                                        $stmtIps->execute([$b['id']]);
                                        while ($ipData = $stmtIps->fetch()): 
                                        ?>
                                            <span class="badge" style="background:rgba(39,196,168,0.05); color:var(--primary); font-size:10px; border:1px solid rgba(39,196,168,0.2);" title="Ports: <?= $ipData['open_ports'] ?: 'none detected' ?>">
                                                <?= $ipData['ip_address'] ?>
                                            </span>
                                        <?php endwhile; ?>
                                        <?php if ($b['active_count'] > 10): ?>
                                            <span style="font-size:10px; color:var(--muted)">+<?= $b['active_count'] - 10 ?> mais</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="card" style="margin-top:20px; border:1px dashed var(--border); background:rgba(255,255,255,0.02);">
                <h3>Run Batch Scanner</h3>
                <p class="muted">Trigger a manual scan of all active IP blocks to discover new hosts and open ports.</p>
                <button onclick="runBatchScan(this)" class="btn primary">Start IP Discovery Scan 🔍</button>
            </div>
        </div>
    </div>

    <script>
    function showTab(event, tabId) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    function runBatchScan(btn) {
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Scanning Blocks... ⏳';
        
        fetch('../scripts/dflow_batch_scanner.php')
            .then(r => r.text())
            .then(data => {
                console.log(data);
                alert('Scan completed successfully! Refreshing data...');
                location.reload();
            })
            .catch(e => {
                alert('Error running scan: ' + e);
                btn.disabled = false;
                btn.innerText = originalText;
            });
    }
    </script>
</div>

<style>
.badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge.success { background: rgba(39,196,168,0.15); color: var(--primary); }
.badge.danger { background: rgba(255,90,95,0.15); color: var(--danger); }
.badge.info { background: rgba(0,123,255,0.15); color: #007bff; }
.muted { color: var(--muted) !important; }
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(39,196,168,0.1);
    color: var(--primary);
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    transition: all 0.2s;
    border: 1px solid rgba(39,196,168,0.2);
}
.btn-action:hover {
    background: var(--primary);
    color: #fff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Relative time updater
    function updateRelativeTimes() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.last-update').forEach(el => {
            const time = parseInt(el.dataset.time);
            const diff = now - time;
            
            if (diff < 5) {
                el.innerHTML = '<span style="color:var(--primary); font-weight:bold;">LIVE</span>';
            } else if (diff < 60) {
                el.innerHTML = diff + 's ago';
            } else {
                const mins = Math.floor(diff / 60);
                el.innerHTML = mins + 'm ago';
            }
        });
    }
    setInterval(updateRelativeTimes, 1000);
    updateRelativeTimes();

    const flowLinks = document.querySelectorAll('.flow-link');
    flowLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // If embedded in Hub, try to communicate with parent
            if (window.parent !== window) {
                e.preventDefault();
                const mac = this.dataset.mac;
                const vlan = this.dataset.vlan;
                
                // Try to find the parent's tab switcher or send a message
                // In plugin_dflow_maps.php, we have a way to switch tabs
                try {
                    if (window.parent.switchTab) {
                        window.parent.switchTab('flows', { mac: mac, vlan: vlan });
                    } else {
                        // Fallback: postMessage to parent
                        window.parent.postMessage({
                            type: 'switchTab',
                            tab: 'flows',
                            params: { mac: mac, vlan: vlan }
                        }, '*');
                    }
                } catch (err) {
                    console.error('Error communicating with parent Hub:', err);
                    // Fallback to normal navigation if blocked
                    window.location.href = this.href;
                }
            }
        });
    });
});
</script>

<?php 
if (!$isEmbed) {
    render_footer(); 
} else {
    echo '</body></html>';
}
?>
