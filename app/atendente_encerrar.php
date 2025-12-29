<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
$ticketId = safe_int($_GET['ticket_id'] ?? ($_POST['ticket_id'] ?? null));
$error = '';
$success = '';

$ticket = $ticketId ? ticket_find($pdo, $ticketId) : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $ticketId = safe_int($_POST['ticket_id'] ?? null);
    if (!$ticketId) {
        $error = 'Chamado inválido.';
    } else {
        $ticket = ticket_find($pdo, $ticketId);
        if (!$ticket) {
            $error = 'Chamado não encontrado.';
        } else {
            ticket_close($pdo, $ticketId, (int)$user['id']);
            $success = 'Chamado encerrado.';
            $ticket = ticket_find($pdo, $ticketId);
        }
    }
}

render_header('Atendente · Encerrar Chamado', current_user());
?>
<div class="card" style="max-width:720px;margin:0 auto">
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <?php if (!$ticket): ?>
    <div class="muted">Informe um chamado válido.</div>
  <?php else: ?>
    <div class="row">
      <div class="col">
        <div class="muted">Chamado #<?= (int)$ticket['id'] ?></div>
        <div style="font-weight:700"><?= h((string)$ticket['subject']) ?></div>
        <div class="muted"><?= h((string)$ticket['client_name']) ?> · <?= h((string)$ticket['category_name']) ?></div>
      </div>
      <div class="col">
        <div class="badge"><?= h((string)$ticket['status_name']) ?></div>
      </div>
    </div>
    <form method="post" style="margin-top:14px">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
      <button class="btn danger" type="submit">Confirmar encerramento</button>
    </form>
  <?php endif; ?>
</div>
<?php
render_footer();




