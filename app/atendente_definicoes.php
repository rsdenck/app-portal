<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');
$success = '';
$error = '';

$action = (string)($_GET['action'] ?? 'list');
$categoryId = safe_int($_GET['id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $postAction = (string)($_POST['action'] ?? '');

    if ($postAction === 'save') {
        $id = safe_int($_POST['id'] ?? null);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $schemaJson = trim((string)($_POST['schema_json'] ?? '[]'));
        
        if ($name === '') {
            $error = 'O nome da categoria é obrigatório.';
        } else {
            // Validate JSON
            json_decode($schemaJson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $schemaJson = '[]';
            }

            if ($slug === '') {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            }

            if ($id) {
                $stmt = $pdo->prepare('UPDATE ticket_categories SET name = ?, slug = ?, schema_json = ? WHERE id = ?');
                $stmt->execute([$name, $slug, $schemaJson, $id]);
                $success = 'Categoria atualizada com sucesso.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO ticket_categories (name, slug, schema_json) VALUES (?, ?, ?)');
                $stmt->execute([$name, $slug, $schemaJson]);
                $success = 'Categoria criada com sucesso.';
            }
            $action = 'list';
        }
    } elseif ($postAction === 'delete') {
        $id = safe_int($_POST['id'] ?? null);
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM ticket_categories WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Categoria excluída com sucesso.';
        }
        $action = 'list';
    }
}

$categories = ticket_categories($pdo);
$editingCategory = null;
if ($action === 'edit' && $categoryId) {
    $stmt = $pdo->prepare('SELECT * FROM ticket_categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    $editingCategory = $stmt->fetch();
}

render_header('Atendente · Definições', $user);
?>

<div class="card" style="margin-bottom:18px">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
    <div>
      <div style="font-weight:700; font-size:1.1rem">Definições do Sistema</div>
      <div class="muted">Gerencie as categorias de chamados e fluxos de atendimento.</div>
    </div>
    <?php if ($action === 'list'): ?>
      <a href="?action=create" class="btn primary">Nova Categoria</a>
    <?php else: ?>
      <a href="?" class="btn">Voltar para lista</a>
    <?php endif; ?>
  </div>

  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <?php if ($action === 'list'): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Slug (ID Sistema)</th>
            <th style="width:120px; text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td><strong><?= h($c['name']) ?></strong></td>
              <td><code><?= h($c['slug']) ?></code></td>
              <td style="text-align:right">
                <div style="display:flex; gap:8px; justify-content:flex-end">
                  <a href="?action=edit&id=<?= (int)$c['id'] ?>" class="btn" style="padding:4px 8px">Editar</a>
                  <form method="post" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria? Isso pode afetar chamados existentes.')" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn" style="padding:4px 8px; color:var(--error-color)">Excluir</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <form method="post" class="card" style="border:1px solid var(--border-color); background:rgba(0,0,0,0.02)">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editingCategory): ?>
        <input type="hidden" name="id" value="<?= (int)$editingCategory['id'] ?>">
      <?php endif; ?>

      <div class="row">
        <div class="col">
          <label>Nome da Categoria</label>
          <input name="name" value="<?= h($editingCategory['name'] ?? '') ?>" required placeholder="Ex: Redes, Suporte N1, etc.">
        </div>
        <div class="col">
          <label>Slug (Identificador)</label>
          <input name="slug" value="<?= h($editingCategory['slug'] ?? '') ?>" placeholder="Ex: redes (deixe vazio para gerar do nome)">
          <div class="muted" style="font-size:0.8rem; margin-top:4px">O slug é usado para vincular permissões de plugins.</div>
        </div>
      </div>

      <div class="row" style="margin-top:14px">
        <div class="col">
          <label>Schema JSON (Campos Extra)</label>
          <textarea name="schema_json" style="font-family:monospace; height:100px"><?= h($editingCategory['schema_json'] ?? '[]') ?></textarea>
          <div class="muted" style="font-size:0.8rem; margin-top:4px">Defina campos adicionais para esta categoria em formato JSON.</div>
        </div>
      </div>

      <div style="margin-top:18px; display:flex; gap:12px">
        <button type="submit" class="btn primary"><?= $editingCategory ? 'Salvar Alterações' : 'Criar Categoria' ?></button>
        <a href="?" class="btn">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<style>
.table-container {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th {
  text-align: left;
  padding: 12px;
  border-bottom: 2px solid var(--border-color);
  font-weight: 600;
  color: var(--muted-color);
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.05em;
}
td {
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
}
tr:hover {
  background: rgba(0,0,0,0.02);
}
code {
  background: rgba(0,0,0,0.05);
  padding: 2px 4px;
  border-radius: 4px;
  font-family: monospace;
}
</style>

<?php
render_footer();



