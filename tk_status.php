<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
$rows = $pdo->query('SELECT id, name, slug FROM ticket_statuses ORDER BY id ASC')->fetchAll();

render_header('Tickets · Status', $user);
?>
<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>Slug</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h((string)$r['name']) ?></td>
          <td><span class="badge"><?= h((string)$r['slug']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();
