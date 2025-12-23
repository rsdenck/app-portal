<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}
$plugin = plugin_get_by_name($pdo, 'veeam');

if (!$plugin || !$plugin['is_active']) {
    header('Location: /');
    exit;
}

render_header('Veeam · Repositórios', $user);
?>

<div class="card">
  <div style="font-weight:700;font-size:18px;margin-bottom:16px">Repositórios de Armazenamento</div>

  <div class="config-grid">
    <div class="card" style="border:1px solid var(--border);background:rgba(255,255,255,0.01)">
      <div style="font-weight:600;margin-bottom:8px">Repo-Principal-01 (Scale-out)</div>
      <div class="muted" style="margin-bottom:12px">Localização: Datacenter Principal</div>
      <div style="font-size:20px;font-weight:700;margin-bottom:4px">7.6 TB / 10 TB</div>
      <div style="width:100%;background:var(--border);height:10px;border-radius:5px;margin-bottom:8px">
        <div style="width:76%;background:var(--primary);height:100%;border-radius:5px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px">
        <span class="muted">Em uso: 76%</span>
        <span style="color:var(--primary)">2.4 TB Livres</span>
      </div>
    </div>

    <div class="card" style="border:1px solid var(--border);background:rgba(255,255,255,0.01)">
      <div style="font-weight:600;margin-bottom:8px">Repo-Cloud-Archive</div>
      <div class="muted" style="margin-bottom:12px">Localização: Azure Blob Storage</div>
      <div style="font-size:20px;font-weight:700;margin-bottom:4px">4.2 TB / 50 TB</div>
      <div style="width:100%;background:var(--border);height:10px;border-radius:5px;margin-bottom:8px">
        <div style="width:8%;background:var(--primary);height:100%;border-radius:5px"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px">
        <span class="muted">Em uso: 8%</span>
        <span style="color:var(--primary)">45.8 TB Livres</span>
      </div>
    </div>
  </div>
</div>

<?php
render_footer();
