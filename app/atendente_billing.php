<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_permission('billing.manage');

// Atualizar status de inadimplência antes de carregar dados
billing_check_overdue_invoices($pdo);

$clientFilter = safe_int($_GET['client_id'] ?? null);
$monthFilter = (int)($_GET['month'] ?? date('n'));
$yearFilter = (int)($_GET['year'] ?? date('Y'));
$statusFilter = $_GET['status'] ?? '';

// Obter lista de clientes para o seletor
$stmt = $pdo->query("SELECT u.id, u.name, cp.company_name FROM users u LEFT JOIN client_profiles cp ON u.id = cp.user_id WHERE u.role = 'cliente' ORDER BY cp.company_name ASC, u.name ASC");
$clients = $stmt->fetchAll();

// Processar cálculo de faturamento baseado em ativos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calculate_assets') {
    $targetClientId = (int)$_POST['client_id'];
    $calc = billing_calculate_client_assets($pdo, $targetClientId);
    
    if (!empty($calc['items'])) {
        $referenceDate = date("$yearFilter-$monthFilter-01");
        foreach ($calc['items'] as $item) {
            billing_add_item($pdo, $targetClientId, $item['description'], (float)$item['amount'], $item['type'], $referenceDate, (int)$user['id']);
        }
        header("Location: /app/atendente_billing.php?client_id=$targetClientId&month=$monthFilter&year=$yearFilter&calc_success=1");
        exit;
    }
}

$items = [];
$account = null;
$history = [];
$clientList = [];

if ($clientFilter) {
    $account = billing_ensure_account($pdo, $clientFilter);
    $items = billing_get_items($pdo, $clientFilter, null, $monthFilter, $yearFilter);
    $invoices = billing_get_invoices($pdo, $clientFilter);
    $history = billing_get_history($pdo, $clientFilter, $monthFilter, $yearFilter);
}

// Processar geração de fatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_invoice') {
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
    if ($clientFilter) {
        if (billing_generate_invoice($pdo, $clientFilter, $dueDate, (int)$user['id'])) {
            header("Location: /app/atendente_billing.php?client_id=$clientFilter&invoice_success=1");
            exit;
        }
    }
}

