<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

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
        $id = user_create($pdo, 'atendente', $name, $email, $password);
        $_SESSION['user'] = [
            'id' => $id,
            'role' => 'atendente',
            'name' => $name,
            'email' => $email,
        ];
        redirect('/app/atendente_config.php');
    }
}

render_header('Atendente · Cadastro', null, false);
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-main">
      <div class="auth-logo">
        <img src="/assets/logo_armazem.png" alt="Armazém Cloud" class="auth-logo-img">
      </div>
      <div class="auth-title">Cadastro de Atendente</div>
      <div class="auth-subtitle">Crie seu acesso administrativo ao portal.</div>
      
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        
        <label>Nome Completo</label>
        <input name="name" required placeholder="Seu nome">

        <label>Email Corporativo</label>
        <input name="email" type="email" autocomplete="email" required placeholder="seu@armazemcloud.com">
        
        <label>Senha</label>
        <input name="password" type="password" autocomplete="new-password" required placeholder="Mínimo 8 caracteres">
        
        <div class="auth-actions">
          <button class="btn primary" type="submit" style="width: 100%; justify-content: center; display: flex;">Finalizar Cadastro</button>
        </div>
      </form>

      <div class="auth-switch" style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px;">
        <div class="muted">Já possui uma conta?</div>
        <a class="btn" href="/app/atendente_login.php" style="width: 100%; text-align: center;">Fazer Login Admin</a>
      </div>
    </div>
    <div class="auth-side">
      <div class="auth-side-title">Aviso de Segurança</div>
      <div class="auth-side-text">
        O cadastro de novos atendentes é monitorado. Use apenas seu e-mail corporativo.
      </div>
      <div class="auth-terms">
        &copy; <?= date('Y') ?> Armazém Cloud.
      </div>
    </div>
  </div>
</div>
<?php
render_footer(false);




