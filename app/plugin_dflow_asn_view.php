<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

// Filtros
$timeRange = $_GET['range'] ?? '1h';
$intervalMap = ['1h' => '1 HOUR', '24h' => '24 HOUR', '7d' => '7 DAY'];
$interval = $intervalMap[$timeRange] ?? '1 HOUR';

// Query: Top ASNs por Volume
$sqlTopAsn = "SELECT a.asn_number, a.organization, a.country, 
              SUM(f.bytes) as total_bytes, COUNT(f.id) as flow_count
              FROM plugin_dflow_asns a
              JOIN plugin_dflow_flow_asn_map m ON (a.asn_id = m.src_asn_id OR a.asn_id = m.dst_asn_id)
              JOIN plugin_dflow_flows f ON m.flow_id = f.id
              WHERE f.ts >= NOW() - INTERVAL $interval
              GROUP BY a.asn_id
              ORDER BY total_bytes DESC
              LIMIT 20";
$topAsns = $pdo->query($sqlTopAsn)->fetchAll();

render_header('DFlow ASN Intelligence', $user);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h3>ASN Traffic Intelligence</h3>
        <div class="btn-group">
            <a href="?range=1h" class="btn <?= $timeRange == '1h' ? 'btn-primary' : '' ?>">1h</a>
            <a href="?range=24h" class="btn <?= $timeRange == '24h' ? 'btn-primary' : '' ?>">24h</a>
            <a href="?range=7d" class="btn <?= $timeRange == '7d' ? 'btn-primary' : '' ?>">7d</a>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ASN</th>
                    <th>Organização</th>
                    <th>País</th>
                    <th>Fluxos</th>
                    <th>Volume Total</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topAsns as $asn): ?>
                    <tr>
                        <td><span class="tag-proto">AS<?= $asn['asn_number'] ?></span></td>
                        <td><?= h($asn['organization']) ?></td>
                        <td><?= h($asn['country']) ?></td>
                        <td><?= number_format($asn['flow_count']) ?></td>
                        <td><?= format_bytes($asn['total_bytes']) ?></td>
                        <td>
                            <a href="plugin_dflow_flows.php?asn=<?= $asn['asn_number'] ?>" class="btn btn-mini">Ver Fluxos</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2" style="margin-top:20px">
    <div class="card">
        <h3>Geographic Distribution (ASN Origin)</h3>
        <!-- Placeholder para Gráfico de Pizza por País -->
        <div style="height:200px;background:var(--bg-alt);display:flex;align-items:center;justify-content:center;border-radius:8px">
            [ Mapa de Calor BGP / Geo-Shift ]
        </div>
    </div>
    <div class="card">
        <h3>BGP Anomaly Monitor</h3>
        <ul class="list-unstyled">
            <li style="padding:10px;border-bottom:1px solid var(--border)">
                <span class="tag-proto" style="background:var(--danger)">HIJACK ALERT</span>
                <small>AS13335 anunciando prefixo de AS15169 (Google)</small>
            </li>
            <li style="padding:10px">
                <span class="tag-proto" style="background:var(--warning)">ROUTE LEAK</span>
                <small>Mudança súbita de AS-PATH detectada para prefixo 8.8.8.0/24</small>
            </li>
        </ul>
    </div>
</div>

<?php render_footer(); ?>
