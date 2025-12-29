<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('cliente');
$success = '';
$error = '';

$stmt = $pdo->prepare('SELECT company_name, document, phone FROM client_profiles WHERE user_id = ?');
$stmt->execute([(int)$user['id']]);
$profile = $stmt->fetch() ?: ['company_name' => '', 'document' => '', 'phone' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $name = trim((string)($_POST['name'] ?? ''));
    $company = trim((string)($_POST['company_name'] ?? ''));
    $document = trim((string)($_POST['document'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');

    if ($name === '') {
        $error = 'Nome é obrigatório.';
    } else {
        user_update_name($pdo, (int)$user['id'], $name);
        $pdo->prepare('UPDATE client_profiles SET company_name = ?, document = ?, phone = ? WHERE user_id = ?')
            ->execute([$company, $document, $phone, (int)$user['id']]);

        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $error = 'Nova senha precisa ter no mínimo 8 caracteres.';
            } else {
                user_update_password($pdo, (int)$user['id'], $newPassword);
            }
        }

        if ($error === '') {
            $_SESSION['user']['name'] = $name;
            $success = 'Configurações salvas.';
            $stmt = $pdo->prepare('SELECT company_name, document, phone FROM client_profiles WHERE user_id = ?');
            $stmt->execute([(int)$user['id']]);
            $profile = $stmt->fetch() ?: ['company_name' => '', 'document' => '', 'phone' => ''];
        }
    }
}

render_header('Cliente · Configurações', current_user());
?>
<div class="card">
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
        <label>Empresa</label>
        <input name="company_name" value="<?= h((string)($profile['company_name'] ?? '')) ?>">
      </div>
      <div class="col">
        <label>Documento</label>
        <input name="document" value="<?= h((string)($profile['document'] ?? '')) ?>">
      </div>
      <div class="col">
        <label>Telefone</label>
        <input name="phone" value="<?= h((string)($profile['phone'] ?? '')) ?>">
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Nova senha</label>
        <input name="new_password" type="password" autocomplete="new-password" placeholder="Deixe em branco para não alterar">
      </div>
    </div>
    <div style="margin-top:14px">
      <button class="btn primary" type="submit">Salvar</button>
    </div>
  </form>
</div>
<?php
render_footer();




