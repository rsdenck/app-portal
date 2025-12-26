<?php

require __DIR__ . '/includes/bootstrap.php';

// The user requested that localhost:8080 always opens the login screen.
// We only redirect if a session is already active AND the user isn't trying to see the login page.
// However, to keep it simple and follow the request "always open the initial login screen", 
// we will skip automatic redirection here.

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Try to login as client
    $user = auth_login($pdo, $email, $password, 'cliente');
    if ($user) {
        redirect('/client/cliente_chamado.php');
    } else {
        $error = 'Email ou senha inválidos.';
    }
}

render_header('Armazém Cloud - Portal de Atendimento', null, false);
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-main">
      <div class="auth-brand">Armazém Cloud</div>
      <div class="auth-title">Portal de Atendimento ao Cliente</div>
      <div class="auth-subtitle">Acesse seus chamados, monitoramento e billing em um só lugar.</div>
      
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        
        <label>Email</label>
        <input name="email" type="email" autocomplete="email" required placeholder=" ">
        
        <label>Senha</label>
        <input name="password" type="password" autocomplete="current-password" required placeholder=" ">
        
        <div class="auth-actions">
          <button class="btn primary" type="submit">Entrar</button>
          <a class="auth-link" href="#">Esqueceu a senha?</a>
        </div>
      </form>
      
      <div class="auth-footer-info">
        <div class="muted">Protegido por segurança de nível empresarial.</div>
        <div class="muted">Seus dados são criptografados e protegidos o tempo todo.</div>
      </div>
      
      <div class="auth-switch-wrapper">
        <div class="auth-switch-label">Você é administrador?</div>
        <a class="btn-block" href="/app/atendente_login.php">Acessar painel do atendente</a>
      </div>
    </div>
    
    <div class="auth-side">
      <div class="auth-side-title">Ainda não tem conta?</div>
      <div class="auth-side-text">
        Entre em contato com sua empresa para obter acesso ao portal da Armazém Cloud.
      </div>
      <div class="auth-terms">
        Ao continuar, você concorda com nossos <a href="#">Termos de Serviço</a> e <a href="#">Política de Privacidade</a>.
      </div>
    </div>
  </div>
</div>
<?php
render_footer(false);
