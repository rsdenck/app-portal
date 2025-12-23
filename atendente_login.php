<?php

require __DIR__ . '/includes/bootstrap.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = auth_login($pdo, $email, $password, 'atendente');
    if (!$user) {
        $error = 'Email ou senha inválidos.';
    } else {
        redirect('/atendente_gestao.php');
    }
}

render_header('Atendente · Login', null);
?>
<div class="card" style="max-width:520px;margin:0 auto">
  <div style="display:flex;justify-content:center;margin-bottom:16px">
    <img src="/assets/logo_armazem.png" alt="Armazém Cloud" style="height:55px;display:block">
  </div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <label>Email</label>
    <input name="email" type="email" autocomplete="email" required>
    <label>Senha</label>
    <input name="password" type="password" autocomplete="current-password" required>
    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
      <button class="btn primary" type="submit">Entrar</button>
      <a class="btn" href="/atendente_cadastro.php">Criar conta</a>
    </div>
  </form>
</div>
<?php
render_footer();
