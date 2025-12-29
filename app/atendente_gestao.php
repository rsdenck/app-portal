<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$ticketCounts = ticket_counts_global($pdo);
$attendantSla = ticket_sla_stats_for_attendants($pdo);
$volume = ticket_volume_last_days($pdo, 7);

try {
    $zbxConfig = zbx_config_from_db($pdo, $config);
    $zbxHostsTotal = 0;
    try {
        $auth = zbx_auth($zbxConfig);
        $hosts = zbx_rpc(
            $zbxConfig,
            'host.get',
            [
                'output' => ['hostid'],
            ],
            $auth
        );
        if (is_array($hosts)) {
            $zbxHostsTotal = count($hosts);
        }
    } catch (Throwable $e) {
        $zbxHostsTotal = 0;
    }
} catch (Throwable $e) {
    $zbxHostsTotal = 0;
}

render_header('Atendente · Gestão', current_user());
?>
<div class="card" style="margin-bottom:18px">

  <div class="dashboard-grid">
    <div>
      <div class="dashboard-panel-title">Resumo de monitoramento</div>
      <div class="dashboard-panel-subtitle">Hosts retornados pela API do Zabbix.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Total de hosts monitorados</div>
        </div>
        <div class="config-tile-tag"><?= (int)$zbxHostsTotal ?></div>
      </div>
    </div>
    <div>
      <div class="dashboard-panel-title">Chamados abertos</div>
      <div class="dashboard-panel-subtitle">Status Aberto e Agendado.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Abertos agora</div>
        </div>
        <div class="config-tile-tag">
          <?= (int)(($ticketCounts['aberto'] ?? 0) + ($ticketCounts['agendado'] ?? 0)) ?>
        </div>
      </div>
    </div>
    <div>
      <div class="dashboard-panel-title">Chamados encerrados</div>
      <div class="dashboard-panel-subtitle">Encerrado e Fechado.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Total encerrado</div>
        </div>
        <div class="config-tile-tag">
          <?= (int)(($ticketCounts['encerrado'] ?? 0) + ($ticketCounts['fechado'] ?? 0)) ?>
        </div>
      </div>
    </div>
    <div>
      <div class="dashboard-panel-title">Chamados suspensos</div>
      <div class="dashboard-panel-subtitle">Status Suspenso.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Total suspenso</div>
        </div>
        <div class="config-tile-tag">
          <?= (int)($ticketCounts['suspenso'] ?? 0) ?>
        </div>
      </div>
    </div>
    <div>
      <div class="dashboard-panel-title">Chamados contestados</div>
      <div class="dashboard-panel-subtitle">Status Contestado.</div>
      <div class="config-tile">
        <div class="config-tile-main">
          <div class="config-tile-title">Total contestado</div>
        </div>
        <div class="config-tile-tag">
          <?= (int)($ticketCounts['contestado'] ?? 0) ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="dashboard-grid">
  <div class="card">
    <div class="dashboard-panel-title">Tickets por status</div>
    <div class="dashboard-panel-subtitle">Distribuição atual por status.</div>
    <div style="height: 300px; position: relative;">
      <canvas id="statusChart"></canvas>
    </div>
    <?php
      $statusMap = [
        'aberto' => 'Aberto',
        'agendado' => 'Agendado',
        'suspenso' => 'Suspenso',
        'aguardando_cotacao' => 'Aguardando cotação',
        'contestado' => 'Contestado',
        'encerrado' => 'Encerrado',
        'fechado' => 'Fechado',
      ];
      $labels = [];
      $data = [];
      $colors = ['#27c4a8', '#3498db', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#34495e'];
      foreach ($statusMap as $slug => $label) {
        $labels[] = $label;
        $data[] = (int)($ticketCounts[$slug] ?? 0);
      }
    ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('statusChart'), {
          type: 'doughnut',
          data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
              data: <?= json_encode($data) ?>,
              backgroundColor: <?= json_encode($colors) ?>,
              borderWidth: 0
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { position: 'bottom', labels: { color: '#8aa0b4', font: { size: 11 } } },
              tooltip: {
                backgroundColor: '#161d27',
                titleColor: '#27c4a8',
                bodyColor: '#fff',
                borderColor: '#27c4a8',
                borderWidth: 1,
                padding: 10,
                displayColors: true
              }
            }
          }
        });
      });
    </script>
  </div>
  <div class="card">
    <div class="dashboard-panel-title">Volume de tickets (últimos 7 dias)</div>
    <div class="dashboard-panel-subtitle">Contagem diária de tickets criados.</div>
    <div style="height: 300px; position: relative;">
      <canvas id="volumeChart"></canvas>
    </div>
    <?php
      $volLabels = [];
      $volData = [];
      foreach ($volume as $row) {
        $volLabels[] = date('d/m', strtotime($row['day']));
        $volData[] = (int)$row['total'];
      }
    ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('volumeChart'), {
          type: 'line',
          data: {
            labels: <?= json_encode($volLabels) ?>,
            datasets: [{
              label: 'Tickets Criados',
              data: <?= json_encode($volData) ?>,
              borderColor: '#27c4a8',
              backgroundColor: 'rgba(39, 196, 168, 0.1)',
              fill: true,
              tension: 0.4
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
  <div class="card">
    <div class="dashboard-panel-title">Ranking de atendentes</div>
    <div class="dashboard-panel-subtitle">Volume de chamados e tempo médio de resolução.</div>
    <table class="table">
      <thead>
        <tr>
          <th>Atendente</th>
          <th>Total</th>
          <th>Encerrados</th>
          <th>1ª Resposta (min)</th>
          <th>Resolução (min)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$attendantSla): ?>
          <tr><td colspan="5" class="muted">Sem dados de chamados para exibir ranking.</td></tr>
        <?php endif; ?>
        <?php foreach ($attendantSla as $row): ?>
          <tr>
            <td><?= h((string)($row['attendant_name'] ?? 'Sem atendente')) ?></td>
            <td><?= (int)($row['total_tickets'] ?? 0) ?></td>
            <td><?= (int)($row['closed_tickets'] ?? 0) ?></td>
            <td>
              <?php
                $avgFirst = $row['avg_first_response_minutes'] ?? null;
                if ($avgFirst === null) {
                    echo 'N/A';
                } else {
                    echo h(number_format((float)$avgFirst, 1, ',', '.'));
                }
              ?>
            </td>
            <td>
              <?php
                $avgRes = $row['avg_resolution_minutes'] ?? null;
                if ($avgRes === null) {
                    echo 'N/A';
                } else {
                    echo h(number_format((float)$avgRes, 1, ',', '.'));
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
render_footer();



