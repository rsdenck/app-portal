<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
$cats = ticket_categories($pdo);

render_header('Tickets · Categorias', $user);
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
      <?php foreach ($cats as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><?= h((string)$c['name']) ?></td>
          <td><span class="badge"><?= h((string)$c['slug']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();
