<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Fetch Flows with filtering
$where = [];
$params = [];
$interfaceInfo = null;

if (isset($_GET['mac'])) {
    $where[] = "(src_mac = ? OR dst_mac = ?)";
    $params[] = $_GET['mac'];
    $params[] = $_GET['mac'];

    // Fetch interface info if MAC is provided
    $stmtIf = $pdo->prepare("SELECT * FROM plugin_dflow_interfaces WHERE mac_address = ?");
    $stmtIf->execute([$_GET['mac']]);
    $interfaceInfo = $stmtIf->fetch();
}

if (isset($_GET['src'])) {
    $where[] = "src_ip = ?";
    $params[] = $_GET['src'];
}
if (isset($_GET['dst'])) {
    $where[] = "dst_ip = ?";
    $params[] = $_GET['dst'];
}
if (isset($_GET['vlan']) && $_GET['vlan'] != '0') {
    $where[] = "vlan = ?";
    $params[] = $_GET['vlan'];
}

$sql = "SELECT * FROM plugin_dflow_flows";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY ts DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flows = $stmt->fetchAll();

if (!$isEmbed) {
    render_header('DFlow · Flows', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent; color:inherit;' : '' ?>">
    <?php if ($interfaceInfo): ?>
        <div class="interface-summary" style="background:rgba(39,196,168,0.1); border:1px solid rgba(39,196,168,0.2); border-radius:8px; padding:15px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:12px; color:var(--muted); text-transform:uppercase; font-weight:700;">Interface-Centric Analysis</div>
                <h3 style="margin:5px 0; color:var(--primary);"><?= h($interfaceInfo['name']) ?> <span style="font-size:14px; color:var(--text-muted); font-weight:normal;">(<?= h($interfaceInfo['description']) ?>)</span></h3>
                <div style="font-size:11px; color:var(--text-muted);">MAC: <?= h($interfaceInfo['mac_address']) ?> | VLAN: <?= $interfaceInfo['vlan'] ?> | Status: <span style="color:var(--primary); font-weight:bold;"><?= strtoupper($interfaceInfo['status']) ?></span></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:20px; font-weight:700; color:var(--text);"><?= format_bytes($interfaceInfo['in_bytes'] + $interfaceInfo['out_bytes']) ?></div>
                <div style="font-size:11px; color:var(--muted);">Total Interface Traffic</div>
            </div>
        </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2 style="margin:0; color:var(--text)"><?= $interfaceInfo ? 'Correlated Flows' : 'IP Flows' ?></h2>
        <div style="display:flex;gap:10px">
            <span class="badge info">Real-time Analysis</span>
        </div>
    </div>

    <div class="table-container">
        <table class="table" style="color:var(--text)">
            <thead>
                <tr>
                    <th style="color:var(--text)">Timestamp</th>
                    <th style="color:var(--text)">Source</th>
                    <th style="color:var(--text)">Destination</th>
                    <th style="color:var(--text)">Proto</th>
                    <th style="color:var(--text)">App</th>
                    <th style="color:var(--text)">Bytes</th>
                    <th style="color:var(--text)">Packets</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($flows)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px">
                            <div class="muted">No flows detected.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($flows as $f): ?>
                        <tr>
                            <td style="font-size:11px; color:var(--text)" class="last-update" data-time="<?= strtotime($f['ts']) ?>">
                                <?= date('H:i:s', strtotime($f['ts'])) ?>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text)"><?= h((string)$f['src_ip']) ?></div>
                                <div style="font-size:10px;color:var(--muted)">Port: <?= (int)$f['src_port'] ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text)"><?= h((string)$f['dst_ip']) ?></div>
                                <div style="font-size:10px;color:var(--muted)">Port: <?= (int)$f['dst_port'] ?></div>
                            </td>
                            <td><span class="tag-proto"><?= h((string)$f['protocol']) ?></span></td>
                            <td><span class="tag-app"><?= h((string)($f['app_proto'] ?: 'Unknown')) ?></span></td>
                            <td style="color:var(--text)"><?= format_bytes($f['bytes']) ?></td>
                            <td style="color:var(--text)"><?= (int)$f['packets'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
</script>

<style>
.interface-summary {
    transition: all 0.2s;
}
.tag-proto { background: rgba(39,196,168,0.1); color: var(--primary); padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.tag-app { background: rgba(0,123,255,0.1); color: #007bff; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.table th { color: var(--text); border-bottom: 2px solid var(--border); }
.table td { color: var(--text); }
</style>

<?php 
if (!$isEmbed) {
    render_footer(); 
} else {
    echo '</body></html>';
}
?>
