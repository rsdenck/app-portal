<?php

require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    if (($user['role'] ?? null) === 'cliente') {
        redirect('/cliente_chamado.php');
    }
    if (($user['role'] ?? null) === 'atendente') {
        redirect('/atendente_gestao.php');
    }
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = auth_login($pdo, $email, $password, 'cliente');
    if (!$user) {
        $error = 'Email ou senha inválidos.';
    } else {
        redirect('/cliente_chamado.php');
    }
}

?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Armazém Cloud - Portal de Atendimento</title>
  <link rel="icon" href="/assets/favicon_round.svg" type="image/svg+xml">
  <link rel="icon" href="/assets/favicon.png" type="image/png">
  <link rel="shortcut icon" href="/assets/favicon.png" type="image/png">
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-main">
        <div class="auth-logo">
          <img src="/assets/logo_armazem.png" alt="Armazém Cloud" class="auth-logo-img">
        </div>
        <div class="auth-title">Portal de Atendimento ao Cliente</div>
        <div class="auth-subtitle">Acesse seus chamados, monitoramento e billing em um só lugar.</div>
        <form method="post" class="auth-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
          <label>Email</label>
          <input name="email" type="email" autocomplete="email" required>
          <label>Senha</label>
          <input name="password" type="password" autocomplete="current-password" required>
          <div class="auth-actions">
            <button class="btn primary" type="submit">Entrar</button>
            <a class="auth-link" href="#">Esqueceu a senha?</a>
          </div>
        </form>
        <div class="auth-meta">
          <div class="muted">Protegido por segurança de nível empresarial.</div>
          <div class="muted">Seus dados são criptografados e protegidos o tempo todo.</div>
        </div>
        <div class="auth-switch">
          <div class="muted">Você é administrador?</div>
          <a class="btn" href="/atendente_login.php">Acessar painel do atendente</a>
        </div>
      </div>
      <div class="auth-side">
        <div class="auth-side-title">Ainda não tem conta?</div>
        <div class="auth-side-text">
          Entre em contato com sua empresa para obter acesso ao portal da Armazém Cloud.
        </div>
        <div class="auth-terms">
          Ao continuar, você concorda com nossos Termos de Serviço e Política de Privacidade.
        </div>
      </div>
    </div>
  </div>
</body>
</html>