// Processar alteração de status da fatura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_invoice_status') {
    $invoiceId = (int)$_POST['invoice_id'];
    $newStatus = $_POST['status'];
    if (billing_update_invoice_status($pdo, $invoiceId, $newStatus, (int)$user['id'])) {
        header("Location: /app/atendente_billing.php?client_id=$clientFilter&status_success=1");
        exit;
    }
} else {
    // Lista geral de faturamento por cliente para o mês selecionado
    $sql = "
        SELECT 
            u.id as client_id, 
            u.name, 
            cp.company_name,
            (SELECT SUM(amount) FROM billing_items WHERE client_user_id = u.id AND MONTH(billing_date) = ? AND YEAR(billing_date) = ?) as total_month,
            (SELECT status FROM billing_invoices WHERE client_user_id = u.id AND MONTH(due_date) = ? AND YEAR(due_date) = ? LIMIT 1) as invoice_status
        FROM users u 
        LEFT JOIN client_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'cliente'
    ";
    
    $params = [$monthFilter, $yearFilter, $monthFilter, $yearFilter];
    
    if ($statusFilter) {
        if ($statusFilter === 'overdue') {
            $sql .= " AND EXISTS (SELECT 1 FROM billing_invoices WHERE client_user_id = u.id AND status = 'overdue')";
        } elseif ($statusFilter === 'pending') {
            $sql .= " AND EXISTS (SELECT 1 FROM billing_invoices WHERE client_user_id = u.id AND status = 'issued' AND MONTH(due_date) = ? AND YEAR(due_date) = ?)";
            $params[] = $monthFilter;
            $params[] = $yearFilter;
        } elseif ($statusFilter === 'paid') {
            $sql .= " AND EXISTS (SELECT 1 FROM billing_invoices WHERE client_user_id = u.id AND status = 'paid' AND MONTH(due_date) = ? AND YEAR(due_date) = ?)";
            $params[] = $monthFilter;
            $params[] = $yearFilter;
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientList = $stmt->fetchAll();

    // Resumo para os cards
    $summary = [
        'pending' => 0,
        'paid' => 0,
        'overdue' => 0
    ];
    foreach ($clientList as $c) {
        $status = $c['invoice_status'];
        $val = (float)$c['total_month'];
        if ($status === 'paid') $summary['paid'] += $val;
        elseif ($status === 'overdue') $summary['overdue'] += $val;
        else $summary['pending'] += $val;
    }
}

// Processar adição de item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $description = (string)($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $type = (string)($_POST['type'] ?? 'Cloud');
    $date = (string)($_POST['date'] ?? date('Y-m-d'));
    
    if ($clientFilter && $description && $amount > 0) {
        if (billing_add_item($pdo, $clientFilter, $description, $amount, $type, $date, (int)$user['id'])) {
            header("Location: /app/atendente_billing.php?client_id=$clientFilter&success=1");
            exit;
        }
    }
}

render_header('Atendente · Billing', $user);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px">
        <form method="GET" style="display:flex;gap:10px;flex:1;min-width:300px;flex-wrap:wrap">
            <select name="client_id" class="input" style="width:250px" onchange="this.form.submit()">
                <option value="">Todos os Clientes (Visão Mensal)</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                        <?= h($client['company_name'] ?: $client['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="month" class="input" style="width:120px" onchange="this.form.submit()">
                <?php for($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $monthFilter == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>

            <select name="year" class="input" style="width:100px" onchange="this.form.submit()">
                <?php for($y=date('Y')-1;$y<=date('Y')+1;$y++): ?>
                    <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <?php if (!$clientFilter): ?>
            <select name="status" class="input" style="width:150px" onchange="this.form.submit()">
                <option value="">Todos os Status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Pagos</option>
                <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Inadimplentes</option>
            </select>
            <?php endif; ?>
        </form>

        <div style="display:flex;gap:10px">
            <a href="/app/atendente_billing_prices.php" class="btn">
                Tabela de Preços
            </a>
            <?php if ($clientFilter): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="calculate_assets">
                    <input type="hidden" name="client_id" value="<?= $clientFilter ?>">
                    <button type="submit" class="btn secondary" onclick="return confirm('Calcular faturamento baseado nos ativos atuais?')">
                        Recalcular Ativos
                    </button>
                </form>
                <button class="btn primary" onclick="openModal('addItemModal')">
                    Novo Item
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$clientFilter): ?>
        <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px">
            <div class="card" style="margin-bottom:0;background:var(--bg-light);border-left:4px solid var(--warning)">
                <span class="muted" style="font-size:12px;text-transform:uppercase">Total Pendentes</span>
                <div style="font-size:24px;font-weight:bold">
                    R$ <?= number_format($summary['pending'], 2, ',', '.') ?>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;background:var(--bg-light);border-left:4px solid var(--success)">
                <span class="muted" style="font-size:12px;text-transform:uppercase">Total Pagos</span>
                <div style="font-size:24px;font-weight:bold;color:var(--success)">
                    R$ <?= number_format($summary['paid'], 2, ',', '.') ?>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;background:var(--bg-light);border-left:4px solid var(--danger)">
                <span class="muted" style="font-size:12px;text-transform:uppercase">Total Inadimplentes</span>
                <div style="font-size:24px;font-weight:bold;color:var(--danger)">
                    R$ <?= number_format($summary['overdue'], 2, ',', '.') ?>
                </div>
            </div>
        </div>

        <h3>Resumo de Faturamento - <?= date('F Y', mktime(0,0,0,$monthFilter,1,$yearFilter)) ?></h3>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Consumo Mensal</th>
                        <th>Status do Boleto</th>
                        <th style="text-align:right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientList as $c): ?>
                        <tr>
                            <td><strong><?= h($c['company_name'] ?: $c['name']) ?></strong></td>
                            <td>R$ <?= number_format((float)$c['total_month'], 2, ',', '.') ?></td>
                            <td>
                                <?php 
                                $status = $c['invoice_status'] ?: 'Pendente';
                                $class = 'warning';
                                if ($status === 'paid' || $status === 'Pago') { $status = 'Pago'; $class = 'success'; }
                                if ($status === 'overdue' || $status === 'Inadimplente') { $status = 'Inadimplente'; $class = 'danger'; }
                                ?>
                                <span class="tag <?= $class ?>"><?= $status ?></span>
                            </td>
                            <td style="text-align:right">
                                <a href="?client_id=<?= $c['client_id'] ?>&month=<?= $monthFilter ?>&year=<?= $yearFilter ?>" class="btn-small">Gerenciar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px">
            <div class="card" style="margin-bottom:0;background:var(--bg-light)">
                <span class="muted" style="font-size:12px;text-transform:uppercase">Saldo em Aberto</span>
                <div style="font-size:24px;font-weight:bold;color:var(--danger)">
                    R$ <?= number_format($account['balance'] ?? 0, 2, ',', '.') ?>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;background:var(--bg-light)">
                <span class="muted" style="font-size:12px;text-transform:uppercase">Itens Pendentes</span>
                <div style="font-size:24px;font-weight:bold">
                    <?= count(array_filter($items, fn($i) => $i['status'] === 'pending')) ?>
                </div>
            </div>
            <div class="card" style="margin-bottom:0;background:var(--bg-light)">
                <span class="muted" style="font-size:12px;text-transform:uppercase">Itens Contestados</span>
                <div style="font-size:24px;font-weight:bold;color:var(--warning)">
                    <?= count(array_filter($items, fn($i) => $i['status'] === 'contested')) ?>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns: 2fr 1fr;gap:30px;margin-top:30px">
            <div>
                <h3>Itens de Cobrança (Pendentes)</h3>
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data Referência</th>
                                <th>Descrição</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th style="text-align:right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $pendingItems = array_filter($items, fn($i) => $i['status'] === 'pending' || $i['status'] === 'contested');
                            if (empty($pendingItems)): 
                            ?>
                                <tr><td colspan="5" class="muted" style="text-align:center">Nenhum item pendente.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pendingItems as $item): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($item['billing_date'])) ?></td>
                                        <td><?= h($item['description']) ?></td>
                                        <td><span class="tag"><?= h($item['type']) ?></span></td>
                                        <td>
                                            <?php if ($item['status'] === 'pending'): ?>
                                                <span class="tag warning">Pendente</span>
                                            <?php elseif ($item['status'] === 'contested'): ?>
                                                <span class="tag danger">Contestado</span>
                                            <?php else: ?>
                                                <span class="tag success"><?= h($item['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right">R$ <?= number_format($item['amount'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($pendingItems)): ?>
                    <div style="margin-top:20px;display:flex;justify-content:flex-end">
                        <button class="btn primary" onclick="openModal('generateInvoiceModal')">Gerar Fatura / Boleto</button>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h3>Faturas Geradas</h3>
                <?php if (empty($invoices)): ?>
                    <p class="muted">Nenhuma fatura gerada.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <?php foreach ($invoices as $inv): ?>
                            <div class="card" style="margin-bottom:0;padding:15px;background:var(--bg-light)">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                    <strong><?= h($inv['invoice_number']) ?></strong>
                                    <span class="tag <?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'overdue' ? 'danger' : 'warning') ?>">
                                        <?= h($inv['status']) ?>
                                    </span>
                                </div>
                                <div style="font-size:18px;font-weight:bold;margin-bottom:10px">
                                    R$ <?= number_format($inv['total_amount'], 2, ',', '.') ?>
                                </div>
                                <div class="muted" style="font-size:12px;margin-bottom:15px">
                                    Vencimento: <?= date('d/m/Y', strtotime($inv['due_date'])) ?>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_invoice_status">
                                    <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                    <div style="display:flex;gap:5px">
                                        <select name="status" class="input" style="padding:4px;font-size:12px">
                                            <option value="issued" <?= $inv['status'] === 'issued' ? 'selected' : '' ?>>Emitido (Pendente)</option>
                                            <option value="paid" <?= $inv['status'] === 'paid' ? 'selected' : '' ?>>Pago</option>
                                            <option value="overdue" <?= $inv['status'] === 'overdue' ? 'selected' : '' ?>>Inadimplente</option>
                                        </select>
                                        <button type="submit" class="btn small">OK</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="margin-top:40px">Histórico de Auditoria</h3>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="4" class="muted" style="text-align:center">Sem histórico disponível.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                <td><?= h($h['action_user_name']) ?></td>
                                <td><span class="tag"><?= h($h['action']) ?></span></td>
                                <td style="font-size:13px"><?= h($h['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Add Item -->
<div id="addItemModal" class="modal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3>Adicionar Item de Cobrança</h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_item">
            <div style="margin-bottom:15px">
                <label>Descrição</label>
                <input type="text" name="description" class="input" required placeholder="Ex: Cloud Computing - Mensalidade Dezembro">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label>Valor (R$)</label>
                    <input type="number" name="amount" class="input" step="0.01" min="0.01" required placeholder="0,00">
                </div>
                <div>
                    <label>Data Referência</label>
                    <input type="date" name="date" class="input" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div style="margin-bottom:20px">
                <label>Tipo de Recurso</label>
                <select name="type" class="input">
                    <option value="Cloud">Cloud Computer</option>
                    <option value="Suporte">Suporte</option>
                    <option value="Contrato">Contrato</option>
                    <option value="Extra">Extra</option>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn" onclick="closeModal('addItemModal')">Cancelar</button>
                <button type="submit" class="btn primary">Adicionar Recurso</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Generate Invoice -->
<div id="generateInvoiceModal" class="modal">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h3>Gerar Fatura Mensal</h3>
            <button class="modal-close" onclick="closeModal('generateInvoiceModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="generate_invoice">
            <div style="margin-bottom:20px">
                <label>Data de Vencimento</label>
                <input type="date" name="due_date" class="input" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                <p class="muted" style="font-size:12px;margin-top:5px">Todos os itens pendentes serão agrupados nesta fatura.</p>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn" onclick="closeModal('generateInvoiceModal')">Cancelar</button>
                <button type="submit" class="btn primary">Gerar Agora</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.classList.remove('active'); }
</script>

<style>
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); align-items:center; justify-content:center; }
.modal.active { display:flex; }
.modal-content { background:var(--panel); padding:20px; border-radius:8px; border:1px solid var(--border); width:95%; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.modal-close { background:none; border:none; color:var(--text); font-size:24px; cursor:pointer; }
.tag { padding: 2px 8px; border-radius: 4px; font-size: 11px; background: var(--bg-hover); border: 1px solid var(--border); }
.tag.warning { background: #fff3cd; color: #856404; border-color: #ffeeba; }
.tag.danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.tag.success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.btn-small { padding: 4px 8px; font-size: 12px; background: var(--bg-hover); border: 1px solid var(--border); border-radius: 4px; color: var(--text); text-decoration: none; }
.btn-small:hover { background: var(--primary); color: white; }
</style>

<?php render_footer(); ?>
