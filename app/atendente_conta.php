<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
$success = '';
$error = '';

attendant_profiles_ensure_category_column($pdo);
$categories = ticket_categories($pdo);

$stmt = $pdo->prepare('SELECT department, category_id FROM attendant_profiles WHERE user_id = ?');
$stmt->execute([(int)$user['id']]);
$profile = $stmt->fetch() ?: ['department' => '', 'category_id' => null];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    
    $name = trim((string)($_POST['name'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $categoryId = safe_int($_POST['category_id'] ?? null);
    $newPassword = (string)($_POST['new_password'] ?? '');

    if ($name === '') {
        $error = 'Nome é obrigatório.';
    } else {
        user_update_name($pdo, (int)$user['id'], $name);
        $pdo->prepare('UPDATE attendant_profiles SET department = ?, category_id = ? WHERE user_id = ?')
            ->execute([$department, $categoryId, (int)$user['id']]);

        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $error = 'Nova senha precisa ter no mínimo 8 caracteres.';
            } else {
                user_update_password($pdo, (int)$user['id'], $newPassword);
            }
        }

        if ($error === '') {
            $_SESSION['user']['name'] = $name;
            $success = 'Configurações de conta salvas com sucesso.';
            
            // Refresh profile data
            $stmt = $pdo->prepare('SELECT department, category_id FROM attendant_profiles WHERE user_id = ?');
            $stmt->execute([(int)$user['id']]);
            $profile = $stmt->fetch() ?: ['department' => '', 'category_id' => null];
            
            // Refresh user data
            $user = current_user();
        }
    }
}

render_header('Atendente · Minha Conta', $user);
?>
<div class="card" style="margin-bottom:18px">
  <div style="font-weight:700;font-size:18px;margin-bottom:12px">Minha Conta</div>
  <div class="muted" style="margin-bottom:12px">Gerencie seus dados pessoais e de acesso ao painel.</div>
</div>

<div class="card">
  <div style="font-weight:700;margin-bottom:4px">Dados do Atendente</div>
  <div class="muted" style="margin-bottom:10px">Configure os dados da sua conta no painel.</div>
  
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    
    <div class="row">
      <div class="col">
        <label>Nome</label>
        <input name="name" value="<?= h((string)($user['name'] ?? '')) ?>" required>
      </div>
      <div class="col">
        <label>Email</label>
        <input value="<?= h((string)($user['email'] ?? '')) ?>" disabled>
      </div>
    </div>
    
    <div class="row">
      <div class="col">
        <label>Departamento</label>
        <input name="department" value="<?= h((string)($profile['department'] ?? '')) ?>">
      </div>
      <div class="col">
        <label>Categoria de atendimento</label>
        <select name="category_id">
          <option value="">Selecione...</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($profile['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= h((string)$c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    
    <div class="row">
      <div class="col">
        <label>Nova senha</label>
        <input name="new_password" type="password" autocomplete="new-password" placeholder="Deixe em branco para não alterar">
      </div>
    </div>
    
    <div style="margin-top:14px">
      <button class="btn primary" type="submit">Salvar Alterações</button>
    </div>
  </form>
</div>

<?php
render_footer();
