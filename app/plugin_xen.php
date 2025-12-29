<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}

$pluginName = 'xen';
$pluginLabel = 'XEN API';
$plugin = plugin_get_by_name($pdo, $pluginName);

if (!$plugin || !$plugin['is_active']) {
    header('Location: /');
    exit;
}

render_header($pluginLabel . ' · Virtualização', $user);
?>

<div class="card" style="padding:40px; text-align:center; margin-top:20px">
    <div style="font-size: 48px; margin-bottom: 20px;">🏗️</div>
    <h3 class="muted">Plugin <?= h($pluginLabel) ?> em Desenvolvimento</h3>
    <p>A configuração da API foi detectada, mas o coletor de dados ainda não foi implementado para este virtualizador.</p>
    
    <div style="margin-top:30px; display:inline-block; text-align:left; background:var(--input-bg); padding:15px; border-radius:8px; border:1px solid var(--border)">
        <div style="font-size:11px; text-transform:uppercase; opacity:0.6; margin-bottom:10px; font-weight:700">Audit Info</div>
        <div style="font-size:12px; margin-bottom:5px"><strong>API Endpoint:</strong> <?= h($plugin['config']['url'] ?? 'N/A') ?></div>
        <div style="font-size:12px; margin-bottom:5px"><strong>Status:</strong> <span class="badge warning">GAP: No Collector</span></div>
    </div>
</div>

<?php
render_footer();
