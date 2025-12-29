<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login();
dflow_ensure_tables($pdo);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alertId = (int)($_POST['alert_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($alertId > 0) {
        if ($action === 'resolve') {
            $stmt = $pdo->prepare("UPDATE plugin_dflow_alerts SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$alertId]);
        } elseif ($action === 'mute') {
            $stmt = $pdo->prepare("UPDATE plugin_dflow_alerts SET status = 'muted' WHERE id = ?");
            $stmt->execute([$alertId]);
        }
    }
    header('Location: plugin_dflow_alerts.php');
    exit;
}

// Fetch alerts
$stmt = $pdo->query("SELECT * FROM plugin_dflow_alerts ORDER BY created_at DESC LIMIT 100");
$alerts = $stmt->fetchAll();

render_header('DFlow · Alertas', $user);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Alertas de Rede & DFlow</h2>
        <p class="text-muted">Monitoramento comportamental e detecção de anomalias em tempo real.</p>
    </div>
    <div class="btn-group">
        <a href="plugin_dflow_flows.php" class="btn btn-outline-secondary">Fluxos Ativos</a>
        <a href="atendente_atendimentos.php?category_id=2" class="btn btn-primary">Ver Chamados (Redes)</a>
    </div>
</div>

<div class="card bg-dark text-white border-secondary mb-4">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span>Alertas Recentes</span>
        <span class="badge bg-danger"><?= count(array_filter($alerts, fn($a) => $a['status'] === 'active')) ?> Ativos</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-dark table-hover mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Severidade</th>
                    <th>Tipo</th>
                    <th>Assunto</th>
                    <th>IPs Envolvidos</th>
                    <th>Chamado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $a): ?>
                    <tr class="<?= $a['status'] !== 'active' ? 'opacity-50' : '' ?>">
                        <td class="small"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
                        <td>
                            <?php
                            $bg = 'secondary';
                            if ($a['severity'] === 'critical') $bg = 'danger';
                            elseif ($a['severity'] === 'high') $bg = 'warning text-dark';
                            elseif ($a['severity'] === 'medium') $bg = 'info text-dark';
                            ?>
                            <span class="badge bg-<?= $bg ?>"><?= strtoupper($a['severity']) ?></span>
                        </td>
                        <td><code><?= h($a['type']) ?></code></td>
                        <td>
                            <div class="fw-bold"><?= h($a['subject']) ?></div>
                            <div class="small text-muted"><?= h(mb_strimwidth($a['description'], 0, 100, "...")) ?></div>
                        </td>
                        <td>
                            <?php if ($a['source_ip']): ?>
                                <span class="badge bg-outline-info">S: <?= h($a['source_ip']) ?></span>
                            <?php endif; ?>
                            <?php if ($a['target_ip']): ?>
                                <span class="badge bg-outline-warning">T: <?= h($a['target_ip']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['ticket_id']): ?>
                                <a href="atendente_ticket.php?id=<?= $a['ticket_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    #<?= $a['ticket_id'] ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['status'] === 'active'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="action" value="resolve">
                                    <button type="submit" class="btn btn-sm btn-success">Resolver</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="action" value="mute">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Silenciar</button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= h($a['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($alerts)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Nenhum alerta detectado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.bg-outline-info { border: 1px solid #0dcaf0; color: #0dcaf0; }
.bg-outline-warning { border: 1px solid #ffc107; color: #ffc107; }
.opacity-50 { opacity: 0.5; }
</style>

<?php
render_footer();
