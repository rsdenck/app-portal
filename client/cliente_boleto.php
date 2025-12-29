<?php

require_once __DIR__ . '/../includes/bootstrap.php';
// require_once __DIR__ . '/../includes/billing.php'; // Já incluído no bootstrap.php
/** @var PDO $pdo */

$user = require_login('cliente');
billing_check_overdue_invoices($pdo);
$userId = (int)$user['id'];

// Handle payment simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $invoiceId = (int)$_POST['pay_invoice'];
    $invoice = billing_get_invoice($pdo, $invoiceId, $userId);
    if ($invoice) {
        // Mock payment process
        sleep(1); // Simulate network lag
        billing_update_invoice_status($pdo, $invoiceId, 'paid', $userId);
        
        // Update account balance
        $stmt = $pdo->prepare('UPDATE billing_accounts SET balance = balance - ? WHERE client_user_id = ?');
        $stmt->execute([(float)$invoice['total_amount'], $userId]);
        
        header('Location: /client/cliente_boleto.php?paid=1');
        exit;
    }
}

// Handle Invoice View (HTML)
if (isset($_GET['view_invoice'])) {
    $invoiceId = (int)$_GET['view_invoice'];
    $invoice = billing_get_invoice($pdo, $invoiceId, $userId);
    if ($invoice) {
        $stmtItems = $pdo->prepare('SELECT * FROM billing_items WHERE invoice_id = ? ORDER BY billing_date DESC');
        $stmtItems->execute([$invoiceId]);
        $items = $stmtItems->fetchAll();
        
        echo billing_generate_invoice_html($invoice, $items, $user);
        exit;
    }
}

$boletos = boleto_list_for_client($pdo, $userId);
$invoices = billing_get_invoices($pdo, $userId);

render_header('Cliente · Boletos e Faturas', current_user());
?>

<div class="card" style="margin-bottom:20px">
    <h3>Faturas e Boletos de Billing</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Nº Fatura</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
                <th style="text-align:right">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr><td colspan="5" class="muted">Nenhuma fatura gerada ainda.</td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): 
                $statusMap = [
                    'issued' => ['label' => 'Pendente', 'class' => 'warning'],
                    'paid' => ['label' => 'Pago', 'class' => 'success'],
                    'overdue' => ['label' => 'Inadimplente', 'class' => 'danger']
                ];
                $st = $statusMap[$inv['status']] ?? ['label' => $inv['status'], 'class' => ''];
            ?>
                <tr>
                    <td><strong><?= h($inv['invoice_number']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td>
                    <td>R$ <?= number_format($inv['total_amount'], 2, ',', '.') ?></td>
                    <td><span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                    <td style="text-align:right">
                        <div style="display:flex;gap:5px;justify-content:flex-end">
                            <a href="?view_invoice=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm">Ver Fatura</a>
                            <?php if ($inv['status'] !== 'paid'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="pay_invoice" value="<?= $inv['id'] ?>">
                                    <button type="submit" class="btn btn-sm primary" onclick="return confirm('Simular pagamento desta fatura?')">Pagar Agora</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
  <h3>Arquivos de Boletos (Legado)</h3>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Referência</th>
        <th>Criado</th>
        <th>Download</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$boletos): ?>
        <tr><td colspan="4" class="muted">Nenhum boleto legado disponível.</td></tr>
      <?php endif; ?>
      <?php foreach ($boletos as $b): ?>
        <tr>
          <td><?= (int)$b['id'] ?></td>
          <td><?= h((string)$b['reference']) ?></td>
          <td><?= h((string)$b['created_at']) ?></td>
          <td><a class="btn btn-sm" href="/client/cliente_boleto.php?download=<?= (int)$b['id'] ?>">Baixar PDF</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();




