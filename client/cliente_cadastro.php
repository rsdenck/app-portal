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
        $id = user_create($pdo, 'cliente', $name, $email, $password);
        $_SESSION['user'] = [
            'id' => $id,
            'role' => 'cliente',
            'name' => $name,
            'email' => $email,
        ];
        redirect('/client/cliente_config.php');
    }
}

render_header('Cliente · Cadastro', null, false);
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-main">
      <div class="auth-logo">
        <img src="/assets/logo_armazem.png" alt="Armazém Cloud" class="auth-logo-img">
      </div>
      <div class="auth-title">Criar Conta de Cliente</div>
      <div class="auth-subtitle">Solicite seu acesso ao portal de atendimento.</div>
      
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        
        <label>Nome Completo</label>
        <input name="name" required placeholder="Seu nome">

        <label>Email</label>
        <input name="email" type="email" autocomplete="email" required placeholder="seu@email.com">
        
        <label>Senha</label>
        <input name="password" type="password" autocomplete="new-password" required placeholder="Mínimo 8 caracteres">
        
        <div class="auth-actions">
          <button class="btn primary" type="submit" style="width: 100%; justify-content: center; display: flex;">Criar Minha Conta</button>
        </div>
      </form>

      <div class="auth-switch" style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px;">
        <div class="muted">Já possui uma conta?</div>
        <a class="btn" href="/index.php" style="width: 100%; text-align: center;">Fazer Login</a>
      </div>
    </div>
    <div class="auth-side">
      <div class="auth-side-title">Informação</div>
      <div class="auth-side-text">
        Seu cadastro será analisado por nossa equipe administrativa antes da liberação total dos recursos.
      </div>
      <div class="auth-terms">
        &copy; <?= date('Y') ?> Armazém Cloud.
      </div>
    </div>
  </div>
</div>
<?php
render_footer(false);




