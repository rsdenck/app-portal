<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
$success = '';
$error = '';

try {
    $clientsStmt = $pdo->query("SELECT id, name FROM users WHERE role = 'cliente' ORDER BY name ASC");
    $clients = $clientsStmt->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

$clientById = [];
foreach ($clients as $c) {
    $clientById[(string)$c['id']] = $c;
}

$zbxConfig = zbx_config_from_db($pdo, $config);

try {
    $auth = zbx_auth($zbxConfig);
    $hostgroups = zbx_rpc(
        $zbxConfig,
        'hostgroup.get',
        [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
        ],
        $auth
    );
    if (!is_array($hostgroups)) {
        $hostgroups = [];
    }
} catch (Throwable $e) {
    $hostgroups = [];
    $error = $e->getMessage();
}

$existing = [];
if (!$error) {
    $stmt = $pdo->query('SELECT client_user_id, hostgroupid, name FROM zabbix_hostgroups');
    foreach ($stmt->fetchAll() as $row) {
        $existing[(string)$row['hostgroupid']] = $row;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$error) {
    csrf_validate();
    $bindings = $_POST['binding'] ?? [];
    $names = $_POST['hostgroup_name'] ?? [];
    if (!is_array($bindings)) {
        $bindings = [];
    }
    if (!is_array($names)) {
        $names = [];
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM zabbix_hostgroups');
        foreach ($bindings as $groupid => $clientId) {
            $groupid = (string)$groupid;
            $clientId = trim((string)$clientId);
            if ($clientId === '') {
                continue;
            }
            $name = isset($names[$groupid]) ? (string)$names[$groupid] : '';
            $stmt = $pdo->prepare('INSERT INTO zabbix_hostgroups (client_user_id, hostgroupid, name) VALUES (?,?,?)');
            $stmt->execute([(int)$clientId, $groupid, $name]);
        }
        $pdo->commit();
        $success = 'Mapeamento de hostgroups salvo.';
        $existing = [];
        $stmt = $pdo->query('SELECT client_user_id, hostgroupid, name FROM zabbix_hostgroups');
        foreach ($stmt->fetchAll() as $row) {
            $existing[(string)$row['hostgroupid']] = $row;
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

render_header('Atendente · Zabbix · Clientes', current_user());
?>
<div class="card">
  <div style="font-weight:700;margin-bottom:4px">Mapeamento de hostgroups para clientes</div>
  <div class="muted" style="margin-bottom:10px">
    Cada hostgroup do Zabbix pode ser associado a um cliente do portal.
    O cliente verá apenas os hosts dos hostgroups vinculados a ele.
  </div>
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <?php if (!$error && !$hostgroups): ?>
    <div class="muted">Nenhum hostgroup retornado pela API. Verifique a URL e credenciais do Zabbix nas configurações.</div>
  <?php endif; ?>
  <?php if (!$error && $hostgroups): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <table class="table">
        <thead>
          <tr>
            <th>HostgroupID</th>
            <th>Nome no Zabbix</th>
            <th>Cliente vinculado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hostgroups as $g): ?>
            <?php
              $gid = (string)($g['groupid'] ?? '');
              $gname = (string)($g['name'] ?? '');
              $current = $existing[$gid]['client_user_id'] ?? null;
            ?>
            <tr>
              <td><?= h($gid) ?></td>
              <td>
                <?= h($gname) ?>
                <input type="hidden" name="hostgroup_name[<?= h($gid) ?>]" value="<?= h($gname) ?>">
              </td>
              <td>
                <select name="binding[<?= h($gid) ?>]">
                  <option value="">Nenhum</option>
                  <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ((string)$c['id'] === (string)$current) ? 'selected' : '' ?>>
                      <?= h((string)$c['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="margin-top:14px">
        <button class="btn primary" type="submit">Salvar mapeamento</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php
render_footer();

