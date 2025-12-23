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

render_header('Veeam · Jobs', $user);
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="font-weight:700;font-size:18px">Jobs de Backup</div>
    <div style="display:flex;gap:10px">
      <input type="text" placeholder="Filtrar jobs..." class="btn" style="background:var(--input-bg);cursor:text">
      <button class="btn primary">Sincronizar API</button>
    </div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Nome do Job</th>
        <th>Tipo</th>
        <th>Último Resultado</th>
        <th>Duração</th>
        <th>Próxima Execução</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Backup_SRV_PROD_SQL</td>
        <td>VM Backup</td>
        <td><span class="badge success">Sucesso</span></td>
        <td>45m 12s</td>
        <td>Hoje 22:00</td>
      </tr>
      <tr>
        <td>Backup_SRV_FILE_SERVER</td>
        <td>VM Backup</td>
        <td><span class="badge success">Sucesso</span></td>
        <td>02h 15m</td>
        <td>Hoje 23:30</td>
      </tr>
      <tr>
        <td>Backup_SRV_APP_WEB</td>
        <td>VM Backup</td>
        <td><span class="badge danger">Falha</span></td>
        <td>12m 05s</td>
        <td>Imediato (Retry)</td>
      </tr>
    </tbody>
  </table>
</div>

<?php
render_footer();
