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

render_header('Veeam · Dashboard', $user);
?>

<div class="card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div style="font-weight:700;font-size:18px">Dashboard Veeam Backup & Replication</div>
    <div class="plugin-category">VCSP Consolidation</div>
  </div>

  <div class="config-grid">
    <div class="config-tile no-hover">
      <div class="config-tile-main">
        <div class="config-tile-title" style="font-size:24px;color:var(--primary)">24</div>
        <div class="config-tile-desc">Total de Jobs</div>
      </div>
    </div>
    <div class="config-tile no-hover">
      <div class="config-tile-main">
        <div class="config-tile-title" style="font-size:24px;color:#27c4a8">22</div>
        <div class="config-tile-desc">Jobs com Sucesso</div>
      </div>
    </div>
    <div class="config-tile no-hover">
      <div class="config-tile-main">
        <div class="config-tile-title" style="font-size:24px;color:var(--danger)">2</div>
        <div class="config-tile-desc">Jobs Falhos</div>
      </div>
    </div>
    <div class="config-tile no-hover">
      <div class="config-tile-main">
        <div class="config-tile-title" style="font-size:24px;color:var(--muted)">120/s</div>
        <div class="config-tile-desc">Rate Limit da API</div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col">
    <div class="card">
      <div style="font-weight:700;margin-bottom:12px">Repositórios de Armazenamento</div>
      <table class="table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Capacidade</th>
            <th>Livre</th>
            <th>Uso</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Repo-Principal-01</td>
            <td>10 TB</td>
            <td>2.4 TB</td>
            <td>
              <div style="width:100%;background:var(--border);height:8px;border-radius:4px">
                <div style="width:76%;background:var(--primary);height:100%;border-radius:4px"></div>
              </div>
            </td>
          </tr>
          <tr>
            <td>Repo-Secundario-02</td>
            <td>20 TB</td>
            <td>15.1 TB</td>
            <td>
              <div style="width:100%;background:var(--border);height:8px;border-radius:4px">
                <div style="width:24%;background:var(--primary);height:100%;border-radius:4px"></div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
.no-hover {
  cursor: default !important;
}
.no-hover:hover {
  border-color: var(--border) !important;
  box-shadow: none !important;
}
.plugin-category {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: var(--border);
  padding: 4px 10px;
  border-radius: 6px;
  color: var(--muted);
  font-weight: 600;
}
</style>

<?php
render_footer();
