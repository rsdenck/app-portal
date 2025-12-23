<?php

require __DIR__ . '/includes/bootstrap.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $email === '' || strlen($password) < 8) {
        $error = 'Preencha nome, email e senha (mínimo 8 caracteres).';
    } elseif (user_find_by_email($pdo, $email)) {
        $error = 'Email já cadastrado.';
    } else {
        $id = user_create($pdo, 'cliente', $name, $email, $password);
        $_SESSION['user'] = [
            'id' => $id,
            'role' => 'cliente',
            'name' => $name,
            'email' => $email,
        ];
        redirect('/cliente_config.php');
    }
}

render_header('Cliente · Cadastro', null);
?>
<div class="card" style="max-width:520px;margin:0 auto">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <label>Nome</label>
    <input name="name" required>
    <label>Email</label>
    <input name="email" type="email" autocomplete="email" required>
    <label>Senha</label>
    <input name="password" type="password" autocomplete="new-password" required>
    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
      <button class="btn primary" type="submit">Criar conta</button>
      <a class="btn" href="/cliente_login.php">Já tenho conta</a>
    </div>
  </form>
</div>
<?php
render_footer();

