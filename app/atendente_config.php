<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

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
    $form = (string)($_POST['form'] ?? 'zabbix');

    if ($form === 'zabbix') {
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
  <div style="font-weight:700;font-size:18px;margin-bottom:12px">Configurações</div>
  <div class="muted" style="margin-bottom:12px">Gerencie as principais configurações do painel do atendente.</div>
  <div class="config-grid">
    <a href="/app/atendente_plugins.php" class="config-tile">
      <div class="config-tile-main">
        <div class="config-tile-title">Plugins</div>
        <div class="config-tile-desc">Zabbix, VMware, Veeam, Acronis e outras integrações.</div>
      </div>
      <div class="config-tile-tag">Integrações</div>
    </a>
    <a href="/app/atendente_conta.php" class="config-tile">
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
<?php
render_footer();



