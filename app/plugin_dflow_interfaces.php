<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';

// Fetch Interfaces
$interfaces = $pdo->query("SELECT * FROM plugin_dflow_interfaces ORDER BY if_index ASC")->fetchAll();

if (!$isEmbed) {
    render_header('DFlow · Interfaces', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent;' : '' ?> color:var(--text);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2 style="margin:0; color:var(--text);">Network Interfaces</h2>
        <div style="display:flex;gap:10px">
            <span class="badge success">Active Interfaces: <?= count($interfaces) ?></span>
            <span class="badge info">Capture: libpcap / PF_RING</span>
        </div>
    </div>

    <div class="table-container">
        <table class="table" style="color:var(--text)">
            <thead>
                <tr>
                    <th style="color:var(--text)">Index</th>
                    <th style="color:var(--text)">Interface Name</th>
                    <th style="color:var(--text)">Status</th>
                    <th style="color:var(--text)">Speed</th>
                    <th style="color:var(--text)">Traffic In</th>
                    <th style="color:var(--text)">Traffic Out</th>
                    <th style="color:var(--text)">Packets In/Out</th>
                    <th style="color:var(--text)">Last Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($interfaces)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px; color:var(--text)">
                            <div class="muted">No interfaces detected. Check collector status.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($interfaces as $if): ?>
                        <tr>
                            <td style="color:var(--text)">#<?= $if['if_index'] ?></td>
                            <td>
                                <div style="font-weight:700; color:var(--text)"><?= h($if['name']) ?></div>
                                <div style="font-size:11px;color:var(--muted)"><?= h($if['description'] ?: 'No description') ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $if['status'] === 'up' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($if['status']) ?>
                                </span>
                            </td>
                            <td style="color:var(--text)"><?= number_format($if['speed'] / 1000000, 0) ?> Mbps</td>
                            <td style="color:var(--primary);font-weight:600">
                                <?= format_bytes($if['in_bytes']) ?>
                            </td>
                            <td style="color:var(--warning);font-weight:600">
                                <?= format_bytes($if['out_bytes']) ?>
                            </td>
                            <td style="color:var(--text)">
                                <div style="font-size:12px">In: <?= number_format($if['in_packets']) ?></div>
                                <div style="font-size:12px">Out: <?= number_format($if['out_packets']) ?></div>
                            </td>
                            <td style="font-size:11px; color:var(--text)"><?= date('H:i:s', strtotime($if['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge.success { background: rgba(39,196,168,0.15); color: var(--primary); }
.badge.danger { background: rgba(255,90,95,0.15); color: var(--danger); }
.badge.info { background: rgba(0,123,255,0.15); color: #007bff; }
.muted { color: var(--muted) !important; }
</style>

<?php 
if (!$isEmbed) {
    render_footer(); 
} else {
    echo '</body></html>';
}
?>
