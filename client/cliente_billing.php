<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_permission('billing.view');
billing_check_overdue_invoices($pdo);

$clientId = (int)$user['id'];
$account = billing_ensure_account($pdo, $clientId);

$monthFilter = (int)($_GET['month'] ?? date('n'));
$yearFilter = (int)($_GET['year'] ?? date('Y'));

$items = billing_get_items($pdo, $clientId, null, $monthFilter, $yearFilter);
$invoices = billing_get_invoices($pdo, $clientId);

// Obter detalhamento de ativos para exibição
$stmt = $pdo->prepare("SELECT * FROM assets WHERE client_user_id = ?");
$stmt->execute([$clientId]);
$myAssets = $stmt->fetchAll();

$calc = billing_calculate_client_assets($pdo, $clientId);

// Agrupar itens por ativo para visualização FinOps
$groupedItems = [];
foreach ($calc['items'] as $item) {
    $assetName = $item['asset_name'] ?? 'Outros';
    $groupedItems[$assetName][] = $item;
}

// Processar contestação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contest') {
    $itemId = safe_int($_POST['item_id'] ?? null);
    $reason = (string)($_POST['reason'] ?? '');
    
    if ($itemId && $reason) {
        if (billing_contest_item($pdo, $itemId, $clientId, $reason)) {
            header("Location: /client/cliente_billing.php?success=contest_sent");
            exit;
        }
    }
}

// Lógica de download de fatura (Simulação de PDF via HTML/Print)
$downloadInvoiceId = safe_int($_GET['download_invoice'] ?? null);
if ($downloadInvoiceId) {
    $invoice = billing_get_invoice($pdo, $downloadInvoiceId, $clientId);
    if ($invoice) {
        $items = billing_get_items($pdo, $clientId); // Simplificado: pega todos os itens atuais
        echo billing_generate_invoice_html($invoice, $items, $user);
        exit;
    }
}

