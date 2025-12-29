<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = auth_login($pdo, $email, $password, 'cliente');
    if (!$user) {
        $error = 'Email ou senha inválidos.';
    } else {
        redirect('/client/cliente_chamado.php');
    }
}

render_header('Cliente · Login', null, false);
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-main">
      <div class="auth-logo">
        <img src="/assets/logo_armazem.png" alt="Armazém Cloud" class="auth-logo-img">
      </div>
      <div class="auth-title">Portal do Cliente</div>
      <div class="auth-subtitle">Acesse seus chamados e serviços em um só lugar.</div>
      
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        
        <label>Email</label>
        <input name="email" type="email" autocomplete="email" required placeholder="seu@email.com">
        
        <label>Senha</label>
        <input name="password" type="password" autocomplete="current-password" required placeholder="••••••••">
        
        <div class="auth-actions">
          <button class="btn primary" type="submit" style="width: 100%; justify-content: center; display: flex;">Entrar</button>
        </div>
      </form>

      <div class="auth-switch" style="margin-top: 24px; border-top: 1px solid var(--border); padding-top: 16px;">
        <div class="muted">Ainda não tem conta?</div>
        <a class="btn" href="/client/cliente_cadastro.php" style="width: 100%; text-align: center;">Solicitar Acesso</a>
      </div>
    </div>
    <div class="auth-side">
      <div class="auth-side-title">Bem-vindo</div>
      <div class="auth-side-text">
        O portal Armazém Cloud oferece visibilidade completa sobre sua infraestrutura e suporte.
      </div>
      <div class="auth-terms">
        &copy; <?= date('Y') ?> Armazém Cloud.
      </div>
    </div>
  </div>
</div>
<?php
render_footer(false);



