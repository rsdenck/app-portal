<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

// VLAN Context
$selectedVlan = isset($_GET['vlan']) ? (int)$_GET['vlan'] : 0;
$vlanList = $pdo->query("SELECT DISTINCT vlan FROM plugin_dflow_flows WHERE vlan > 0 ORDER BY vlan")->fetchAll(PDO::FETCH_COLUMN);

$vlanFilter = $selectedVlan > 0 ? " AND vlan = $selectedVlan" : "";
$vlanFilterWhere = $selectedVlan > 0 ? " WHERE vlan = $selectedVlan" : "";

// Stats
$stats = [
    'total_flows' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows" . $vlanFilterWhere)->fetchColumn(),
    'anomalies' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_flows WHERE anomaly_type IS NOT NULL" . $vlanFilter)->fetchColumn(),
    'hosts_active' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_hosts WHERE last_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR)" . $vlanFilter)->fetchColumn(),
    'interfaces' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_interfaces WHERE status = 'up'" . $vlanFilter)->fetchColumn()
];

// System Performance (Latest metrics)
// Check if plugin_dflow_system_metrics exists
$systemMetrics = $pdo->query("SELECT * FROM plugin_dflow_system_metrics WHERE timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY timestamp DESC LIMIT 10")->fetchAll();
$totalPPS = 0;
$totalBPS = 0;
$totalDrops = 0;
foreach ($systemMetrics as $m) {
    $totalPPS += $m['processed_packets'];
    $totalBPS += $m['processed_bytes'];
    $totalDrops += $m['dropped_packets'];
}
$avgPPS = count($systemMetrics) > 0 ? $totalPPS / count($systemMetrics) : 0;

// Recent flows
$recentFlows = $pdo->query("SELECT * FROM plugin_dflow_flows" . $vlanFilterWhere . " ORDER BY last_seen DESC LIMIT 10")->fetchAll();

// Topology data for D3 - Filtered by VLAN if selected
$topoQuery = $selectedVlan > 0 
    ? "SELECT src_ip as source, dst_ip as target, 5 as weight FROM plugin_dflow_flows WHERE vlan = $selectedVlan LIMIT 100"
    : "SELECT local_device_ip as source, remote_device_name as target, 5 as weight FROM plugin_dflow_topology";

$topologyLinks = $pdo->query($topoQuery)->fetchAll(PDO::FETCH_ASSOC);
$nodes = [];
foreach ($topologyLinks as $link) {
    $nodes[$link['source']] = ['id' => $link['source'], 'group' => 1];
    $nodes[$link['target']] = ['id' => $link['target'], 'group' => 2];
}
$topologyNodes = array_values($nodes);

render_header('DFlow · Flow-First Network Traffic Intelligence Platform', $user);
?>

<script src="https://d3js.org/d3.v7.min.js"></script>

<div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin:0">Flow-First Network Traffic Intelligence Platform</h2>
    <form method="GET" style="display:flex; gap:10px; align-items:center">
        <label for="vlan" style="font-weight:600">Contexto VLAN:</label>
        <select name="vlan" id="vlan" class="form-control" onchange="this.form.submit()" style="width:150px">
            <option value="0">Global (Todas)</option>
            <?php foreach ($vlanList as $v): ?>
                <option value="<?= $v ?>" <?= $selectedVlan == $v ? 'selected' : '' ?>>VLAN <?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px">
    <div class="summary-card">
        <span class="muted">Fluxos (Últimos 5min)</span>
        <div class="value"><?= $stats['total_flows'] ?></div>
        <div class="footer-note">Sessões ativas no momento</div>
    </div>
    <div class="summary-card danger">
        <span class="muted">Anomalias / CVEs</span>
        <div class="value"><?= $stats['anomalies'] ?></div>
        <div class="footer-note">Ameaças detectadas pelo motor L7</div>
    </div>
    <div class="summary-card">
        <span class="muted">Hosts Ativos (1h)</span>
        <div class="value"><?= $stats['hosts_active'] ?></div>
        <div class="footer-note">Dispositivos detectados</div>
    </div>
    <div class="summary-card primary">
        <span class="muted">Interfaces Ativas</span>
        <div class="value"><?= $stats['interfaces'] ?></div>
        <div class="footer-note">Coleta via libpcap/DPDK</div>
    </div>
    <div class="summary-card">
        <span class="muted">Performance (PPS)</span>
        <div class="value"><?= number_format($avgPPS) ?></div>
        <div class="footer-note">Média de pacotes/seg</div>
    </div>
    <div class="summary-card info" onclick="location.href='plugin_dflow_asn_view.php'" style="cursor:pointer">
        <span class="muted">ASN Intelligence</span>
        <div class="value">BGP</div>
        <div class="footer-note">Ver Top ASNs e Rotas</div>
    </div>
