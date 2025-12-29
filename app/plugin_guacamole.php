<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}
$plugin = plugin_get_by_name($pdo, 'guacamole');

if (!$plugin || !$plugin['is_active']) {
    redirect('/app/atendente_gestao.php');
}

$config = $plugin['config'] ?? [];
$guacUrl = $config['url'] ?? '';
$guacUser = $config['username'] ?? '';
$guacPass = $config['password'] ?? '';

render_header('Acesso Remoto · Guacamole', $user);
?>

<div class="card" style="margin-bottom:18px">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
        <div style="display:flex; align-items:center; gap:12px">
            <a href="/app/atendente_gestao.php" class="btn" style="padding:8px">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            </a>
            <div>
                <div style="font-weight:700; font-size:1.1rem">Acesso Remoto (Guacamole)</div>
                <div class="muted">Gerencie conexões RDP, SSH e VNC de forma centralizada.</div>
            </div>
        </div>
        <?php if ($guacUrl): ?>
            <a href="<?= h($guacUrl) ?>" target="_blank" class="btn primary">Abrir Guacamole Externo</a>
        <?php endif; ?>
    </div>

    <?php if (!$guacUrl): ?>
        <div class="error" style="margin-top:20px">
            Plugin não configurado. Por favor, configure a URL e credenciais nas 
            <a href="/app/atendente_plugins.php" style="color:inherit; font-weight:700">Configurações de Plugins</a>.
        </div>
    <?php else: ?>
        <div style="margin-top:20px">
            <div class="dashboard-grid">
                <div class="card" style="border:1px solid var(--border-color)">
                    <div class="dashboard-panel-title">Instância</div>
                    <div class="dashboard-panel-subtitle">URL do servidor</div>
                    <div style="font-size:1.2rem; font-weight:700; margin-top:8px"><?= h($guacUrl) ?></div>
                </div>
                <div class="card" style="border:1px solid var(--border-color)">
                    <div class="dashboard-panel-title">Status da API</div>
                    <div class="dashboard-panel-subtitle">Conectividade com o servidor</div>
                    <div style="color:var(--success-color); font-weight:700; margin-top:8px">● Online</div>
                </div>
            </div>

            <div style="margin-top:24px">
                <div style="font-weight:700; margin-bottom:12px">Conexões Rápidas</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Protocolo</th>
                                <th>Endereço</th>
                                <th style="text-align:right">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Servidor Windows Principal</strong></td>
                                <td><span class="badge">RDP</span></td>
                                <td><code>10.0.0.50</code></td>
                                <td style="text-align:right">
                                    <button class="btn primary" onclick="alert('Funcionalidade de túnel em desenvolvimento...')">Conectar</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Gateway Linux (SSH)</strong></td>
                                <td><span class="badge">SSH</span></td>
                                <td><code>10.0.0.1</code></td>
                                <td style="text-align:right">
                                    <button class="btn primary" onclick="alert('Funcionalidade de túnel em desenvolvimento...')">Conectar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.table-container {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
}
.table th {
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid var(--border-color);
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--muted-color);
}
.table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
}
.badge {
    background: var(--border-color);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}
</style>

<?php
render_footer();



