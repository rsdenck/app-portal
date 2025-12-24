<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');
$successAccount = '';
$errorAccount = '';
$successZabbix = '';
$errorZabbix = '';

attendant_profiles_ensure_category_column($pdo);
$categories = ticket_categories($pdo);

$stmt = $pdo->prepare('SELECT department, category_id FROM attendant_profiles WHERE user_id = ?');
$stmt->execute([(int)$user['id']]);
$profile = $stmt->fetch() ?: ['department' => '', 'category_id' => null];

$zbxSettings = zbx_settings_get($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $form = (string)($_POST['form'] ?? 'account');

    if ($form === 'account') {
        $name = trim((string)($_POST['name'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $categoryId = safe_int($_POST['category_id'] ?? null);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($name === '') {
            $errorAccount = 'Nome é obrigatório.';
        } else {
            user_update_name($pdo, (int)$user['id'], $name);
            $pdo->prepare('UPDATE attendant_profiles SET department = ?, category_id = ? WHERE user_id = ?')
                ->execute([$department, $categoryId, (int)$user['id']]);

            if ($newPassword !== '') {
                if (strlen($newPassword) < 8) {
                    $errorAccount = 'Nova senha precisa ter no mínimo 8 caracteres.';
                } else {
                    user_update_password($pdo, (int)$user['id'], $newPassword);
                }
            }

            if ($errorAccount === '') {
                $_SESSION['user']['name'] = $name;
                $successAccount = 'Configurações salvas.';
                $stmt = $pdo->prepare('SELECT department, category_id FROM attendant_profiles WHERE user_id = ?');
                $stmt->execute([(int)$user['id']]);
                $profile = $stmt->fetch() ?: ['department' => '', 'category_id' => null];
            }
        }
    } elseif ($form === 'zabbix') {
        $url = trim((string)($_POST['zabbix_url'] ?? ''));
        $username = trim((string)($_POST['zabbix_username'] ?? ''));
        $password = (string)($_POST['zabbix_password'] ?? '');
        $ignoreSsl = isset($_POST['zabbix_ignore_ssl']) && $_POST['zabbix_ignore_ssl'] === '1';

        if ($url === '' || $username === '' || $password === '') {
            $errorZabbix = 'Preencha URL, usuário e senha do Zabbix.';
        } else {
            zbx_settings_save($pdo, $url, $username, $password, $ignoreSsl);
            $successZabbix = 'Configurações do Zabbix salvas.';
            $zbxSettings = zbx_settings_get($pdo);
        }
    }
}

render_header('Atendente · Configurações', current_user());
?>
<div class="card" style="margin-bottom:18px">
  <div style="font-weight:700;margin-bottom:6px">Configurações</div>
  <div class="muted" style="margin-bottom:12px">Gerencie as principais configurações do painel do atendente.</div>
  <div class="config-grid">
    <a href="/app/atendente_plugins.php" class="config-tile">
      <div class="config-tile-main">
        <div class="config-tile-title">Plugins</div>
        <div class="config-tile-desc">Zabbix, VMware, Veeam, Acronis e outras integrações.</div>
      </div>
      <div class="config-tile-tag">Integrações</div>
    </a>
    <a href="#config-account" class="config-tile">
      <div class="config-tile-main">
        <div class="config-tile-title">Conta</div>
        <div class="config-tile-desc">Nome, departamento e senha do atendente.</div>
      </div>
      <div class="config-tile-tag">Usuário</div>
    </a>
    <a href="/app/atendente_definicoes.php" class="config-tile">
      <div class="config-tile-main">
        <div class="config-tile-title">Definições</div>
        <div class="config-tile-desc">Gerencie categorias de chamados e fluxos de atendimento.</div>
      </div>
      <div class="config-tile-tag">Sistema</div>
    </a>
  </div>
</div>
<div class="card" id="config-account">
  <div style="font-weight:700;margin-bottom:4px">Conta do atendente</div>
  <div class="muted" style="margin-bottom:10px">Configure os dados da sua conta no painel.</div>
  <?php if ($successAccount): ?><div class="success"><?= h($successAccount) ?></div><?php endif; ?>
  <?php if ($errorAccount): ?><div class="error"><?= h($errorAccount) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="form" value="account">
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
      <button class="btn primary" type="submit">Salvar</button>
    </div>
  </form>
</div>

<?php
render_footer();