</div>

<div style="display:grid;grid-template-columns: 2fr 1fr;gap:30px">
    <!-- Network Topology Map -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
            <h3 style="margin:0">Mapa Topológico Vivo</h3>
            <span class="tag-live">LIVE</span>
        </div>
        <div id="topology-map" style="width:100%;height:500px;background:var(--bg);border-radius:12px;overflow:hidden;position:relative">
            <?php if (empty($topologyNodes)): ?>
                <div class="empty-state" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center">
                    <div style="font-size:40px;margin-bottom:10px">🕸️</div>
                    <div style="font-weight:600">Aguardando dados de rede...</div>
                    <div class="muted" style="font-size:12px">O mapa será populado automaticamente assim que fluxos forem detectados.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Anomaly Alerts -->
    <div style="display:flex;flex-direction:column;gap:30px">
        <div class="card">
            <h3 style="margin-bottom:15px">Alertas MITRE & CVE</h3>
            <div style="display:flex;flex-direction:column;gap:12px">
                <?php 
                $anomalies = array_filter($recentFlows, fn($f) => !empty($f['anomaly_type']));
                if (empty($anomalies)): ?>
                    <div class="empty-state mini">Nenhuma anomalia crítica.</div>
                <?php else: ?>
                    <?php foreach ($anomalies as $f): ?>
                        <div class="alert-item">
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                                <span class="alert-type"><?= h($f['anomaly_type']) ?></span>
                                <span class="alert-cve"><?= h($f['cve_id']) ?></span>
                            </div>
                            <div class="alert-desc">Ataque vindo de <strong><?= h($f['src_ip']) ?></strong> para <strong><?= h($f['dst_ip']) ?></strong></div>
                            <div class="alert-time"><?= date('H:i:s', strtotime($f['timestamp'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- BGP & ASN Intel -->
        <div class="card">
            <h3 style="margin-bottom:15px">Inteligência BGP / ASN</h3>
            <div style="display:flex;flex-direction:column;gap:10px">
                <?php
                $bgpPrefixes = $pdo->query("SELECT * FROM plugin_dflow_bgp_prefixes LIMIT 5")->fetchAll();
                foreach ($bgpPrefixes as $bgp): ?>
                    <div style="display:flex;justify-content:space-between;padding:8px;background:var(--bg);border-radius:8px">
                        <div>
                            <div style="font-size:12px;font-weight:700"><?= h($bgp['prefix']) ?></div>
                            <div style="font-size:10px;color:var(--muted)">AS<?= $bgp['asn'] ?> - <?= h($bgp['as_name']) ?></div>
                        </div>
                        <span class="tag-proto" style="align-self:center"><?= h($bgp['source']) ?></span>
                    </div>
                <?php endforeach; ?>
                <a href="/app/plugin_dflow_interfaces.php" class="btn btn-mini" style="margin-top:5px;text-align:center">Gerenciar ASN</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Flows Table -->
<div class="card" style="margin-top:30px">
    <h3 style="margin-bottom:15px">Fluxos em Tempo Real (L7 Analysis)</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Direção</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Protocolo L7</th>
                    <th>Volume</th>
                    <th>Metadados / SNI</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentFlows as $f): ?>
                    <tr>
                        <td>
                            <span class="dir-icon <?= $f['direction'] ?>">
                                <?= $f['direction'] === 'in' ? '↙' : '↗' ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:600"><?= h($f['src_ip']) ?></div>
                            <div class="muted" style="font-size:11px">Porta: <?= $f['src_port'] ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600"><?= h($f['dst_ip']) ?></div>
                            <div class="muted" style="font-size:11px">Porta: <?= $f['dst_port'] ?></div>
                        </td>
                        <td>
                            <span class="tag-proto"><?= h($f['l7_proto']) ?></span>
                        </td>
                        <td>
                            <div style="font-size:13px"><?= number_format($f['bytes'] / 1024, 1) ?> KB</div>
                            <div class="muted" style="font-size:11px"><?= $f['packets'] ?> pacotes</div>
                        </td>
                        <td>
                            <div style="font-size:12px"><?= h($f['sni'] ?? 'N/A') ?></div>
                            <?php if ($f['ja3_hash']): ?>
                                <div class="muted" style="font-size:10px">JA3: <?= substr($f['ja3_hash'], 0, 8) ?>...</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($f['anomaly_type']): ?>
                                <span class="tag-status danger">AMEAÇA</span>
                            <?php else: ?>
                                <span class="tag-status success">LIMPO</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.summary-card { background: var(--panel); border: 1px solid var(--border); border-radius: 16px; padding: 20px; position: relative; }
.summary-card .value { font-size: 28px; font-weight: 800; margin: 8px 0; color: var(--text); }
.summary-card.danger { border-color: rgba(255,90,95,0.3); }
.summary-card.danger .value { color: var(--danger); }
.summary-card.primary { border-color: rgba(39,196,168,0.3); }
.summary-card.primary .value { color: var(--primary); }
.footer-note { font-size: 11px; color: var(--muted); }

.tag-live { background: var(--danger); color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; animation: blink 1.5s infinite; }
@keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }

.alert-item { background: rgba(255,90,95,0.05); border: 1px solid rgba(255,90,95,0.2); border-radius: 10px; padding: 12px; }
.alert-type { color: var(--danger); font-weight: 700; font-size: 13px; }
.alert-cve { background: var(--danger); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }
.alert-desc { font-size: 12px; margin: 4px 0; color: var(--text-muted); }
.alert-time { font-size: 10px; color: var(--muted); text-align: right; }

.dir-icon { display: inline-flex; width: 24px; height: 24px; border-radius: 50%; align-items: center; justify-content: center; font-weight: bold; }
.dir-icon.in { background: rgba(39,196,168,0.15); color: var(--primary); }
.dir-icon.out { background: rgba(255,165,0,0.15); color: orange; }

.tag-proto { background: var(--border); padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.tag-status { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; }
.tag-status.danger { background: rgba(255,90,95,0.15); color: var(--danger); }
.tag-status.success { background: rgba(39,196,168,0.15); color: var(--primary); }

/* Force-directed graph nodes and links */
.node circle { stroke: #fff; stroke-width: 1.5px; }
.node text { pointer-events: none; font: 10px sans-serif; fill: var(--text-muted); }
.link { stroke: var(--border); stroke-opacity: 0.6; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = {
        nodes: <?= json_encode($topologyNodes) ?>,
        links: <?= json_encode($topologyLinks) ?>
    };

    const width = document.getElementById('topology-map').clientWidth;
    const height = 500;

    const svg = d3.select("#topology-map")
        .append("svg")
        .attr("width", width)
        .attr("height", height);

    const simulation = d3.forceSimulation(data.nodes)
        .force("link", d3.forceLink(data.links).id(d => d.id))
        .force("charge", d3.forceManyBody().strength(-300))
        .force("center", d3.forceCenter(width / 2, height / 2));

    const link = svg.append("g")
        .attr("class", "links")
        .selectAll("line")
        .data(data.links)
        .enter().append("line")
        .attr("class", "link")
        .attr("stroke-width", d => Math.sqrt(d.weight));

    const node = svg.append("g")
        .attr("class", "nodes")
        .selectAll("g")
        .data(data.nodes)
        .enter().append("g")
        .call(d3.drag()
            .on("start", dragstarted)
            .on("drag", dragged)
            .on("end", dragended));

    node.append("circle")
        .attr("r", 10)
        .attr("fill", d => d.group === 1 ? "var(--primary)" : "#3498db");

    node.append("text")
        .attr("dx", 12)
        .attr("dy", ".35em")
        .text(d => d.id);

    simulation.on("tick", () => {
        link
            .attr("x1", d => d.source.x)
            .attr("y1", d => d.source.y)
            .attr("x2", d => d.target.x)
            .attr("y2", d => d.target.y);

        node
            .attr("transform", d => `translate(${d.x},${d.y})`);
    });

    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }

    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }

    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }
});
</script>

<?php render_footer(); ?>
