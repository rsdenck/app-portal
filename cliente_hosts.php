<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('cliente');
$groupid = trim((string)($_GET['groupid'] ?? ''));
if ($groupid === '' && isset($_SESSION['cliente_hostgroupid'])) {
    $groupid = trim((string)$_SESSION['cliente_hostgroupid']);
}
$groups = zbx_hostgroups_for_client($pdo, (int)$user['id']);
$selected = null;
if ($groupid !== '') {
    $selected = zbx_hostgroup_find_for_client($pdo, (int)$user['id'], $groupid);
    if (!$selected) {
        http_response_code(404);
        exit('Hostgroup não encontrado');
    }
    $_SESSION['cliente_hostgroupid'] = $groupid;
}

$zbxError = '';
$hosts = [];
if ($selected) {
    try {
        $zbxConfig = zbx_config_from_db($pdo, $config);
        $auth = zbx_auth($zbxConfig);

        $groupIds = [];
        $mappedGroupId = (string)($selected['hostgroupid'] ?? '');
        if ($mappedGroupId !== '' && ctype_digit($mappedGroupId)) {
            $groupIds[] = $mappedGroupId;
        }

        $groupName = (string)($selected['name'] ?? '');
        if ($groupName !== '') {
            $hostgroupsResp = zbx_rpc(
                $zbxConfig,
                'hostgroup.get',
                [
                    'output' => ['groupid', 'name'],
                    'filter' => ['name' => [$groupName]],
                ],
                $auth
            );
            if (is_array($hostgroupsResp)) {
                foreach ($hostgroupsResp as $g) {
                    $gid = (string)($g['groupid'] ?? '');
                    if ($gid !== '') {
                        $groupIds[] = $gid;
                    }
                }
            }
        }

        $groupIds = array_values(array_unique($groupIds));
        if (!$groupIds) {
            throw new RuntimeException('Hostgroup não encontrado na API do Zabbix.');
        }

        $hosts = zbx_rpc(
            $zbxConfig,
            'host.get',
            [
                'groupids' => $groupIds,
                'output' => ['hostid', 'host', 'name', 'status'],
                'sortfield' => 'name',
            ],
            $auth
        );
        if (!is_array($hosts)) {
            $hosts = [];
        }

        // Pagination logic
        $perPage = 4;
        $totalHosts = count($hosts);
        $totalPages = ceil($totalHosts / $perPage);
        $page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;
        $pagedHosts = array_slice($hosts, $offset, $perPage);

    } catch (Throwable $e) {
        $zbxError = $e->getMessage();
    }
}

render_header('Cliente · Hosts', current_user());
?>
<style>
.pagination-btn {
  min-width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 10px;
  border-radius: 8px;
  background: var(--panel) !important;
  border: 1px solid var(--border) !important;
  color: var(--text) !important;
  font-weight: 500;
  transition: all 0.2s ease;
  text-decoration: none;
}
.pagination-btn:hover {
  border-color: var(--primary) !important;
  color: var(--primary) !important;
}
.pagination-btn.active {
  background: rgba(39, 196, 168, 0.1) !important;
  border-color: var(--primary) !important;
  color: var(--primary) !important;
  box-shadow: 0 0 10px rgba(39, 196, 168, 0.1);
}
</style>
<div class="card">

<?php if ($zbxError): ?><div class="error"><?= h($zbxError) ?></div><?php endif; ?>

  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px">
    <div style="min-width:320px">
      <label>Hostgroup</label>
      <select name="groupid" required>
        <option value="">Selecione...</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= h((string)$g['hostgroupid']) ?>" <?= ($groupid === (string)$g['hostgroupid']) ? 'selected' : '' ?>>
            <?= h((string)$g['name']) ?> (<?= h((string)$g['hostgroupid']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">Carregar</button>
  </form>

  <?php if (!$selected): ?>
    <div class="muted">Selecione um hostgroup para listar os hosts.</div>
  <?php else: ?>
    <div class="muted" style="margin-bottom:8px">
      Hostgroup selecionado: <?= h((string)($selected['name'] ?? '')) ?> (<?= h((string)($selected['hostgroupid'] ?? '')) ?>)
    </div>
    <?php if (!$hosts): ?>
      <div class="muted">Nenhum host retornado pela API.</div>
    <?php else: ?>
      <div class="host-list">
        <?php foreach ($pagedHosts as $hrow): ?>
          <?php
            $status = ((string)($hrow['status'] ?? '0') === '0') ? 'enabled' : 'disabled';
            $hostId = (string)($hrow['hostid'] ?? '');
            $name = (string)($hrow['name'] ?? '');
            $hostName = (string)($hrow['host'] ?? '');
          ?>
          <div class="host-card">
            <div class="host-card-header">
              <div>
                <div class="host-card-title"><?= h($name !== '' ? $name : $hostName) ?></div>
                <div class="host-card-meta">
                  HostID <?= h($hostId) ?> · <?= h($hostName) ?>
                </div>
                <div class="host-card-meta">
                  Hostgroup <?= h((string)($selected['name'] ?? '')) ?> (<?= h((string)($selected['hostgroupid'] ?? '')) ?>)
                </div>
              </div>
              <div class="host-card-actions">
                <?php if ($hostId !== ''): ?>
                  <a class="btn primary" href="/cliente_host.php?hostid=<?= h($hostId) ?>">Visualizar</a>
                <?php endif; ?>
              </div>
            </div>
            <div class="host-card-meta">Status: <span class="badge"><?= h($status) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;align-items:center">
          <?php
            $params = $_GET;
            $buildUrl = function($p) use ($params) {
                $params['page'] = $p;
                return '?' . http_build_query($params);
            };

            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
          ?>
          
          <?php if ($start > 1): ?>
            <a class="pagination-btn" href="<?= $buildUrl(1) ?>">1</a>
            <?php if ($start > 2): ?><span class="muted">...</span><?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
            <a class="pagination-btn <?= $i === $page ? 'active' : '' ?>" href="<?= $buildUrl($i) ?>"><?= $i ?></a>
          <?php endfor; ?>

          <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="muted">...</span><?php endif; ?>
            <a class="pagination-btn" href="<?= $buildUrl($totalPages) ?>">Último</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
render_footer();
