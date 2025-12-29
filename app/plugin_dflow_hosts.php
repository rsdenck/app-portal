<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Fetch Hosts
$hosts = $pdo->query("SELECT * FROM plugin_dflow_hosts ORDER BY throughput_bps DESC LIMIT 100")->fetchAll();

if (!$isEmbed) {
    render_header('DFlow · Hosts', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>DFlow · Hosts</title><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent; color:inherit;' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2 style="margin:0; color:var(--text)">Active Hosts</h2>
        <div style="display:flex;gap:10px">
            <span class="badge info">nDPI Analysis: Enabled</span>
            <span class="badge primary">Geolocation: Enabled</span>
        </div>
    </div>

    <div class="table-container">
        <table class="table" style="color:var(--text)">
            <thead>
                <tr>
                    <th style="color:var(--text)">IP Address</th>
                    <th style="color:var(--text)">Hostname / MAC</th>
                    <th style="color:var(--text)">VLAN</th>
                    <th style="color:var(--text)">ASN / Country</th>
                    <th style="color:var(--text)">Throughput</th>
                    <th style="color:var(--text)">Total Traffic</th>
                    <th style="color:var(--text)">Active Flows</th>
                    <th style="color:var(--text)">Score</th>
                    <th style="color:var(--text)">Deep Analysis</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hosts)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px">
                            <div class="muted">No hosts detected in the last hour.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($hosts as $h): ?>
                        <tr>
                            <td>
                                <div style="font-weight:800;color:var(--primary)"><?= h((string)$h['ip_address']) ?></div>
                            </td>
                            <td>
                                <div style="font-size:13px; color:var(--text)"><?= h((string)($h['hostname'] ?: 'Unknown')) ?></div>
                                <div style="font-size:10px;color:var(--muted)"><?= h((string)($h['mac_address'] ?? '')) ?></div>
                            </td>
                            <td><span class="tag-vlan"><?= (int)$h['vlan'] ?></span></td>
                            <td>
                                <div style="font-size:12px; color:var(--text)">AS<?= (int)$h['asn'] ?></div>
                                <div style="font-size:10px; color:var(--text-muted)"><?= h((string)($h['country_code'] ?? '')) ?> 🚩</div>
                            </td>
                            <td>
                                <div class="throughput-bar">
                                    <div class="fill" style="width: <?= min(100, ($h['throughput_bps'] / 10000000)) ?>%"></div>
                                </div>
                                <div style="font-size:11px;margin-top:4px; color:var(--text-muted)"><?= format_bps($h['throughput_bps']) ?></div>
                            </td>
                            <td style="color:var(--text)"><?= format_bytes($h['total_bytes']) ?></td>
                            <td style="color:var(--text)"><?= (int)$h['active_flows'] ?></td>
                            <td>
                                <span class="score-badge <?= $h['active_flows'] > 50 ? 'warning' : 'success' ?>">
                                    <?= 100 - min(50, (int)$h['active_flows']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="plugin_dflow_deep_analysis.php?ip=<?= urlencode((string)$h['ip_address']) ?>" class="btn-deep">
                                    Analyze 🔍
                                </a>
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

<?php 
if (!$isEmbed) {
    render_footer(); 
} else {
    echo '</body></html>';
}
?>
