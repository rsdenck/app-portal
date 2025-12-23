<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('cliente');

$downloadId = safe_int($_GET['download'] ?? null);
if ($downloadId) {
    $boleto = boleto_find_for_client($pdo, $downloadId, (int)$user['id']);
    if (!$boleto) {
        http_response_code(404);
        exit('Boleto não encontrado');
    }

    $relative = (string)$boleto['file_relative_path'];
    if (!str_ends_with(strtolower($relative), '.pdf')) {
        http_response_code(400);
        exit('Arquivo inválido');
    }

    $fullPath = realpath_under_base((string)$config['storage']['boletos_dir'], $relative);
    if (!$fullPath || !is_file($fullPath)) {
        http_response_code(404);
        exit('Arquivo não encontrado');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="boleto-' . (int)$boleto['id'] . '.pdf"');
    header('Content-Length: ' . (string)filesize($fullPath));
    readfile($fullPath);
    exit;
}

$boletos = boleto_list_for_client($pdo, (int)$user['id']);

render_header('Cliente · Boletos', current_user());
?>
<div class="card">
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
        <tr><td colspan="4" class="muted">Nenhum boleto disponível.</td></tr>
      <?php endif; ?>
      <?php foreach ($boletos as $b): ?>
        <tr>
          <td><?= (int)$b['id'] ?></td>
          <td><?= h((string)$b['reference']) ?></td>
          <td><?= h((string)$b['created_at']) ?></td>
          <td><a class="btn" href="/cliente_boleto.php?download=<?= (int)$b['id'] ?>">Baixar PDF</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="muted" style="margin-top:12px;font-size:12px">
    Arquivos são servidos com proteção contra directory traversal.
  </div>
</div>
<?php
render_footer();