render_header('Portal do Cliente · FinOps Billing', $user);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:20px">
        <div>
            <h2 style="margin:0;display:flex;align-items:center;gap:10px">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Gestão Financeira (FinOps)
            </h2>
            <p class="muted" style="margin:5px 0 0">Visão clara de recursos alocados e custos detalhados.</p>
        </div>
        
        <div style="display:flex;gap:15px;align-items:center">
            <form method="GET" style="display:flex;gap:10px;background:var(--bg-hover);padding:10px;border-radius:8px">
                <select name="month" class="input" style="width:120px;border:none;background:transparent" onchange="this.form.submit()">
                    <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?= $m ?>" <?= $monthFilter == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>

                <select name="year" class="input" style="width:100px;border:none;background:transparent" onchange="this.form.submit()">
                    <?php for($y=date('Y')-1;$y<=date('Y')+1;$y++): ?>
                        <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Dashboard de Resumo -->
    <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));gap:20px;margin-bottom:40px">
        <div class="summary-card">
            <span class="muted">Saldo em Aberto</span>
            <div class="value danger">R$ <?= number_format($account['balance'] ?? 0, 2, ',', '.') ?></div>
            <div class="footer-note">Total de faturas não pagas</div>
        </div>
        <div class="summary-card primary">
            <span class="muted">Estimativa Mensal</span>
            <div class="value">R$ <?= number_format($calc['total'], 2, ',', '.') ?></div>
            <div class="footer-note">Custo fixo baseado nos recursos atuais</div>
        </div>
        <div class="summary-card">
            <span class="muted">Total de Ativos</span>
            <div class="value"><?= count($myAssets) ?></div>
            <div class="footer-note">Itens de infraestrutura mapeados</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns: 3fr 1fr;gap:30px">
        <!-- Detalhamento FinOps -->
        <div>
            <div class="section-header">
                <h3>Detalhamento de Custos por Ativo</h3>
                <p class="muted">Transparência total sobre o que compõe sua fatura.</p>
            </div>

            <?php if (empty($groupedItems)): ?>
                <div class="empty-state">
                    <p>Nenhum ativo gerando cobrança no momento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($groupedItems as $assetName => $assetItems): 
                    $assetTotal = array_sum(array_column($assetItems, 'amount'));
                ?>
                    <div class="asset-billing-card">
                        <div class="asset-header">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="asset-icon">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                </div>
                                <strong><?= h($assetName) ?></strong>
                            </div>
                            <div class="asset-total">R$ <?= number_format($assetTotal, 2, ',', '.') ?></div>
                        </div>
                        <div class="asset-body">
                            <table class="finops-table">
                                <thead>
                                    <tr>
                                        <th>Recurso</th>
                                        <th style="text-align:center">Quantidade</th>
                                        <th style="text-align:right">Preço Unit.</th>
                                        <th style="text-align:right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assetItems as $item): ?>
                                        <tr>
                                            <td>
                                                <span class="resource-type-tag <?= $item['type'] ?>"><?= h(strtoupper($item['type'])) ?></span>
                                                <?= h(explode(':', $item['description'])[0]) ?>
                                            </td>
                                            <td style="text-align:center"><?= $item['quantity'] ?> <?= $item['unit'] ?></td>
                                            <td style="text-align:right">R$ <?= number_format($item['unit_price'], 2, ',', '.') ?></td>
                                            <td style="text-align:right;font-weight:bold">R$ <?= number_format($item['amount'], 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h3 style="margin:40px 0 20px">Lançamentos da Próxima Fatura</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Referência</th>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th style="text-align:right">Valor</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="5" class="muted" style="text-align:center;padding:40px">Nenhum item pendente de cobrança.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td style="font-size:13px"><?= date('m/Y', strtotime($item['billing_date'])) ?></td>
                                    <td>
                                        <div style="font-weight:500"><?= h($item['description']) ?></div>
                                        <div class="muted" style="font-size:11px"><?= h($item['type']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($item['status'] === 'pending'): ?>
                                            <span class="tag warning">Aguardando Fatura</span>
                                        <?php elseif ($item['status'] === 'contested'): ?>
                                            <span class="tag danger">Contestado</span>
                                        <?php else: ?>
                                            <span class="tag success"><?= h($item['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;font-weight:500">R$ <?= number_format($item['amount'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($item['status'] === 'pending'): ?>
                                            <button class="btn-icon" onclick="openContestModal(<?= $item['id'] ?>, '<?= h($item['description']) ?>')" title="Contestar valor">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar: Faturas -->
        <div>
            <div class="section-header">
                <h3>Faturas</h3>
            </div>
            <?php if (empty($invoices)): ?>
                <div class="empty-state mini">
                    <p class="muted">Nenhuma fatura gerada.</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:15px">
                    <?php foreach ($invoices as $invoice): ?>
                        <div class="invoice-card">
                            <div class="inv-header">
                                <span class="inv-num"><?= h($invoice['invoice_number']) ?></span>
                                <span class="tag-status <?= $invoice['status'] ?>">
                                    <?= $invoice['status'] === 'paid' ? 'Pago' : ($invoice['status'] === 'overdue' ? 'Inadimplente' : 'Pendente') ?>
                                </span>
                            </div>
                            <div class="inv-total">R$ <?= number_format($invoice['total_amount'], 2, ',', '.') ?></div>
                            <div class="inv-footer">
                                <span class="muted">Vence em <?= date('d/m/Y', strtotime($invoice['due_date'])) ?></span>
                                <a href="/client/cliente_billing.php?download_invoice=<?= $invoice['id'] ?>" class="btn-link" target="_blank">Ver PDF</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="help-box">
                <h4>Suporte Financeiro</h4>
                <p class="muted">Dúvidas sobre sua fatura ou recursos? Estamos aqui para ajudar.</p>
                <a href="/client/cliente_abrir_ticket.php" class="btn primary small" style="width:100%;margin-top:10px;text-align:center">Abrir Chamado</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Contest -->
<div id="contestModal" class="modal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3>Contestar Lançamento</h3>
            <button class="modal-close" onclick="closeModal('contestModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="contest">
            <input type="hidden" name="item_id" id="contest_item_id">
            <div style="margin-bottom:15px">
                <label>Item</label>
                <input type="text" id="contest_item_desc" class="input" readonly style="background:var(--bg-hover)">
            </div>
            <div style="margin-bottom:20px">
                <label>Motivo da Contestação</label>
                <textarea name="reason" class="input" style="height:100px" required placeholder="Explique detalhadamente por que este valor está incorreto..."></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn" onclick="closeModal('contestModal')">Cancelar</button>
                <button type="submit" class="btn primary">Enviar Contestação</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Estilos FinOps */
.summary-card {
    background: var(--bg-hover);
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid var(--border);
    transition: transform 0.2s;
}
.summary-card:hover { transform: translateY(-3px); }
.summary-card.primary { border-left-color: var(--primary); background: rgba(0, 123, 255, 0.05); }
.summary-card .value { font-size: 28px; font-weight: bold; margin: 10px 0; color: var(--text); }
.summary-card .value.danger { color: #ff4d4d; }
.summary-card .footer-note { font-size: 11px; color: var(--muted); }

.section-header { margin-bottom: 20px; }
.section-header h3 { margin: 0; font-size: 18px; }

.asset-billing-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 20px;
    overflow: hidden;
}
.asset-header {
    background: var(--bg-hover);
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border);
}
.asset-icon {
    background: var(--primary);
    color: white;
    padding: 6px;
    border-radius: 6px;
    display: flex;
}
.asset-total { font-weight: bold; font-size: 16px; color: var(--primary); }

.finops-table { width: 100%; border-collapse: collapse; }
.finops-table th { padding: 12px 20px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--muted); background: rgba(0,0,0,0.02); }
.finops-table td { padding: 12px 20px; border-top: 1px solid var(--border); font-size: 14px; }

.resource-type-tag {
    font-size: 9px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    margin-right: 8px;
    background: #eee;
    color: #666;
}
.resource-type-tag.vcpu { background: #e3f2fd; color: #1976d2; }
.resource-type-tag.ram { background: #f3e5f5; color: #7b1fa2; }
.resource-type-tag.storage { background: #fff3e0; color: #e65100; }
.resource-type-tag.license { background: #e8f5e9; color: #2e7d32; }
.resource-type-tag.rede { background: #f1f8e9; color: #33691e; }

.invoice-card {
    background: var(--bg-hover);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid var(--border);
}
.inv-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.inv-num { font-weight: bold; font-size: 13px; }
.inv-total { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
.inv-footer { display: flex; justify-content: space-between; align-items: center; font-size: 12px; }

.tag-status { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
.tag-status.paid { background: #d4edda; color: #155724; }
.tag-status.overdue { background: #f8d7da; color: #721c24; }
.tag-status.issued { background: #fff3cd; color: #856404; }

.help-box {
    margin-top: 25px;
    padding: 20px;
    background: linear-gradient(135deg, var(--bg-hover) 0%, var(--bg) 100%);
    border-radius: 12px;
    border: 1px dashed var(--primary);
}
.help-box h4 { margin: 0 0 10px; }

.btn-link { color: var(--primary); text-decoration: none; font-weight: bold; }
.btn-link:hover { text-decoration: underline; }

.empty-state { text-align: center; padding: 40px; background: var(--bg-hover); border-radius: 12px; border: 1px dashed var(--border); }
.empty-state.mini { padding: 20px; }
</style>

<script>
function openContestModal(id, desc) {
    document.getElementById('contest_item_id').value = id;
    document.getElementById('contest_item_desc').value = desc;
    openModal('contestModal');
}
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
</script>

<?php render_footer(); ?>
