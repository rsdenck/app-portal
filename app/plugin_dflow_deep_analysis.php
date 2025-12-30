<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/dflow_deep_lib.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$ip = $_GET['ip'] ?? '';
if (empty($ip)) {
    die("IP address is required.");
}

$action = $_POST['action'] ?? '';
$message = '';
$error = '';

if ($action === 'analyze') {
    $result = deep_analyze_ip($pdo, $ip);
    if ($result['success']) {
        $message = "Native DPI synchronization completed. System is monitoring $ip.";
    } else {
        $error = $result['error'];
    }
}

// Fetch analysis history for this IP
$history = $pdo->prepare("SELECT * FROM plugin_dflow_deep_analysis WHERE ip_address = ? ORDER BY timestamp DESC LIMIT 200");
$history->execute([$ip]);
$insights = $history->fetchAll();

render_header('DFlow · Deep Analysis: ' . $ip, $user);
?>

<div class="container-fluid">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="margin:0; color:var(--text)">Deep Packet Inspection: <span style="color:var(--primary)"><?= h($ip) ?></span></h1>
        <a href="plugin_dflow_hosts.php" class="btn secondary">Back to Hosts</a>
    </div>

    <?php if ($message): ?>
        <div class="alert success" style="margin-bottom:20px;"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert danger" style="margin-bottom:20px;"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <h3>Native DPI Automation</h3>
                <p class="muted">DFlow Engine is performing continuous Deep Packet Inspection for this host. No manual action is required.</p>
                <div style="padding: 15px; background: rgba(39,196,168,0.1); border-radius: 8px; border: 1px solid var(--primary); margin-bottom: 15px;">
                    <div style="font-weight: 700; color: var(--primary); margin-bottom: 5px;">⚡ Active Monitoring</div>
                    <div style="font-size: 12px;">Protocol categorization and L7 analysis are being collected automatically.</div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="analyze">
                    <button type="submit" class="btn primary btn-block" id="analyzeBtn">
                        Sync Latest Insights 🔄
                    </button>
                </form>
            </div>

            <div class="card" style="margin-top:20px;">
                <h3>Engine Status</h3>
                <ul class="status-list">
                    <li>
                        <span>Native Collector:</span>
                        <span class="badge success">Running</span>
                    </li>
                    <li>
                        <span>DPI Mode:</span>
                        <span class="badge info">L7 State-aware</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <h3>Analysis Results</h3>
                <?php if (empty($insights)): ?>
                    <div style="text-align:center; padding:60px;" class="muted">
                        <i style="font-size:48px; display:block; margin-bottom:15px;">🔍</i>
                        No deep analysis data found for this IP.<br>
                        The Native DFlow engine is collecting data. Try syncing again in a few moments.
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Protocol</th>
                                    <th>Insight</th>
                                    <th>Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($insights as $row): ?>
                                    <tr>
                                        <td style="font-size:11px;"><?= date('H:i:s', strtotime($row['timestamp'])) ?></td>
                                        <td><span class="badge info"><?= h($row['analysis_type']) ?></span></td>
                                        <td><?= h($row['protocol']) ?></td>
                                        <td>
                                            <div style="font-weight:700; font-size:12px;"><?= h($row['detail_key']) ?></div>
                                            <div style="font-family:monospace; font-size:11px; word-break:break-all;"><?= h($row['detail_value']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge-sev <?= h($row['severity']) ?>">
                                                <?= h($row['severity']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.status-list { list-style:none; padding:0; margin:0; }
.status-list li { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); }
.badge { padding:2px 8px; border-radius:4px; font-size:11px; }
.badge.success { background:rgba(39,196,168,0.2); color:var(--primary); }
.badge.danger { background:rgba(255,0,0,0.1); color:red; }
.badge.info { background:rgba(0,123,255,0.1); color:#007bff; }
.badge-sev { padding:2px 6px; border-radius:4px; font-size:10px; font-weight:800; text-transform:uppercase; }
.badge-sev.info { background: #eee; color: #666; }
.badge-sev.low { background: #d4edda; color: #155724; }
.badge-sev.medium { background: #fff3cd; color: #856404; }
.badge-sev.high { background: #f8d7da; color: #721c24; }
.btn-block { width:100%; display:block; margin-top:10px; }
</style>

<script>
document.getElementById('analyzeBtn').addEventListener('click', function() {
    this.innerHTML = 'Syncing Native Engine Data... ⏳';
    this.classList.add('disabled');
});
</script>

<?php render_footer(); ?>
