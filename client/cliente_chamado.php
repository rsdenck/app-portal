<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('cliente');
$clientId = (int)$user['id'];
$tickets = ticket_list_for_client($pdo, $clientId);
$counts = ticket_counts_for_client($pdo, $clientId);
$volume = ticket_volume_last_days_for_client($pdo, $clientId, 7);
$sla = ticket_sla_for_client($pdo, $clientId);

render_header('Cliente · Chamados', current_user());
?>
<div class="card">
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <a class="btn primary" href="/client/cliente_abrir_ticket.php">Abrir chamado</a>
  </div>
  <div class="dashboard-grid" style="margin-bottom:16px">
    <div class="card">
      <div class="dashboard-panel-title">Total de chamados</div>
      <div class="dashboard-panel-subtitle">Todos os chamados deste cliente.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Total</div>
        </div>
        <div class="config-tile-tag"><?= (int)count($tickets) ?></div>
      </div>
    </div>
    <div class="card">
      <div class="dashboard-panel-title">Chamados suspensos</div>
      <div class="dashboard-panel-subtitle">Status Suspenso.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Total suspenso</div>
        </div>
        <div class="config-tile-tag"><?= (int)($counts['suspenso'] ?? 0) ?></div>
      </div>
    </div>
    <div class="card">
      <div class="dashboard-panel-title">Monitoramento</div>
      <div class="dashboard-panel-subtitle">Total de ativos monitorados.</div>
      <?php
        $zbxHostsClient = 0;
        try {
            $zbxConfig = zbx_config_from_db($pdo, $config);
            $auth = zbx_auth($zbxConfig);
            $hgName = get_client_hostgroup($pdo, $clientId);
            if ($hgName) {
                $hgs = zbx_rpc($zbxConfig, 'hostgroup.get', ['output' => ['groupid'], 'filter' => ['name' => $hgName]], $auth);
                if ($hgs) {
                    $hosts = zbx_rpc($zbxConfig, 'host.get', ['output' => ['hostid'], 'groupids' => $hgs[0]['groupid']], $auth);
                    $zbxHostsClient = count($hosts);
                }
            }
        } catch (Throwable $e) {}
      ?>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Ativos reais</div>
        </div>
        <div class="config-tile-tag"><?= (int)$zbxHostsClient ?></div>
      </div>
    </div>
    <div class="card">
      <div class="dashboard-panel-title">SLA real</div>
      <div class="dashboard-panel-subtitle">Tempo médio de resolução.</div>
      <?php
        $closedClient = (int)($sla['closed_tickets'] ?? 0);
        $avgMinutesClient = $sla['avg_resolution_minutes'] ?? null;
        $slaLabel = 'N/A';
        if ($closedClient > 0 && $avgMinutesClient !== null) {
          $minutesInt = (int)round($avgMinutesClient);
          if ($minutesInt < 1) $slaLabel = '< 1 min';
          else {
            $hours = intdiv($minutesInt, 60);
            $mins = $minutesInt % 60;
            $slaLabel = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
          }
        }
      ?>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">SLA Médio</div>
        </div>
        <div class="config-tile-tag"><?= h($slaLabel) ?></div>
      </div>
    </div>
  </div>

  <div class="dashboard-grid" style="margin-bottom:24px">
    <div class="card">
      <div class="dashboard-panel-title">Chamados por status</div>
      <div class="dashboard-panel-subtitle">Situação atual.</div>
      <div style="height: 250px; position: relative;">
        <canvas id="clientStatusChart"></canvas>
      </div>
      <?php
        $statusMap = ['aberto'=>'Aberto','agendado'=>'Agendado','suspenso'=>'Suspenso','aguardando_cotacao'=>'Aguardando cotação','contestado'=>'Contestado','encerrado'=>'Encerrado','fechado'=>'Fechado'];
        $labels = []; $data = [];
        foreach ($statusMap as $slug => $label) {
          $labels[] = $label;
          $data[] = (int)($counts[$slug] ?? 0);
        }
      ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          new Chart(document.getElementById('clientStatusChart'), {
            type: 'pie',
            data: {
              labels: <?= json_encode($labels) ?>,
              datasets: [{
                data: <?= json_encode($data) ?>,
                backgroundColor: ['#27c4a8', '#3498db', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#34495e'],
                borderWidth: 0
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { 
                legend: { position: 'right', labels: { color: '#8aa0b4', font: { size: 10 } } },
                tooltip: {
                  backgroundColor: '#161d27',
                  titleColor: '#27c4a8',
                  bodyColor: '#fff',
                  borderColor: '#27c4a8',
                  borderWidth: 1,
                  padding: 10
                }
              }
            }
          });
        });
      </script>
    </div>
    <div class="card">
      <div class="dashboard-panel-title">Volume de tickets (7 dias)</div>
      <div class="dashboard-panel-subtitle">Histórico de abertura.</div>
      <div style="height: 250px; position: relative;">
        <canvas id="clientVolumeChart"></canvas>
      </div>
      <?php
        $volLabels = []; $volData = [];
        foreach ($volume as $row) {
          $volLabels[] = date('d/m', strtotime($row['day']));
          $volData[] = (int)$row['total'];
        }
      ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          new Chart(document.getElementById('clientVolumeChart'), {
            type: 'bar',
            data: {
              labels: <?= json_encode($volLabels) ?>,
              datasets: [{
                label: 'Tickets',
                data: <?= json_encode($volData) ?>,
                backgroundColor: 'rgba(39, 196, 168, 0.6)',
                borderRadius: 4
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                y: { beginAtZero: true, grid: { color: '#1f2a3a' }, ticks: { color: '#8aa0b4' } },
                x: { grid: { display: false }, ticks: { color: '#8aa0b4' } }
              },
              plugins: { 
                legend: { display: false },
                tooltip: {
                  backgroundColor: '#161d27',
                  titleColor: '#27c4a8',
                  bodyColor: '#fff',
                  borderColor: '#27c4a8',
                  borderWidth: 1,
                  padding: 10
                }
              }
            }
          });
        });
      </script>
    </div>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Assunto</th>
        <th>Categoria</th>
        <th>Status</th>
        <th>Atendente</th>
        <th>Criado</th>
        <th style="width: 80px">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$tickets): ?>
        <tr><td colspan="7" class="muted">Nenhum chamado encontrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($tickets as $t): ?>
        <?php 
            $m = ticket_calculate_metrics($t, $pdo); 
            $hasUnread = ticket_has_unread($pdo, (int)$t['id'], (int)$user['id']);
        ?>
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 8px">
                <?= (int)$t['id'] ?>
                <?php if ($hasUnread): ?>
                    <div title="Nova interação" style="background: #ff0000; color: #fff; width: 18px; height: 18px; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; animation: pulse 1.5s infinite; box-shadow: 0 0 8px rgba(255,0,0,0.6)">
                        !
                    </div>
                <?php endif; ?>
            </div>
          </td>
          <td style="<?= $hasUnread ? 'font-weight: 700' : '' ?>">
            <?= h((string)$t['subject']) ?>
          </td>
          <td><span class="badge"><?= h((string)$t['category_name']) ?></span></td>
          <td>
            <span class="badge"><?= h((string)$t['status_name']) ?></span>
            <br>
            <span style="font-size:0.8em" class="muted <?= $m['sla_status'] === 'Dentro do Prazo' ? 'text-success' : 'text-danger' ?>">
                SLA: <?= $m['sli_formatted'] ?>
            </span>
          </td>
          <td><?= h((string)$t['assigned_name'] ?: 'Aguardando') ?></td>
          <td><?= h((string)$t['created_at']) ?></td>
          <td>
            <a href="/client/cliente_ticket.php?id=<?= (int)$t['id'] ?>" class="btn primary small">Visualizar</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();



