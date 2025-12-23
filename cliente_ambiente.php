<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('cliente');

$groups = zbx_hostgroups_for_client($pdo, (int)$user['id']);
$zbxError = '';
$hostCounts = [];
try {
    $zbxConfig = zbx_config_from_db($pdo, $config);
    $auth = zbx_auth($zbxConfig);
    foreach ($groups as $g) {
        $gids = [];
        $dbGid = trim((string)($g['hostgroupid'] ?? ''));
        if ($dbGid !== '' && ctype_digit($dbGid)) {
            $gids[] = $dbGid;
        }
        
        $gname = trim((string)($g['name'] ?? ''));
        if ($gname !== '') {
            try {
                $found = zbx_rpc($zbxConfig, 'hostgroup.get', [
                    'output' => ['groupid'],
                    'filter' => ['name' => [$gname]]
                ], $auth);
                if (is_array($found)) {
                    foreach ($found as $fg) {
                        $fgid = (string)($fg['groupid'] ?? '');
                        if ($fgid !== '') {
                            $gids[] = $fgid;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }
        
        $gids = array_unique($gids);
        if (!$gids) {
            $hostCounts[(string)$g['hostgroupid']] = '0';
            continue;
        }

        $count = zbx_rpc($zbxConfig, 'host.get', ['groupids' => array_values($gids), 'countOutput' => true], $auth);
        $hostCounts[(string)$g['hostgroupid']] = is_string($count) ? $count : (string)$count;
    }
} catch (Throwable $e) {
    $zbxError = $e->getMessage();
}

render_header('Cliente · Ambiente (Zabbix)', current_user());
?>
<div class="card">
  <?php if ($zbxError): ?><div class="error"><?= h($zbxError) ?></div><?php endif; ?>

  <div style="font-weight:700;margin-bottom:10px">Hostgroups do cliente</div>
  <table class="table">
    <thead>
      <tr>
        <th>HostgroupID</th>
        <th>Nome</th>
        <th>Hosts</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$groups): ?>
        <tr><td colspan="4" class="muted">Nenhum hostgroup configurado.</td></tr>
      <?php endif; ?>
      <?php foreach ($groups as $g): ?>
        <?php $gid = (string)$g['hostgroupid']; ?>
        <tr>
          <td><?= h($gid) ?></td>
          <td><?= h((string)$g['name']) ?></td>
          <td><?= h((string)($hostCounts[$gid] ?? '')) ?></td>
          <td><a class="btn" href="/cliente_hosts.php?groupid=<?= h($gid) ?>">Ver hosts</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();
