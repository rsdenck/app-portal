<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
attendant_profiles_ensure_category_column($pdo);

$ticketId = safe_int($_GET['ticket_id'] ?? ($_POST['ticket_id'] ?? null));
$ticket = $ticketId ? ticket_find($pdo, $ticketId) : null;
$attendants = attendant_list($pdo);
if ($ticket && isset($ticket['category_id'])) {
    $attendants = array_values(array_filter($attendants, fn($a) => (
        (int)($a['category_id'] ?? 0) === (int)$ticket['category_id'] ||
        (int)($a['category_id_2'] ?? 0) === (int)$ticket['category_id']
    )));
}
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $ticketId = safe_int($_POST['ticket_id'] ?? null);
    $toUserId = safe_int($_POST['to_user_id'] ?? null);

    if (!$ticketId || !$toUserId) {
        $error = 'Selecione o chamado e o atendente.';
    } else {
        $ticket = ticket_find($pdo, $ticketId);
        if (!$ticket) {
            $error = 'Chamado não encontrado.';
        } else {
            $stmt = $pdo->prepare('SELECT category_id, category_id_2 FROM attendant_profiles WHERE user_id = ?');
            $stmt->execute([$toUserId]);
            $profile = $stmt->fetch() ?: ['category_id' => null, 'category_id_2' => null];
            $cid1 = safe_int($profile['category_id'] ?? null);
            $cid2 = safe_int($profile['category_id_2'] ?? null);
            $ticketCategoryId = (int)$ticket['category_id'];
            $matches = ($cid1 !== null && $cid1 === $ticketCategoryId) || ($cid2 !== null && $cid2 === $ticketCategoryId);
            if (!$matches) {
                $error = 'O atendente selecionado não atende a categoria deste chamado.';
            } else {
                ticket_assign($pdo, $ticketId, $toUserId, (int)$user['id']);
                $success = 'Chamado transferido.';
            }
        }
    }
}

render_header('Atendente · Transferir Chamado', current_user());
?>
<div class="card" style="max-width:620px;margin:0 auto">
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>ID do chamado</label>
    <input name="ticket_id" value="<?= h((string)($ticketId ?? '')) ?>" required>
    <label>Transferir para</label>
    <select name="to_user_id" required>
      <option value="">Selecione...</option>
      <?php foreach ($attendants as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= h((string)$a['name']) ?> (<?= h((string)$a['email']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <div style="margin-top:14px; display:flex; gap:10px">
      <button class="btn primary" type="submit">Transferir</button>
    </div>
  </form>
</div>
<?php
render_footer();



