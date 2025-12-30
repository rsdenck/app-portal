<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

if (!$isEmbed) {
    render_header('SNMP Integration', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent;' : '' ?> color:var(--text);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2 style="margin:0; color:var(--text);">SNMP Device Management</h2>
        <div style="display:flex;gap:10px">
            <span class="badge success">Engine: SNMP v1/v2c/v3</span>
            <span class="badge primary">Discovery: Active</span>
        </div>
    </div>

    <div style="grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-bottom:30px; display: <?= $pdo->query("SELECT COUNT(*) FROM plugin_dflow_interfaces")->fetchColumn() > 0 ? 'grid' : 'none' ?>;">
        <div class="stat-box" style="color:var(--text)">
            <div class="muted">Total Interfaces</div>
            <div class="value" style="color:var(--primary)"><?= $pdo->query("SELECT COUNT(*) FROM plugin_dflow_interfaces")->fetchColumn() ?></div>
        </div>
        <div class="stat-box" style="color:var(--text)">
            <div class="muted">Active VLANs</div>
            <div class="value" style="color:var(--primary)"><?= $pdo->query("SELECT COUNT(DISTINCT vlan) FROM plugin_dflow_interfaces")->fetchColumn() ?></div>
        </div>
        <div class="stat-box" style="color:var(--text)">
            <div class="muted">Correlated Flows</div>
            <div class="value" style="color:var(--primary)"><?= $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows WHERE application = 'SNMP Correlated'")->fetchColumn() ?></div>
        </div>
    </div>

    <?php 
    $interfaces = $pdo->query("SELECT * FROM plugin_dflow_interfaces ORDER BY if_index ASC")->fetchAll();
    if (empty($interfaces)): 
    ?>
    <div class="empty-state" style="padding:60px; text-align:center; background:var(--bg); border-radius:12px; border:1px dashed var(--border)">
        <div style="font-size:40px; margin-bottom:15px">📡</div>
        <h3>No SNMP Interfaces Discovered</h3>
        <p class="muted">Start by adding a device or running the SNMP collector.</p>
        <div style="margin-top:20px">
            <button class="btn btn-primary" onclick="runDiscovery()">Run Collector Now</button>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>Index</th>
                    <th>Interface</th>
                    <th>Alias / Description</th>
                    <th>MAC Address</th>
                    <th>VLAN</th>
                    <th>Speed</th>
                    <th>Status</th>
                    <th>Last Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interfaces as $if): ?>
                <tr>
                    <td><?= $if['if_index'] ?></td>
                    <td><b><?= h($if['name']) ?></b></td>
                    <td><?= h($if['description']) ?></td>
                    <td><code style="font-size:11px"><?= $if['mac_address'] ?></code></td>
                    <td><span class="badge"><?= $if['vlan'] ?></span></td>
                    <td><?= format_bytes($if['speed']) ?>bps</td>
                    <td><span class="status-dot active"></span> UP</td>
                    <td style="font-size:11px; color:var(--text)" class="last-update" data-time="<?= strtotime($if['updated_at']) ?>">
                        <?= date('H:i:s', strtotime($if['updated_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

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
});

function runDiscovery() {
    const btn = event.target;
    btn.disabled = true;
    btn.innerText = 'Running...';
    fetch('../scripts/dflow_snmp_collector.php') // This might need a proper wrapper if not direct
        .then(() => window.location.reload());
}
</script>

<style>
.stat-box { background: var(--bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border); }
.stat-box .value { font-size: 24px; font-weight: 800; margin-top: 5px; }
.badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
.badge.success { background: rgba(39,196,168,0.15); color: var(--primary); }
.badge.primary { background: rgba(39,196,168,0.15); color: var(--primary); }
</style>

<?php 
if (!$isEmbed) {
    render_footer(); 
} else {
    echo '</body></html>';
}
?>
