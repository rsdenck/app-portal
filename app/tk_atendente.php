<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

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

if ($action === 'delete' && $editUserId) {
    // For simplicity in this implementation, we use a GET link for deletion.
    // In a production environment, this should ideally be a POST request.
    if ($editUserId === (int)$user['id']) {
        $error = 'Você não pode excluir seu próprio usuário.';
    } else {
        try {
            $pdo->beginTransaction();
            // Delete from attendant_profiles first due to FK if any (though here it's likely just a relation)
            $stmt = $pdo->prepare("DELETE FROM attendant_profiles WHERE user_id = ?");
            $stmt->execute([$editUserId]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'atendente'");
            $stmt->execute([$editUserId]);
            
            $pdo->commit();
            $success = 'Atendente excluído com sucesso.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Erro ao excluir atendente: ' . $e->getMessage();
        }
    }
}

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

<div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px; align-items: start;">
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
      <div>
        <div style="font-weight:700; font-size: 1.1rem;">Atendentes</div>
        <div class="muted" style="font-size: 0.85rem;">Lista de usuários analistas e seus departamentos</div>
      </div>
      <div style="background: var(--primary-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
        <?= count($attendants) ?> Total
      </div>
    </div>
    
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Nome / Email</th>
            <th>Departamento</th>
            <th>Categorias</th>
            <th style="text-align: right;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attendants as $a): ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="width: 32px; height: 32px; background: #2a2a2a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; color: var(--primary-color); border: 1px solid #333;">
                    <?= strtoupper(substr($a['name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight: 600;"><?= h((string)$a['name']) ?></div>
                    <div class="muted" style="font-size: 0.75rem;"><?= h((string)$a['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span style="background: #1a1a1a; padding: 2px 8px; border-radius: 4px; border: 1px solid #333; font-size: 0.85rem;">
                  <?= h((string)($a['department'] ?: 'Geral')) ?>
                </span>
              </td>
              <td>
                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                  <?php
                    $cid1 = (int)($a['category_id'] ?? 0);
                    $cid2 = (int)($a['category_id_2'] ?? 0);
                    if ($cid1 && isset($categoryNames[$cid1])): ?>
                      <span style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; border: 1px solid rgba(var(--primary-rgb), 0.2);">
                        <?= h($categoryNames[$cid1]) ?>
                      </span>
                    <?php endif; ?>
                    <?php if ($cid2 && isset($categoryNames[$cid2]) && $cid2 !== $cid1): ?>
                      <span style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; border: 1px solid rgba(var(--primary-rgb), 0.2);">
                        <?= h($categoryNames[$cid2]) ?>
                      </span>
                    <?php endif; ?>
                </div>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <a class="btn danger" href="/app/tk_atendente.php?action=delete&user_id=<?= (int)$a['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este atendente?')" style="padding: 4px 10px; font-size: 0.85rem; margin-right: 5px;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                  Excluir
                </a>
                <a class="btn" href="/app/tk_atendente.php?action=edit&user_id=<?= (int)$a['id'] ?>" style="padding: 4px 10px; font-size: 0.85rem;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg>
                  Editar
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" style="position: sticky; top: 20px;">
    <div style="font-weight:700; margin-bottom:4px; font-size: 1.1rem;"><?= $editing ? 'Editar Atendente' : 'Novo Atendente' ?></div>
    <div class="muted" style="margin-bottom:20px; font-size: 0.85rem;">Preencha os dados abaixo para <?= $editing ? 'atualizar o cadastro' : 'criar um novo acesso' ?>.</div>
    
    <?php if ($success): ?><div class="success" style="margin-bottom: 15px;"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error" style="margin-bottom: 15px;"><?= h($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="mode" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?>
        <input type="hidden" name="user_id" value="<?= (int)$editing['id'] ?>">
      <?php endif; ?>
      
      <div style="margin-bottom: 12px;">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Nome Completo</label>
        <input name="name" value="<?= h((string)($editing['name'] ?? '')) ?>" required style="width: 100%;">
      </div>
      
      <div style="margin-bottom: 12px;">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">E-mail</label>
        <input name="email" type="email" value="<?= h((string)($editing['email'] ?? '')) ?>" required style="width: 100%;">
      </div>

      <div style="margin-bottom: 12px;">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Departamento</label>
        <input name="department" value="<?= h((string)($editing['department'] ?? '')) ?>" placeholder="Ex: N1, Suporte, Redes" style="width: 100%;">
      </div>

      <div style="margin-bottom: 12px;">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;"><?= $editing ? 'Nova Senha (opcional)' : 'Senha' ?></label>
        <input name="password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?> style="width: 100%;">
      </div>

      <div style="margin-bottom: 12px;">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Categoria Principal</label>
        <select name="category_id_1" required style="width: 100%;">
          <option value="">Selecione...</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($editing['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= h((string)$c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Categoria Secundária</label>
        <select name="category_id_2" style="width: 100%;">
          <option value="">Nenhuma</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($editing['category_id_2'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= h((string)$c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: flex; gap: 10px;">
        <button class="btn primary" type="submit" style="flex: 1;"><?= $editing ? 'Salvar' : 'Cadastrar' ?></button>
        <?php if ($editing): ?>
          <a class="btn" href="/app/tk_atendente.php" style="flex: 1; text-align: center;">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php
render_footer();



