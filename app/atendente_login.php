<?php

require __DIR__ . '/../includes/bootstrap.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = auth_login($pdo, $email, $password, 'atendente');
    if (!$user) {
        $error = 'Email ou senha inválidos.';
    } else {
        redirect('/app/atendente_gestao.php');
    }
}

render_header('Atendente · Login', null, false);
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-main">
      <div class="auth-brand">Armazém Cloud</div>
      <div class="auth-title">Portal Administrativo</div>
      <div class="auth-subtitle">Acesso exclusivo para atendentes e administradores.</div>
      
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        
        <label>Email</label>
        <input name="email" type="email" autocomplete="email" required placeholder=" ">
        
        <label>Senha</label>
        <input name="password" type="password" autocomplete="current-password" required placeholder=" ">
        
        <div class="auth-actions">
          <button class="btn primary" type="submit">Entrar no Painel</button>
        </div>
      </form>

      <div class="auth-footer-info">
        <div class="muted">IP registrado para auditoria.</div>
        <div class="muted">Sessão protegida por TLS 1.3.</div>
      </div>

      <div class="auth-switch-wrapper">
        <div class="auth-switch-label">É um cliente?</div>
        <a class="btn-block" href="/index.php">Voltar para Área do Cliente</a>
      </div>
    </div>
    <div class="auth-side">
      <div class="auth-side-title">Área Restrita</div>
      <div class="auth-side-text">
        Este painel é destinado ao gerenciamento de chamados, ativos e configurações do sistema.
      </div>
      <div class="auth-terms">
        &copy; <?= date('Y') ?> Armazém Cloud. Todos os direitos reservados.
      </div>
    </div>
  </div>
</div>
<?php
render_footer(false);



