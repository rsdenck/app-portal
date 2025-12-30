<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

// Filters
$severity = $_GET['severity'] ?? '';
$eventType = $_GET['event_type'] ?? '';
$asn = $_GET['asn'] ?? '';
$technique = $_GET['technique'] ?? '';

$where = [];
$params = [];

if ($severity) {
    $where[] = "severity = ?";
    $params[] = $severity;
}
if ($eventType) {
    $where[] = "event_type = ?";
    $params[] = $eventType;
}
if ($asn) {
    $where[] = "(src_asn = ? OR dst_asn = ?)";
    $params[] = $asn;
    $params[] = $asn;
}
if ($technique) {
    // In MySQL 5.7+ / 8.0, we can use JSON_CONTAINS
    $where[] = "JSON_CONTAINS(mitre_techniques, ?)";
    $params[] = json_encode($technique);
}

$sql = "SELECT * FROM plugin_dflow_security_events";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY detected_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Stats for the top of the page
$stats = [
    'critical' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_security_events WHERE severity = 'critical' AND detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
    'high' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_security_events WHERE severity = 'high' AND detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
    'total_24h' => $pdo->query("SELECT COUNT(*) FROM plugin_dflow_security_events WHERE detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
];

render_header('DFlow · Segurança & Anomalias', $user);
?>

<div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin:0">Segurança & Anomalias de Rede</h2>
    <div style="display:flex; gap:10px;">
        <span class="tag-live">WATCHER ACTIVE</span>
    </div>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px">
    <div class="summary-card danger">
        <span class="muted">Críticos (24h)</span>
        <div class="value"><?= $stats['critical'] ?></div>
        <div class="footer-note">Ações imediatas recomendadas</div>
    </div>
    <div class="summary-card warning">
        <span class="muted">Alta Severidade (24h)</span>
        <div class="value"><?= $stats['high'] ?></div>
        <div class="footer-note">Anomalias significativas</div>
    </div>
    <div class="summary-card info">
        <span class="muted">Total Eventos (24h)</span>
        <div class="value"><?= $stats['total_24h'] ?></div>
        <div class="footer-note">Volume de detecções passivas</div>
    </div>
    <div class="summary-card primary">
        <span class="muted">MITRE Techniques</span>
        <div class="value">ATT&CK</div>
        <div class="footer-note">Mapeamento de táticas</div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px; padding:15px;">
    <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; align-items: end;">
        <div>
            <label class="form-label">Severidade</label>
            <select name="severity" class="form-control">
                <option value="">Todas</option>
                <option value="low" <?= $severity == 'low' ? 'selected' : '' ?>>Baixa</option>
                <option value="medium" <?= $severity == 'medium' ? 'selected' : '' ?>>Média</option>
                <option value="high" <?= $severity == 'high' ? 'selected' : '' ?>>Alta</option>
                <option value="critical" <?= $severity == 'critical' ? 'selected' : '' ?>>Crítica</option>
            </select>
        </div>
        <div>
            <label class="form-label">Tipo de Evento</label>
            <select name="event_type" class="form-control">
                <option value="">Todos</option>
                <option value="volume_anomaly" <?= $eventType == 'volume_anomaly' ? 'selected' : '' ?>>Volume Anormal</option>
                <option value="port_scan" <?= $eventType == 'port_scan' ? 'selected' : '' ?>>Port Scan</option>
                <option value="tcp_anomaly" <?= $eventType == 'tcp_anomaly' ? 'selected' : '' ?>>TCP Anomaly</option>
                <option value="l7_mismatch" <?= $eventType == 'l7_mismatch' ? 'selected' : '' ?>>L7 Mismatch</option>
            </select>
        </div>
        <div>
            <label class="form-label">ASN</label>
            <input type="text" name="asn" class="form-control" placeholder="Ex: 13335" value="<?= h($asn) ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="width:100%">Filtrar</button>
        </div>
    </form>
</div>

<!-- Events Timeline -->
<div class="card">
    <h3 style="margin-bottom:15px">Timeline de Eventos de Segurança</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Tipo</th>
                    <th>Severidade</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>MITRE</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px">
                            <div class="muted">Nenhum evento de segurança detectado.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $e): ?>
                        <tr>
                            <td style="white-space:nowrap"><?= date('d/m H:i:s', strtotime($e['detected_at'])) ?></td>
                            <td>
                                <div style="font-weight:600"><?= h(str_replace('_', ' ', strtoupper($e['event_type']))) ?></div>
                                <div class="muted" style="font-size:10px"><?= h($e['protocol_l7'] ?: $e['protocol_l4']) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $e['severity'] ?>"><?= strtoupper($e['severity']) ?></span>
                            </td>
                            <td>
                                <div><?= h($e['src_ip']) ?></div>
                                <div class="muted" style="font-size:10px">AS<?= $e['src_asn'] ?></div>
                            </td>
                            <td>
                                <div><?= h($e['dst_ip']) ?></div>
                                <div class="muted" style="font-size:10px">AS<?= $e['dst_asn'] ?></div>
                            </td>
                            <td>
                                <?php 
                                $techniques = json_decode($e['mitre_techniques'], true) ?: [];
                                foreach ($techniques as $t): ?>
                                    <span class="tag-proto" title="MITRE Technique"><?= h($t) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <a href="plugin_dflow_flows.php?src=<?= urlencode($e['src_ip']) ?>&dst=<?= urlencode($e['dst_ip']) ?>" class="btn btn-mini">Drill-down</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
