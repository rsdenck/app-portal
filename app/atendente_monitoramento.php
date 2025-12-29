<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
$zbxError = '';
$hosts = [];
$hostgroupid = trim((string)($_GET['hostgroupid'] ?? ''));
$clientFilter = trim((string)($_GET['client_user_id'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));

try {
    $groups = zbx_hostgroups_with_clients($pdo);
} catch (Throwable $e) {
    $groups = [];
}

$zbxConfig = zbx_config_from_db($pdo, $config);

$groupClientMap = [];
$clients = [];
foreach ($groups as $g) {
    $gid = (string)($g['hostgroupid'] ?? '');
    $cid = (string)($g['client_user_id'] ?? '');
    if ($gid !== '') {
        $groupClientMap[$gid][] = $g;
    }
    if ($cid !== '') {
        if (!isset($clients[$cid])) {
            $clients[$cid] = [
                'id' => $cid,
                'name' => (string)($g['client_name'] ?? ''),
            ];
        }
    }
}

$allHostgroups = [];

try {
    $auth = zbx_auth($zbxConfig);

    $hostgroupsResp = zbx_rpc(
        $zbxConfig,
        'hostgroup.get',
        [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
        ],
        $auth
    );
    if (is_array($hostgroupsResp)) {
        $allHostgroups = $hostgroupsResp;
    }

    $params = [
        'output' => ['hostid', 'host', 'name', 'status'],
        'sortfield' => 'name',
        'selectGroups' => ['groupid', 'name'],
    ];
    if ($hostgroupid !== '') {
        $params['groupids'] = [$hostgroupid];
    }
    $hosts = zbx_rpc($zbxConfig, 'host.get', $params, $auth);
    if (!is_array($hosts)) {
        $hosts = [];
    }
} catch (Throwable $e) {
    $zbxError = $e->getMessage();
    $hosts = [];
    $allHostgroups = [];
}

if ($clientFilter !== '' || $search !== '' || $hostgroupid !== '') {
    $filtered = [];
    foreach ($hosts as $hrow) {
        $hostId = (string)($hrow['hostid'] ?? '');
        $name = (string)($hrow['name'] ?? '');
        $hostName = (string)($hrow['host'] ?? '');
        $groupsForHost = isset($hrow['groups']) && is_array($hrow['groups']) ? $hrow['groups'] : [];

        if ($hostgroupid !== '') {
            $matchesGroup = false;
            foreach ($groupsForHost as $hg) {
                if ((string)($hg['groupid'] ?? '') === $hostgroupid) {
                    $matchesGroup = true;
                    break;
                }
            }
            if (!$matchesGroup) {
                continue;
            }
        }

        if ($clientFilter !== '') {
            $matchesClient = false;
            foreach ($groupsForHost as $hg) {
                $gid = (string)($hg['groupid'] ?? '');
                if ($gid !== '' && isset($groupClientMap[$gid])) {
                    foreach ($groupClientMap[$gid] as $binding) {
                        if ((string)($binding['client_user_id'] ?? '') === $clientFilter) {
                            $matchesClient = true;
                            break 2;
                        }
                    }
                }
            }
            if (!$matchesClient) {
                continue;
            }
        }

        if ($search !== '') {
            $needle = strtolower($search);
            $haystack = strtolower($name . ' ' . $hostName . ' ' . $hostId);
            if (strpos($haystack, $needle) === false) {
                continue;
            }
        }

        $filtered[] = $hrow;
    }
    $hosts = $filtered;
}

// Pagination logic
$perPage = 4;
$totalHosts = count($hosts);
$totalPages = ceil($totalHosts / $perPage);
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pagedHosts = array_slice($hosts, $offset, $perPage);

$zabbixUrl = (string)($zbxConfig['zabbix']['url'] ?? '');
$zabbixUiBase = $zabbixUrl !== '' ? preg_replace('~/api_jsonrpc\.php$~', '/zabbix.php', $zabbixUrl) : '';

render_header('Atendente · Monitoramento', current_user());
?>
<div class="card">
  <?php if ($zbxError): ?><div class="error"><?= h($zbxError) ?></div><?php endif; ?>

  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px">
    <div style="min-width:220px">
      <label>Cliente</label>
      <select name="client_user_id">
        <option value="">Todos</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= h((string)$c['id']) ?>" <?= $clientFilter === (string)$c['id'] ? 'selected' : '' ?>>
            <?= h((string)$c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:260px">
      <label>Hostgroup</label>
      <select name="hostgroupid">
        <option value="">Todos</option>
        <?php foreach ($allHostgroups as $g): ?>
          <?php
            $gid = (string)($g['groupid'] ?? '');
            $gname = (string)($g['name'] ?? '');
            $clientLabel = '';
            if ($gid !== '' && isset($groupClientMap[$gid])) {
                $binding = $groupClientMap[$gid][0];
                $clientLabel = (string)($binding['client_name'] ?? '');
            }
          ?>
          <option value="<?= h($gid) ?>" <?= $hostgroupid === $gid ? 'selected' : '' ?>>
            <?= h(($clientLabel !== '' ? $clientLabel . ' · ' : '') . $gname . ' (' . $gid . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:220px">
      <label>Busca</label>
      <input name="q" value="<?= h($search) ?>" placeholder="Nome ou host">
    </div>
    <button class="btn" type="submit">Filtrar</button>
  </form>

  <?php if (!$hosts && !$zbxError): ?>
    <div class="muted">Nenhum host retornado pela API.</div>
  <?php elseif ($hosts): ?>
    <div class="host-list">
      <?php foreach ($pagedHosts as $hrow): ?>
          <?php
            $status = ((string)($hrow['status'] ?? '0') === '0') ? 'enabled' : 'disabled';
            $hostId = (string)($hrow['hostid'] ?? '');
            $name = (string)($hrow['name'] ?? '');
            $hostName = (string)($hrow['host'] ?? '');
            $groupsForHost = isset($hrow['groups']) && is_array($hrow['groups']) ? $hrow['groups'] : [];
            $clientLabel = '';
            $groupLabels = [];
            foreach ($groupsForHost as $hg) {
                $gid = (string)($hg['groupid'] ?? '');
                $gname = (string)($hg['name'] ?? '');
                if ($gid !== '') {
                    $groupLabels[] = $gname !== '' ? ($gname . ' (' . $gid . ')') : $gid;
                    if ($clientLabel === '' && isset($groupClientMap[$gid])) {
                        $binding = $groupClientMap[$gid][0];
                        $clientLabel = (string)($binding['client_name'] ?? '');
                    }
                }
            }
          ?>
          <div class="host-card">
            <div class="host-card-header">
              <div>
                <div class="host-card-title"><?= h($name !== '' ? $name : $hostName) ?></div>
                <div class="host-card-meta">
                  HostID <?= h($hostId) ?> · <?= h($hostName) ?>
                  <?php if ($clientLabel !== ''): ?>
                    · Cliente <?= h($clientLabel) ?>
                  <?php endif; ?>
                </div>
                <?php if ($groupLabels): ?>
                  <div class="host-card-meta"><?= h(implode(' | ', $groupLabels)) ?></div>
                <?php endif; ?>
              </div>
              <div class="host-card-actions">
                <?php if ($hostId !== ''): ?>
                  <a class="btn primary" href="/app/atendente_host.php?hostid=<?= h($hostId) ?>">Visualizar</a>
                <?php endif; ?>
                <?php if ($zabbixUiBase !== '' && $hostId !== ''): ?>
                  <a class="btn" href="<?= h($zabbixUiBase . '?action=host.view&hostid=' . rawurlencode($hostId)) ?>" target="_blank" rel="noopener noreferrer">Zabbix</a>
                <?php endif; ?>
              </div>
            </div>
            <div class="host-card-meta">Status: <span class="badge"><?= h($status) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top:30px;display:flex;gap:10px;justify-content:center;align-items:center;padding:10px">
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
      <?php endif; ?>
  <?php endif; ?>
</div>
<?php
render_footer();



