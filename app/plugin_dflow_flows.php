<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Fetch Flows
$flows = $pdo->query("SELECT * FROM plugin_dflow_flows ORDER BY ts DESC LIMIT 100")->fetchAll();

if (!$isEmbed) {
    render_header('DFlow · Flows', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent; color:inherit;' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2 style="margin:0; color:var(--text)">IP Flows</h2>
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
                            <td style="font-size:11px; color:var(--text-muted)"><?= date('H:i:s', (int)$f['ts']) ?></td>
                            <td>
                                <div style="font-weight:600; color:var(--text)"><?= h((string)$f['src_ip']) ?></div>
                                <div style="font-size:10px;color:var(--muted)">Port: <?= (int)$f['src_port'] ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text)"><?= h((string)$f['dst_ip']) ?></div>
                                <div style="font-size:10px;color:var(--muted)">Port: <?= (int)$f['dst_port'] ?></div>
                            </td>
                            <td><span class="tag-proto"><?= h((string)$f['proto']) ?></span></td>
                            <td><span class="tag-app"><?= h((string)($f['app_proto'] ?: 'Unknown')) ?></span></td>
                            <td style="color:var(--text)"><?= format_bytes($f['bytes']) ?></td>
                            <td style="color:var(--text)"><?= (int)$f['pkts'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
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
