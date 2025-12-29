<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
attendant_profiles_ensure_category_column($pdo);

$stmt = $pdo->prepare('SELECT category_id, category_id_2 FROM attendant_profiles WHERE user_id = ?');
$stmt->execute([(int)$user['id']]);
$profile = $stmt->fetch() ?: ['category_id' => null, 'category_id_2' => null];
$attendantCategoryIds = [];
$cid1 = safe_int($profile['category_id'] ?? null);
$cid2 = safe_int($profile['category_id_2'] ?? null);
if ($cid1 !== null) {
    $attendantCategoryIds[] = $cid1;
}
if ($cid2 !== null && $cid2 !== $cid1) {
    $attendantCategoryIds[] = $cid2;
}

$categories = ticket_categories($pdo);
if ($attendantCategoryIds) {
    $categories = array_values(array_filter($categories, fn($c) => in_array((int)$c['id'], $attendantCategoryIds, true)));
}

$categoryId = safe_int($_GET['category_id'] ?? null);

// Atendimentos: tickets with interaction or closed (showing all for team visibility)
$tickets = ticket_list_active($pdo);
if ($attendantCategoryIds) {
    $tickets = array_values(array_filter($tickets, fn($t) => in_array((int)$t['category_id'], $attendantCategoryIds, true)));
}
if ($categoryId) {
    $tickets = array_values(array_filter($tickets, fn($t) => (int)$t['category_id'] === $categoryId));
}

render_header('Atendente · Atendimentos', current_user());
?>
<div class="card">

  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px">
    <div style="min-width:280px">
      <label>Filtrar por categoria</label>
      <select name="category_id">
        <option value="">Todas</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($categoryId === (int)$c['id']) ? 'selected' : '' ?>><?= h((string)$c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">Aplicar</button>
  </form>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Cliente</th>
        <th>Assunto</th>
        <th>Categoria</th>
        <th>Status</th>
        <th>Criado</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$tickets): ?>
        <tr><td colspan="7" class="muted">Nenhum atendimento encontrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($tickets as $t): ?>
        <?php 
            $m = ticket_calculate_metrics($t, $pdo); 
            $isMine = (int)$t['assigned_user_id'] === (int)$user['id'];
            $hasUnread = ticket_has_unread($pdo, (int)$t['id'], (int)$user['id']);
        ?>
        <tr <?= $isMine ? 'style="border-left: 4px solid #0066cc"' : '' ?>>
          <td>
            <div style="display: flex; align-items: center; gap: 8px">
                <?= (int)$t['id'] ?>
                <?php if ($hasUnread): ?>
                    <div title="Nova interação" style="background: #ff0000; color: #fff; width: 18px; height: 18px; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; animation: pulse 1.5s infinite; box-shadow: 0 0 8px rgba(255,0,0,0.6)">
                        !
                    </div>
                <?php endif; ?>
            </div>
          </td>
          <td><?= h((string)$t['client_name']) ?></td>
          <td style="<?= $hasUnread ? 'font-weight: 700' : '' ?>"><?= h((string)$t['subject']) ?></td>
          <td>
            <span class="badge"><?= h((string)$t['category_name']) ?></span>
            <?php if ($t['assigned_name']): ?>
                <div style="font-size:0.8em; margin-top:4px" class="muted">
                    Atendente: <?= h($t['assigned_name']) ?>
                </div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge"><?= h((string)$t['status_name']) ?></span>
            <br>
            <span style="font-size:0.8em; font-weight: bold" class="<?= $m['sla_status'] === 'Dentro do Prazo' ? 'text-success' : 'text-danger' ?>">
                <?= $m['sla_status'] ?>: <?= $m['sli_formatted'] ?>
            </span>
          </td>
          <td><?= h((string)$t['created_at']) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn primary" href="/app/atendente_ticket.php?id=<?= (int)$t['id'] ?>">Visualizar</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();



