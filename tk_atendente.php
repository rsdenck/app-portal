<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
attendant_profiles_ensure_category_column($pdo);
$categories = ticket_categories($pdo);
$categoryNames = [];
foreach ($categories as $c) {
    $categoryNames[(int)$c['id']] = (string)$c['name'];
}

$error = '';
$success = '';

$action = (string)($_GET['action'] ?? '');
$editUserId = safe_int($_GET['user_id'] ?? null);
$editing = null;
if ($action === 'edit' && $editUserId) {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, ap.department, ap.category_id, ap.category_id_2
         FROM users u
         LEFT JOIN attendant_profiles ap ON ap.user_id = u.id
         WHERE u.id = ? AND u.role = 'atendente' LIMIT 1"
    );
    $stmt->execute([$editUserId]);
    $editing = $stmt->fetch() ?: null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $mode = (string)($_POST['mode'] ?? 'create');
    if ($mode === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $department = trim((string)($_POST['department'] ?? ''));
        $categoryId1 = safe_int($_POST['category_id_1'] ?? null);
        $categoryId2 = safe_int($_POST['category_id_2'] ?? null);

        if ($name === '' || $email === '' || strlen($password) < 8) {
            $error = 'Preencha nome, email e senha (mínimo 8 caracteres).';
        } elseif (!$categoryId1 && !$categoryId2) {
            $error = 'Selecione pelo menos uma categoria.';
        } elseif ($categoryId1 && $categoryId2 && $categoryId1 === $categoryId2) {
            $error = 'As duas categorias devem ser diferentes.';
        } elseif (user_find_by_email($pdo, $email)) {
            $error = 'Email já cadastrado.';
        } else {
            try {
                $id = user_create($pdo, 'atendente', $name, $email, $password);
                $stmt = $pdo->prepare('UPDATE attendant_profiles SET department = ?, category_id = ?, category_id_2 = ? WHERE user_id = ?');
                $stmt->execute([$department, $categoryId1, $categoryId2, $id]);
                $success = 'Atendente criado com sucesso.';
            } catch (Throwable $e) {
                $error = 'Erro ao criar atendente.';
            }
        }
    } elseif ($mode === 'update') {
        $userId = safe_int($_POST['user_id'] ?? null);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $categoryId1 = safe_int($_POST['category_id_1'] ?? null);
        $categoryId2 = safe_int($_POST['category_id_2'] ?? null);
        $newPassword = (string)($_POST['password'] ?? '');

        if (!$userId) {
            $error = 'Usuário inválido.';
        } elseif ($name === '' || $email === '') {
            $error = 'Nome e email são obrigatórios.';
        } elseif (!$categoryId1 && !$categoryId2) {
            $error = 'Selecione pelo menos uma categoria.';
        } elseif ($categoryId1 && $categoryId2 && $categoryId1 === $categoryId2) {
            $error = 'As duas categorias devem ser diferentes.';
        } else {
            $existing = user_find_by_email($pdo, $email);
            if ($existing && (int)$existing['id'] !== $userId) {
                $error = 'Email já cadastrado para outro usuário.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                    $stmt->execute([$name, $email, $userId]);
                    if ($newPassword !== '') {
                        if (strlen($newPassword) < 8) {
                            $error = 'Nova senha precisa ter no mínimo 8 caracteres.';
                        } else {
                            user_update_password($pdo, $userId, $newPassword);
                        }
                    }
                    if ($error === '') {
                        $stmt = $pdo->prepare('UPDATE attendant_profiles SET department = ?, category_id = ?, category_id_2 = ? WHERE user_id = ?');
                        $stmt->execute([$department, $categoryId1, $categoryId2, $userId]);
                        $pdo->commit();
                        $success = 'Atendente atualizado com sucesso.';
                        $action = '';
                        $editUserId = null;
                        $editing = null;
                    } else {
                        $pdo->rollBack();
                    }
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Erro ao atualizar atendente.';
                }
            }
        }
    }
}

$attendants = attendant_list($pdo);

render_header('Tickets · Atendentes', $user);
?>
<div class="card" style="margin-bottom:18px;max-width:720px">
  <div style="font-weight:700;margin-bottom:6px"><?= $editing ? 'Editar atendente' : 'Novo atendente' ?></div>
  <div class="muted" style="margin-bottom:10px">Crie ou edite usuários atendentes/analistas vinculados a até duas categorias.</div>
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="mode" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?>
      <input type="hidden" name="user_id" value="<?= (int)$editing['id'] ?>">
    <?php endif; ?>
    <div class="row">
      <div class="col">
        <label>Nome</label>
        <input name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required>
      </div>
      <div class="col">
        <label>Email</label>
        <input name="email" type="email" value="<?= h((string)($editing['email'] ?? '')) ?>" required>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label><?= $editing ? 'Nova senha' : 'Senha' ?></label>
        <input name="password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
      </div>
      <div class="col">
        <label>Departamento</label>
        <input name="department" value="<?= h((string)($editing['department'] ?? '')) ?>">
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Categoria 1</label>
        <select name="category_id_1" required>
          <option value="">Selecione...</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($editing['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= h((string)$c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label>Categoria 2 (opcional)</label>
        <select name="category_id_2">
          <option value="">Selecione...</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($editing['category_id_2'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= h((string)$c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="margin-top:14px">
      <button class="btn primary" type="submit"><?= $editing ? 'Salvar alterações' : 'Criar atendente' ?></button>
      <?php if ($editing): ?>
        <a class="btn" href="/tk_atendente.php">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<div class="card">
  <div style="font-weight:700;margin-bottom:6px">Atendentes</div>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>Email</th>
        <th>Departamento</th>
        <th>Categorias</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($attendants as $a): ?>
        <tr>
          <td><?= (int)$a['id'] ?></td>
          <td><?= h((string)$a['name']) ?></td>
          <td><?= h((string)$a['email']) ?></td>
          <td><?= h((string)($a['department'] ?? '')) ?></td>
          <td>
            <?php
              $cats = [];
              $cid1 = (int)($a['category_id'] ?? 0);
              $cid2 = (int)($a['category_id_2'] ?? 0);
              if ($cid1 && isset($categoryNames[$cid1])) {
                  $cats[] = $categoryNames[$cid1];
              }
              if ($cid2 && isset($categoryNames[$cid2]) && $cid2 !== $cid1) {
                  $cats[] = $categoryNames[$cid2];
              }
            ?>
            <?= h(implode(', ', $cats)) ?>
          </td>
          <td>
            <a class="btn" href="/tk_atendente.php?action=edit&user_id=<?= (int)$a['id'] ?>">Editar</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();
