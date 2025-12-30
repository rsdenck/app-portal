<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Fetch Hosts with search filter and VLAN context
$search = $_GET['search'] ?? '';
$vlan = isset($_GET['vlan']) ? (int)$_GET['vlan'] : 0;

$where = [];
$params = [];

if ($vlan > 0) {
    $where[] = "vlan = ?";
    $params[] = $vlan;
}

if (!empty($search)) {
    $where[] = "(ip_address LIKE ? OR hostname LIKE ? OR mac_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT *, 
               COALESCE(throughput_in, 0) + COALESCE(throughput_out, 0) as throughput_bps, 
               COALESCE(bytes_sent, 0) + COALESCE(bytes_received, 0) as total_bytes 
        FROM plugin_dflow_hosts";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY last_seen DESC, throughput_bps DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$isEmbed) {
    render_header('DFlow · Hosts', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>DFlow · Hosts</title><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent; color:inherit;' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px; gap: 20px;">
        <h2 style="margin:0; color:var(--text)">Hosts Ativos</h2>
        
        <div style="flex:1; max-width: 400px;">
            <form method="GET" style="display:flex; gap:10px;">
                <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Buscar por IP, Host ou MAC..." 
                       style="flex:1; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:8px 12px; border-radius:8px;">
                <button type="submit" class="btn primary" style="padding:8px 15px;">Buscar</button>
            </form>
        </div>

        <div style="display:flex;gap:10px; align-items:center;">
            <button onclick="runDiscovery(this)" class="btn" style="padding:8px 15px; background:var(--bg); border:1px solid var(--border); color:var(--text); cursor:pointer;">
                🔄 Descoberta SNMP
            </button>
            <a href="plugin_dflow_add_host.php<?= $isEmbed ? '?embed=1' : '' ?>" class="btn primary" style="padding:8px 15px; text-decoration:none; font-weight:700;">+ Adicionar Host</a>
            <span class="badge info">nDPI: On</span>
            <span class="badge primary">Geo: On</span>
        </div>
    </div>

    <div class="table-container">
        <table class="table" style="color:var(--text)">
            <thead>
                <tr>
                    <th style="color:var(--text)">Endereço IP</th>
                    <th style="color:var(--text)">Contexto VLAN</th>
                    <th style="color:var(--text)">Hostname / MAC</th>
                    <th style="color:var(--text)">Top Apps (DPI)</th>
                    <th style="color:var(--text)">Throughput</th>
                    <th style="color:var(--text)">Tráfego Total</th>
                    <th style="color:var(--text)">Última Ativ.</th>
                    <th style="color:var(--text)">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hosts)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:60px">
                            <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
                            <div class="muted" style="font-size: 18px; margin-bottom: 20px;">Nenhum host detectado no momento.</div>
                            <div style="display: flex; gap: 15px; justify-content: center;">
                                <a href="plugin_dflow_add_host.php<?= $isEmbed ? '?embed=1' : '' ?>" class="btn primary" style="padding: 12px 25px; text-decoration: none;">+ Adicionar Host via SNMP</a>
                                <button onclick="runDiscovery(this)" class="btn" style="padding: 12px 25px; background: var(--bg-card); border: 1px solid var(--border); color: var(--text); cursor: pointer;">
                                    🔄 Executar Descoberta Agora
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($hosts as $h): 
                        // Fetch top protocols for this host via flows
                        $stmtProto = $pdo->prepare("SELECT app_proto, SUM(bytes) as vol 
                                                   FROM plugin_dflow_flows 
                                                   WHERE src_ip = ? OR dst_ip = ?
                                                   GROUP BY app_proto ORDER BY vol DESC LIMIT 2");
                        $stmtProto->execute([$h['ip_address'], $h['ip_address']]);
                        $topProtos = $stmtProto->fetchAll();
                    ?>
                        <tr>
                            <td>
                                <div style="font-weight:800;color:var(--primary)"><?= h((string)$h['ip_address']) ?></div>
                            </td>
                            <td>
                                <span class="tag-vlan">VLAN <?= (int)$h['vlan'] ?></span>
                                <div style="font-size:10px; color:var(--muted); margin-top:4px;">Virtual Segment</div>
                            </td>
                            <td>
                                <div style="font-size:13px; color:var(--text)"><?= h((string)($h['hostname'] ?: 'Unknown')) ?></div>
                                <div style="font-size:10px;color:var(--muted)"><?= h((string)($h['mac_address'] ?? '')) ?></div>
                            </td>
                            <td>
                                <div style="display:flex; gap:4px; flex-wrap:wrap">
                                    <?php if (empty($topProtos)): ?>
                                        <span style="font-size:10px; color:var(--muted)">Analyzing...</span>
                                    <?php else: ?>
                                        <?php foreach ($topProtos as $tp): ?>
                                            <span class="badge info" style="font-size:9px; padding:2px 6px;">
                                                <?= h($tp['app_proto'] ?: 'Unknown') ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="throughput-bar">
                                    <div class="fill" style="width: <?= min(100, ($h['throughput_bps'] / 10000000)) ?>%"></div>
                                </div>
                                <div style="font-size:11px;margin-top:4px; color:var(--text-muted)"><?= format_bps((int)$h['throughput_bps']) ?></div>
                            </td>
                            <td style="color:var(--text)"><?= format_bytes((int)$h['total_bytes']) ?></td>
                            <td style="font-size:11px; color:var(--text)" class="last-update" data-time="<?= strtotime($h['last_seen']) ?>">
                                <?= date('H:i:s', strtotime($h['last_seen'])) ?>
                            </td>
                            <td>
                                <span class="score-badge <?= $h['threat_score'] > 50 ? 'warning' : 'success' ?>">
                                    <?= 100 - (int)$h['threat_score'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.tag-vlan { background: var(--bg); border: 1px solid var(--border); padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; color: var(--text); }
.throughput-bar { width: 80px; height: 6px; background: var(--bg); border-radius: 3px; overflow: hidden; }
.throughput-bar .fill { height: 100%; background: var(--primary); }
.score-badge { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 800; }
.score-badge.success { background: rgba(39,196,168,0.2); color: var(--primary); }
.score-badge.warning { background: rgba(255,165,0,0.2); color: orange; }

.badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
.badge.info { background: rgba(0,123,255,0.15); color: #007bff; }
.badge.primary { background: rgba(39,196,168,0.15); color: var(--primary); }
.btn-deep { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: var(--primary); color: #fff; text-decoration: none; transition: opacity 0.2s; }
.btn-deep:hover { opacity: 0.8; }
.table th { color: var(--text); border-bottom: 2px solid var(--border); }
.table td { color: var(--text); }
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
});

function runDiscovery(btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Running... ⏳';
    
    fetch('../scripts/dflow_snmp_collector.php')
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.text();
        })
        .then(() => {
            btn.innerHTML = 'Done! ✅';
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        })
        .catch(err => {
            console.error(err);
            btn.innerHTML = 'Error ❌';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }, 2000);
        });
}
</script>

<?php 
if (!$isEmbed) {
    render_footer(); 
} else {
    echo '</body></html>';
}
?>
