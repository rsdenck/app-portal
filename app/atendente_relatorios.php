<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');

$period = safe_int($_GET['period'] ?? 30);
$company_id = safe_int($_GET['company_id'] ?? null);
$host_id = (string)($_GET['host_id'] ?? '');
$tab = (string)($_GET['tab'] ?? 'technical');
$generate = safe_int($_GET['generate'] ?? 0);

$timeTill = time();
$timeFrom = $timeTill - ($period * 86400);

// List companies for the filter
$stmt = $pdo->query('SELECT id, name FROM companies ORDER BY name ASC');
$companies = $stmt->fetchAll();

$availableHosts = [];
if ($company_id) {
    try {
        $zbxConfig = zbx_config_from_db($pdo, $config);
        $auth = zbx_auth($zbxConfig);
        $stmt = $pdo->prepare('SELECT zh.hostgroupid, zh.name FROM zabbix_hostgroups zh JOIN client_profiles cp ON cp.user_id = zh.client_user_id WHERE cp.company_id = ?');
        $stmt->execute([$company_id]);
        $hgs = $stmt->fetchAll();
        $gIds = [];
        foreach ($hgs as $hg) {
            $resp = zbx_rpc($zbxConfig, 'hostgroup.get', ['output' => ['groupid'], 'filter' => ['name' => [$hg['name']]]], $auth);
            if ($resp) foreach ($resp as $r) $gIds[] = $r['groupid'];
        }
        if (!empty($gIds)) {
            $availableHosts = zbx_rpc($zbxConfig, 'host.get', [
                'groupids' => array_values(array_unique($gIds)),
                'output' => ['hostid', 'name']
            ], $auth);
        }
    } catch (Exception $e) {
        // Silently fail for dropdown
    }
}

$reportData = [];
$zbxError = '';

if ($generate) {
    try {
        if ($tab === 'technical' && empty($host_id)) {
            throw new RuntimeException('Para o Relatório Técnico (Consumo), selecione um Host específico.');
        }

        $zbxConfig = zbx_config_from_db($pdo, $config);
        $auth = zbx_auth($zbxConfig);

        // 1. Get hostgroups and hosts only if needed (all tabs except SLA)
        $hosts = [];
        if ($tab !== 'sla') {
            // 1. Get hostgroups for the selected company or all
            $hostgroups = [];
            if ($company_id) {
                $stmt = $pdo->prepare('SELECT zh.hostgroupid, zh.name FROM zabbix_hostgroups zh JOIN client_profiles cp ON cp.user_id = zh.client_user_id WHERE cp.company_id = ?');
                $stmt->execute([$company_id]);
                $hostgroups = $stmt->fetchAll();
            } else {
                $stmt = $pdo->query('SELECT hostgroupid, name FROM zabbix_hostgroups');
                $hostgroups = $stmt->fetchAll();
            }

            $groupIds = [];
            foreach ($hostgroups as $hg) {
                // We need to find the real group IDs in Zabbix by name
                $resp = zbx_rpc($zbxConfig, 'hostgroup.get', [
                    'output' => ['groupid'],
                    'filter' => ['name' => [$hg['name']]]
                ], $auth);
                if ($resp) {
                    foreach ($resp as $r) $groupIds[] = $r['groupid'];
                }
            }
            $groupIds = array_unique($groupIds);

            if (empty($groupIds)) {
                if ($tab === 'technical') {
                    throw new RuntimeException('Nenhum grupo de hosts encontrado no Zabbix para esta empresa.');
                }
            } else {
                // 2. Get hosts in these groups
                $hostParams = [
                    'groupids' => array_values($groupIds),
                    'output' => ['hostid', 'name', 'host'],
                    'selectInterfaces' => ['ip'],
                    'sortfield' => 'name'
                ];
                if ($host_id) {
                    $hostParams['hostids'] = $host_id;
                }
                $hosts = zbx_rpc($zbxConfig, 'host.get', $hostParams, $auth);
            }
        }

        // 3. Get tickets for SLA/SLI/SLO
        $ticketData = [];
        if ($company_id) {
            $stmt = $pdo->prepare("
                SELECT t.*, u.name as client_name, a.name as assigned_name,
                       (SELECT created_at FROM ticket_history WHERE ticket_id = t.id AND action = 'comment' ORDER BY created_at ASC LIMIT 1) as first_response_at
                FROM tickets t
                LEFT JOIN users u ON t.client_user_id = u.id
                LEFT JOIN users a ON t.assigned_user_id = a.id
                JOIN client_profiles cp ON cp.user_id = t.client_user_id
                WHERE cp.company_id = ? AND t.created_at >= ?
            ");
            $stmt->execute([$company_id, date('Y-m-d H:i:s', $timeFrom)]);
            $tickets = $stmt->fetchAll();
            foreach ($tickets as $tk) {
                $ticketData[] = ticket_calculate_metrics($tk, $pdo);
            }
        }

        // 3.5. Dashboard Data (Audit logs, specific ticket trends)
        $dashboardData = [];
        if ($tab === 'dashboard') {
            // Ticket creation trend
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as day, COUNT(*) as total 
                FROM tickets 
                WHERE created_at >= ? " . ($company_id ? " AND client_user_id IN (SELECT user_id FROM client_profiles WHERE company_id = ?)" : "") . "
                GROUP BY DATE(created_at) 
                ORDER BY day ASC
            ");
            $params = [date('Y-m-d H:i:s', $timeFrom)];
            if ($company_id) $params[] = $company_id;
            $stmt->execute($params);
            $dashboardData['tickets_trend'] = $stmt->fetchAll();

            // Calculate SLA for dashboard
            $slaMet = 0; $slaTotal = count($ticketData);
            $slaByDay = [];
            foreach ($ticketData as $td) { 
                if ($td['is_within_sla']) $slaMet++; 
                $day = date('Y-m-d', strtotime($td['created_at']));
                if (!isset($slaByDay[$day])) $slaByDay[$day] = ['total' => 0, 'met' => 0];
                $slaByDay[$day]['total']++;
                if ($td['is_within_sla']) $slaByDay[$day]['met']++;
            }
            $slaPercent = $slaTotal > 0 ? ($slaMet / $slaTotal) * 100 : 100;
            
            // SLA Trend (last 7 days for the sparkline)
            $slaTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                if (isset($slaByDay[$d])) {
                    $slaTrend[] = ($slaByDay[$d]['met'] / $slaByDay[$d]['total']) * 100;
                } else {
                    $slaTrend[] = 100;
                }
            }
            $dashboardData['sla_trend'] = $slaTrend;

            // Zabbix Alerts Data
            $hIds = array_column($hosts, 'hostid');
            $dashboardData['zabbix_active_alerts'] = zbx_get_active_alerts($zbxConfig, $auth, $hIds);
            $dashboardData['zabbix_alerts_count'] = count($dashboardData['zabbix_active_alerts']);
            $dashboardData['zabbix_alerts_trend'] = zbx_get_alerts_trend($zbxConfig, $auth, $hIds);
        }

        // 4. Batch get items and trends for all hosts (all tabs except SLA)
        if ($tab !== 'sla' && !empty($hosts)) {
            $hostIds = array_column($hosts, 'hostid');
            
            // 4.1 Batch Get Items for all hosts (chunked to avoid memory/500 errors)
            $allItems = [];
            $hostIdChunks = array_chunk($hostIds, 50);
            
            foreach ($hostIdChunks as $chunk) {
                $chunkItems = zbx_rpc($zbxConfig, 'item.get', [
                    'hostids' => $chunk,
                    'output' => ['itemid', 'hostid', 'name', 'key_', 'units', 'value_type', 'lastvalue'],
                    'search' => [
                        'key_' => [
                            'system.cpu.util', 'cpu.util', 'system.cpu.load', 'system.cpu.util[,idle]', 'system.cpu.num',
                            'vm.memory.size', 'memory.size', 'vm.memory.util', 'memory.util',
                            'vfs.fs.size', 'vfs.fs.util', 'vmware.vm.vfs.fs.size',
                            'net.if', 'agent.ping', 'icmpping', 'proc.num', 'system.uptime',
                            'perf_counter', 'processor time', 'memory used', 'memory total', 'memory available',
                            'system.resource.cpu-load', 'system.resource.cpu-count',
                            'system.resource.total-memory', 'system.resource.used-memory', 'system.resource.free-memory',
                            'system.resource.total-hdd-space', 'system.resource.used-hdd-space', 'system.resource.free-hdd-space',
                            'hrStorageSize', 'hrStorageUsed', 'vmware.vm.cpu', 'vmware.vm.memory'
                        ],
                        'name' => [
                            'cpu', 'memória', 'memory', 'disco', 'disk', 'vcpus', 'rede', 'network'
                        ]
                    ],
                    'searchByAny' => true
                ], $auth);
                
                if (is_array($chunkItems)) {
                    $allItems = array_merge($allItems, $chunkItems);
                }
            }

            // Group items by host
            $itemsByHost = [];
            foreach ($allItems as $item) {
                $itemsByHost[$item['hostid']][] = $item;
            }

            // 4.2 Collect all item IDs for trends
            $allItemIds = array_column($allItems, 'itemid');
            $allTrends = [];
            if (!empty($allItemIds)) {
                $allTrends = zbx_get_trends($zbxConfig, $auth, $allItemIds, $timeFrom, $timeTill);
            }

            // Group trends by item
            $trendsByItem = [];
            foreach ($allTrends as $t) {
                $trendsByItem[$t['itemid']][] = $t;
            }

            // 4.3 Process each host with pre-fetched data
            foreach ($hosts as $h) {
                $hostId = $h['hostid'];
                $items = $itemsByHost[$hostId] ?? [];
            
                $hostItems = [
                    'cpu' => null, 'cpu_idle' => null, 'cpu_count' => null,
                    'mem_total' => null, 'mem_used' => null, 'mem_free' => null, 'mem_available' => null, 'mem_pused' => null,
                    'disks' => [], 'net_in' => null, 'net_out' => null, 'availability' => null
                ];

                foreach ($items as $item) {
                    $key = strtolower($item['key_']);
                    $name = strtolower($item['name']);
                    
                    if (strpos($key, 'system.cpu.num') !== false || strpos($key, 'system.resource.cpu-count') !== false || strpos($key, 'vmware.vm.cpu.num') !== false || strpos($name, 'vcpus') !== false || (strpos($name, 'cpu') !== false && (strpos($name, 'número') !== false || strpos($name, 'number') !== false || strpos($name, 'atribuídas') !== false))) {
                        if (!$hostItems['cpu_count'] || strpos($key, 'system.cpu.num') !== false || strpos($key, 'vmware.vm.cpu.num') !== false) $hostItems['cpu_count'] = $item;
                    }
                    elseif (strpos($key, 'cpu.util') !== false || strpos($key, 'system.cpu.util') !== false || strpos($key, 'vmware.vm.cpu.usage') !== false || strpos($key, 'processor time') !== false || strpos($key, 'system.resource.cpu-load') !== false || strpos($name, 'cpu utilization') !== false || strpos($name, 'uso de cpu') !== false || (strpos($name, 'cpu') !== false && strpos($name, 'porcentagem') !== false)) {
                        if (strpos($key, 'idle') !== false || strpos($name, 'idle') !== false) $hostItems['cpu_idle'] = $item;
                        elseif (!$hostItems['cpu'] || strpos($key, 'avg1') !== false || strpos($key, '_total') !== false || strpos($name, 'total') !== false || strpos($key, 'cpu-load') !== false || strpos($name, 'porcentagem') !== false || strpos($key, 'vmware.vm.cpu.usage') !== false) $hostItems['cpu'] = $item;
                    } elseif (strpos($key, 'system.cpu.load') !== false && !$hostItems['cpu']) {
                        $hostItems['cpu'] = $item;
                    }
                    elseif (strpos($key, 'vm.memory.size[total]') !== false || strpos($key, 'memory.size[total]') !== false || strpos($key, 'vmware.vm.memory.size.total') !== false || strpos($key, 'system.resource.total-memory') !== false || (strpos($name, 'memória') !== false && strpos($name, 'total') !== false) || (strpos($name, 'memory') !== false && strpos($name, 'total') !== false) || strpos($key, 'memory total') !== false) {
                        $hostItems['mem_total'] = $item;
                    } elseif (strpos($key, 'vm.memory.size[used]') !== false || strpos($key, 'memory.size[used]') !== false || strpos($key, 'vmware.vm.memory.size.usage') !== false || strpos($key, 'system.resource.used-memory') !== false || (strpos($name, 'memória') !== false && (strpos($name, 'usada') !== false || strpos($name, 'uso') !== false)) || (strpos($name, 'memory') !== false && (strpos($name, 'used') !== false || strpos($name, 'usage') !== false))) {
                        if (strpos($name, 'porcentagem') === false && strpos($name, '%') === false) $hostItems['mem_used'] = $item;
                        else $hostItems['mem_pused'] = $item;
                    } elseif (strpos($key, 'vm.memory.size[available]') !== false || strpos($key, 'memory.size[available]') !== false || strpos($key, 'system.resource.free-memory') !== false || (strpos($name, 'memória') !== false && (strpos($name, 'disponível') !== false || strpos($name, 'livre') !== false)) || (strpos($name, 'memory') !== false && (strpos($name, 'available') !== false || strpos($name, 'free') !== false))) {
                        $hostItems['mem_available'] = $item;
                    } elseif (strpos($key, 'vm.memory.size[free]') !== false || strpos($key, 'memory.size[free]') !== false || (strpos($name, 'memória') !== false && strpos($name, 'livre') !== false) || (strpos($name, 'memory') !== false && strpos($name, 'free') !== false)) {
                        $hostItems['mem_free'] = $item;
                    } elseif (strpos($key, 'vm.memory.size[pused]') !== false || strpos($key, 'vm.memory.util') !== false || strpos($key, 'memory.util') !== false || strpos($name, 'uso de memória %') !== false || strpos($name, 'memory utilization') !== false || (strpos($name, 'memória') !== false && strpos($name, 'porcentagem') !== false)) {
                        $hostItems['mem_pused'] = $item;
                    }
                    elseif (strpos($key, 'vfs.fs.size') !== false || strpos($key, 'vfs.fs.util') !== false || strpos($key, 'hrstoragesize') !== false || strpos($key, 'hrstorageused') !== false || strpos($key, 'total-hdd-space') !== false || strpos($key, 'used-hdd-space') !== false || strpos($key, 'free-hdd-space') !== false || strpos($name, 'disk') !== false || strpos($name, 'disco') !== false || strpos($name, 'espaço') !== false || strpos($name, 'tamanho') !== false) {
                        $mount = null;
                        if (preg_match('/\[([^\]]+)\]/', $item['key_'], $m)) {
                            $params = explode(',', $m[1]);
                            if (strpos($key, 'vmware.vm.vfs.fs.size') !== false && count($params) >= 3) $mount = trim($params[2]);
                            else $mount = trim($params[0]);
                        }
                        $invalidMounts = ['total', 'used', 'free', 'pused', 'pfree', 'available', 'util'];
                        if ($mount && in_array(strtolower($mount), $invalidMounts)) $mount = null;
                        if (!$mount || $mount === '') {
                            if (preg_match('/([a-zA-Z]:)/', $name, $m2)) $mount = strtoupper($m2[1]);
                            elseif (preg_match('/([a-zA-Z]:)/', $item['key_'], $m2)) $mount = strtoupper($m2[1]);
                            elseif (strpos($name, ' / ') !== false || strpos($name, ' /') !== false || $name === '/') $mount = '/';
                            elseif (strpos($key, '/,') !== false || strpos($key, '[/]') !== false) $mount = '/';
                        }
                        if (!$mount || $mount === '') $mount = 'Geral';
                        if (preg_match('/^[a-zA-Z]:$/', $mount)) $mount = strtoupper($mount);
                        if (!isset($hostItems['disks'][$mount])) $hostItems['disks'][$mount] = ['total' => null, 'used' => null, 'free' => null, 'pused' => null, 'mount' => $mount];
                        if (strpos($key, 'total') !== false || strpos($key, 'hrstoragesize') !== false || strpos($key, 'total-hdd-space') !== false || strpos($name, 'total') !== false) {
                            if (!$hostItems['disks'][$mount]['total'] || strpos($key, 'vfs.fs') !== false) $hostItems['disks'][$mount]['total'] = $item;
                        } elseif (strpos($key, 'used') !== false || strpos($key, 'hrstorageused') !== false || strpos($key, 'used-hdd-space') !== false || strpos($name, 'usado') !== false || strpos($name, 'used') !== false) {
                            if (!$hostItems['disks'][$mount]['used'] || strpos($key, 'vfs.fs') !== false) $hostItems['disks'][$mount]['used'] = $item;
                        } elseif (strpos($key, 'free') !== false || strpos($key, 'available') !== false || strpos($key, 'free-hdd-space') !== false || strpos($name, 'livre') !== false || strpos($name, 'free') !== false) {
                            if (!$hostItems['disks'][$mount]['free'] || strpos($key, 'vfs.fs') !== false) $hostItems['disks'][$mount]['free'] = $item;
                        } elseif (strpos($key, 'pused') !== false || strpos($key, 'pfree') !== false || strpos($key, 'util') !== false || strpos($name, 'utilization') !== false || strpos($name, 'uso %') !== false) {
                            if (!$hostItems['disks'][$mount]['pused'] || strpos($key, 'vfs.fs') !== false) $hostItems['disks'][$mount]['pused'] = $item;
                        }
                    }
                    elseif (strpos($key, 'net.if.in') !== false) {
                        if (!$hostItems['net_in'] || strpos($key, 'eth0') !== false || strpos($key, 'ens') !== false || strpos($key, 'bond') !== false) $hostItems['net_in'] = $item;
                    } elseif (strpos($key, 'net.if.out') !== false) {
                        if (!$hostItems['net_out'] || strpos($key, 'eth0') !== false || strpos($key, 'ens') !== false || strpos($key, 'bond') !== false) $hostItems['net_out'] = $item;
                    }
                    elseif (strpos($key, 'agent.ping') !== false || strpos($key, 'icmpping') !== false) {
                        $hostItems['availability'] = $item;
                    }
                }

                // Collect trends for this host's items
                $hostTrends = [];
                $hItemIds = [];
                foreach ($hostItems as $k => $it) {
                    if ($k === 'disks') { foreach ($it as $d) { if ($d['total']) $hItemIds[] = $d['total']['itemid']; if ($d['used']) $hItemIds[] = $d['used']['itemid']; if ($d['free']) $hItemIds[] = $d['free']['itemid']; if ($d['pused']) $hItemIds[] = $d['pused']['itemid']; } }
                    elseif ($it) $hItemIds[] = $it['itemid'];
                }
                foreach ($hItemIds as $iid) {
                    if (isset($trendsByItem[$iid])) $hostTrends = array_merge($hostTrends, $trendsByItem[$iid]);
                }

                // CPU Count last value (can't easily batch history.get without more complex mapping, but let's try to use lastvalue)
                $cpuCountVal = null;
                if ($hostItems['cpu_count']) {
                    if (isset($hostItems['cpu_count']['lastvalue']) && $hostItems['cpu_count']['lastvalue'] !== '') {
                        $cpuCountVal = (float)$hostItems['cpu_count']['lastvalue'];
                    }
                }

                $reportData[] = [
                    'host' => $h,
                    'items' => $hostItems,
                    'trends' => $hostTrends,
                    'cpu_count' => $cpuCountVal
                ];
            }
        }
    } catch (Throwable $e) {
        $zbxError = $e->getMessage();
    }
}

render_header('Atendente · Relatórios', current_user());
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div>
            <h2 style="margin:0">Relatórios</h2>
            <div class="muted">Gere relatórios técnicos e gerenciais detalhados e visualize KPIs.</div>
        </div>
    </div>

    <?php if ($zbxError): ?><div class="error" style="margin-bottom:20px"><?= h($zbxError) ?></div><?php endif; ?>

    <div class="tabs-container" style="margin-bottom:20px">
        <div class="tabs" style="display:flex; gap:10px; border-bottom: 1px solid var(--border); padding-bottom:10px">
            <a href="?tab=technical&period=<?= $period ?>&company_id=<?= $company_id ?>&host_id=<?= $host_id ?>" class="tab-btn <?= $tab === 'technical' ? 'active' : '' ?>" style="text-decoration:none; padding:8px 16px; border-radius:4px; <?= $tab === 'technical' ? 'background:var(--primary); color:white' : 'color:var(--text)' ?>" onclick="updateHostSelect('technical')">Técnico (Consumo)</a>
            <a href="?tab=sla&period=<?= $period ?>&company_id=<?= $company_id ?>" class="tab-btn <?= $tab === 'sla' ? 'active' : '' ?>" style="text-decoration:none; padding:8px 16px; border-radius:4px; <?= $tab === 'sla' ? 'background:var(--primary); color:white' : 'color:var(--text)' ?>" onclick="updateHostSelect('sla')">SLA & Chamados</a>
            <a href="?tab=dashboard&period=<?= $period ?>&company_id=<?= $company_id ?>" class="tab-btn <?= $tab === 'dashboard' ? 'active' : '' ?>" style="text-decoration:none; padding:8px 16px; border-radius:4px; <?= $tab === 'dashboard' ? 'background:var(--primary); color:white' : 'color:var(--text)' ?>" onclick="updateHostSelect('dashboard')">Dashboard Executivo</a>
            <a href="?tab=billing&period=<?= $period ?>&company_id=<?= $company_id ?>" class="tab-btn <?= $tab === 'billing' ? 'active' : '' ?>" style="text-decoration:none; padding:8px 16px; border-radius:4px; <?= $tab === 'billing' ? 'background:var(--primary); color:white' : 'color:var(--text)' ?>" onclick="updateHostSelect('billing')">Billing & Custos</a>
            <a href="?tab=kpi&period=<?= $period ?>&company_id=<?= $company_id ?>" class="tab-btn <?= $tab === 'kpi' ? 'active' : '' ?>" style="text-decoration:none; padding:8px 16px; border-radius:4px; <?= $tab === 'kpi' ? 'background:var(--primary); color:white' : 'color:var(--text)' ?>" onclick="updateHostSelect('kpi')">KPIs Globais</a>
            <a href="?tab=gerencial&period=<?= $period ?>&company_id=<?= $company_id ?>" class="tab-btn <?= $tab === 'gerencial' ? 'active' : '' ?>" style="text-decoration:none; padding:8px 16px; border-radius:4px; <?= $tab === 'gerencial' ? 'background:var(--primary); color:white' : 'color:var(--text)' ?>" onclick="updateHostSelect('gerencial')">Resumo Gerencial</a>
        </div>
    </div>

    <script>
    const getThemeColor = (variable) => getComputedStyle(document.body).getPropertyValue(variable).trim();

    function updateHostSelect(tab) {
        const select = document.getElementById('host_id_select');
        if (!select) return;
        const allOption = select.querySelector('option[value=""]');
        if (tab === 'technical') {
            allOption.disabled = true;
            allOption.style.display = 'none';
            if (select.value === "") {
                if (select.options.length > 1) {
                    select.selectedIndex = 1;
                }
            }
        } else {
            allOption.disabled = false;
            allOption.style.display = 'block';
        }
    }

    // Run on page load
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab') || 'technical';
        updateHostSelect(tab);
    });
    </script>

    <form method="get" class="filters-form" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; background:var(--bg); padding:20px; border-radius:8px; margin-bottom:20px">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <input type="hidden" name="generate" value="1">
        
        <div class="form-group">
            <label style="display:block; margin-bottom:5px; font-weight:600">Empresa</label>
            <select name="company_id" class="form-control" onchange="this.form.generate.value=0; this.form.submit()" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border); background:var(--panel); color:var(--text)">
                <option value="">Todas as Empresas</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $company_id === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label style="display:block; margin-bottom:5px; font-weight:600">Host (Opcional)</label>
            <select name="host_id" id="host_id_select" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border); background:var(--panel); color:var(--text)">
                <option value="" <?= $tab === 'technical' ? 'disabled style="display:none"' : '' ?>>Todos os Hosts</option>
                <?php foreach ($availableHosts as $h): ?>
                    <option value="<?= h($h['hostid']) ?>" <?= $host_id === $h['hostid'] ? 'selected' : '' ?>><?= h($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label style="display:block; margin-bottom:5px; font-weight:600">Período</label>
            <select name="period" class="form-control" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border); background:var(--panel); color:var(--text)">
                <option value="7" <?= $period === 7 ? 'selected' : '' ?>>Últimos 7 dias</option>
                <option value="15" <?= $period === 15 ? 'selected' : '' ?>>Últimos 15 dias</option>
                <option value="30" <?= $period === 30 ? 'selected' : '' ?>>Últimos 30 dias</option>
            </select>
        </div>

        <div class="form-group" style="display:flex; align-items:flex-end">
            <button type="submit" class="btn primary" style="width:100%">Gerar Relatório</button>
        </div>
    </form>

    <?php if ($tab === 'technical'): ?>
        <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print, .sidebar, .header-user, .tabs-container, .filters-form {
                display: none !important;
            }
            .report-content-card, .dash-card {
                break-inside: avoid;
                page-break-inside: avoid;
                border: 1px solid #ddd !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }
        .report-dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .report-resource-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 180px;
        }
        .report-resource-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .report-resource-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 14px;
            color: var(--muted);
        }
        .report-resource-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
        }
        .report-resource-chart {
            width: 100%;
            height: 100px;
            margin-top: auto;
        }
        .report-host-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .resource-badge {
            background: rgba(39, 196, 168, 0.1);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .resource-subtext {
            font-size: 12px;
            color: var(--muted);
            margin-top: -8px;
        }
        </style>

        <?php if ($generate && !empty($reportData)): ?>
        <div id="report-container">
            <?php foreach ($reportData as $idx => $data): ?>
                <?php 
                    $h = $data['host'];
                    $trends = $data['trends'];
                    $items = $data['items'];
                    
                    // Group trends by itemid
                    $groupedTrends = [];
                    foreach ($trends as $t) {
                        $groupedTrends[$t['itemid']][] = $t;
                    }

                    // Helper for getting last value from trends or fallback to item's lastvalue
                    $getLastVal = function($item) use ($groupedTrends) {
                        if (!$item) return null;
                        $itemId = $item['itemid'];
                        if (isset($groupedTrends[$itemId]) && !empty($groupedTrends[$itemId])) {
                            $last = end($groupedTrends[$itemId]);
                            return (float)$last['value_avg'];
                        }
                        // Fallback to Zabbix item's lastvalue if trends are empty
                        if (isset($item['lastvalue']) && $item['lastvalue'] !== '' && $item['lastvalue'] !== null) {
                            return (float)$item['lastvalue'];
                        }
                        return null;
                    };

                    // CPU Current & vCPUs
                    $cpuUtil = $getLastVal($items['cpu'] ?? null);
                    if ($cpuUtil === null) {
                        $idle = $getLastVal($items['cpu_idle'] ?? null);
                        if ($idle !== null) $cpuUtil = 100 - $idle;
                        else {
                            $cpuUtil = $getLastVal($items['cpu_load'] ?? null);
                            // If it's load and we have cpu count, convert to percentage roughly
                            if ($cpuUtil !== null && $cpuCountVal > 0 && $cpuUtil > $cpuCountVal * 0.5) {
                                // This is a very rough heuristic, but better than nothing
                                // If load > count, it's high. We don't want to show load as % directly if it's > 100
                            }
                        }
                    }
                    $cpuCountVal = (int)($data['cpu_count'] ?? 0);
                    if ($cpuCountVal <= 0 && isset($items['cpu_count']['lastvalue'])) {
                        $cpuCountVal = (int)$items['cpu_count']['lastvalue'];
                    }

                    // Memory
                    $memTotal = $getLastVal($items['mem_total'] ?? null);
                    $memUsed = $getLastVal($items['mem_used'] ?? null);
                    $memFree = $getLastVal($items['mem_free'] ?? null);
                    $memAvailable = $getLastVal($items['mem_available'] ?? null);
                    $memPUsed = $getLastVal($items['mem_pused'] ?? null);
                    
                    if ($memUsed === null && $memTotal !== null) {
                        if ($memAvailable !== null) $memUsed = $memTotal - $memAvailable;
                        elseif ($memFree !== null) $memUsed = $memTotal - $memFree;
                        elseif ($memPUsed !== null) $memUsed = ($memPUsed * $memTotal) / 100;
                    }
                    if ($memPUsed === null && $memTotal > 0 && $memUsed !== null) {
                        $memPUsed = ($memUsed / $memTotal) * 100;
                    }
                    if ($memAvailable === null) {
                        if ($memFree !== null) $memAvailable = $memFree;
                        elseif ($memTotal !== null && $memUsed !== null) $memAvailable = $memTotal - $memUsed;
                    }

                    // Network Rates (Last value in bps)
                    $netInRate = $getLastVal($items['net_in'] ?? null);
                    $netOutRate = $getLastVal($items['net_out'] ?? null);
                ?>
                <div class="host-report-section" style="margin-bottom: 60px;">
                    <div class="report-host-header">
                        <div>
                            <h2 style="margin:0; font-size: 24px; font-weight: 800;"><?= h($h['name']) ?></h2>
                            <div class="muted" style="font-size: 14px; margin-top: 4px;">
                                HostID: <?= h($h['hostid']) ?> · IP: <?= h($h['interfaces'][0]['ip'] ?? 'N/A') ?> · Período: <?= $period ?> dias
                            </div>
                        </div>
                        <div class="resource-badge" style="padding: 8px 16px; font-size: 14px;">Relatório de Consumo</div>
                    </div>

                    <div class="report-dash-grid">
                        <!-- CPU Card -->
                        <div class="report-resource-card">
                            <div class="report-resource-header">
                                <div class="report-resource-title">
                                    <svg style="width:18px;height:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="4" y="4" width="16" height="16" rx="2" />
                                        <rect x="9" y="9" width="6" height="6" />
                                        <path d="M15 2v2M9 2v2M20 15h2M20 9h2M15 20v2M9 20v2M2 15h2M2 9h2" />
                                    </svg>
                                    CPU
                                </div>
                                <div class="resource-badge"><?= $cpuCountVal !== null ? $cpuCountVal : 'N/A' ?> vCPUs</div>
                            </div>
                            <div class="report-resource-value"><?= $cpuUtil !== null ? round($cpuUtil, 1) . '%' : 'N/A' ?></div>
                            <div class="report-resource-chart">
                                <canvas id="cpu-chart-<?= $idx ?>"></canvas>
                            </div>
                        </div>

                        <!-- Mem Card -->
                        <div class="report-resource-card">
                            <div class="report-resource-header">
                                <div class="report-resource-title">
                                    <svg style="width:18px;height:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M6 19v2M10 19v2M14 19v2M18 19v2M8 11V9a4 4 0 1 1 8 0v2M5 15h14a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z" />
                                    </svg>
                                    Memória
                                </div>
                                <div class="resource-badge"><?= format_bytes($memTotal) ?></div>
                            </div>
                            <div class="report-resource-value"><?= $memPUsed !== null ? round($memPUsed, 1) . '%' : 'N/A' ?></div>
                            <div class="resource-subtext">Livre: <?= format_bytes($memAvailable ?? $memFree) ?></div>
                            <div class="report-resource-chart">
                                <canvas id="mem-chart-<?= $idx ?>"></canvas>
                            </div>
                        </div>

                        <!-- Net Card -->
                        <div class="report-resource-card">
                            <div class="report-resource-header">
                                <div class="report-resource-title">
                                    <svg style="width:18px;height:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                                    </svg>
                                    Rede
                                </div>
                            </div>
                            <div style="display: flex; gap: 20px; margin-top: 10px;">
                                <div>
                                    <div class="resource-subtext">Download</div>
                                    <div style="font-weight: 800; color: var(--primary);"><?= format_rate($netInRate) ?></div>
                                </div>
                                <div>
                                    <div class="resource-subtext">Upload</div>
                                    <div style="font-weight: 800; color: var(--primary);"><?= format_rate($netOutRate) ?></div>
                                </div>
                            </div>
                            <div class="report-resource-chart">
                                <canvas id="net-chart-<?= $idx ?>"></canvas>
                            </div>
                        </div>

                        <!-- Disks (Dynamically added to grid) -->
                        <?php 
                        $diskIdx = 0;
                        if (!empty($items['disks'])):
                            ksort($items['disks']);
                            foreach ($items['disks'] as $mount => $disk): 
                                $dTotal = $getLastVal($disk['total']);
                                $dUsed = $getLastVal($disk['used']);
                                $dFree = $getLastVal($disk['free']);
                                $dPused = $getLastVal($disk['pused']);
                                
                                if ($dUsed === null && $dTotal !== null && $dFree !== null) $dUsed = $dTotal - $dFree;
                                if ($dPused === null && $dTotal > 0 && $dUsed !== null) $dPused = ($dUsed / $dTotal) * 100;
                                
                                if ($dTotal === null && $dUsed === null && $dPused === null) continue;
                        ?>
                            <div class="report-resource-card">
                                <div class="report-resource-header">
                                    <div class="report-resource-title" title="<?= h($mount) ?>">
                                        <svg style="width:18px;height:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <ellipse cx="12" cy="5" rx="9" ry="3" />
                                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                                        </svg>
                                        Disco <?= h($mount) ?>
                                    </div>
                                    <div class="resource-badge"><?= format_bytes($dTotal) ?></div>
                                </div>
                                <div class="report-resource-value"><?= $dPused !== null ? round($dPused, 1) . '%' : 'N/A' ?></div>
                                <div class="resource-subtext">Livre: <?= format_bytes($dFree ?? ($dTotal !== null && $dUsed !== null ? ($dTotal - $dUsed) : null)) ?></div>
                                <div class="report-resource-chart">
                                    <canvas id="disk-chart-<?= $idx ?>-<?= $diskIdx ?>"></canvas>
                                </div>
                            </div>
                        <?php 
                                $diskIdx++;
                            endforeach; 
                        endif;
                        ?>
                    </div>

                    <script>
                    (function() {
                        const commonOptions = {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { display: false },
                                y: { display: false, beginAtZero: true }
                            },
                            elements: {
                                point: { radius: 0 },
                                line: { tension: 0.4, borderWidth: 2 }
                            }
                        };

                        function initChart(id, labels, data, color) {
                            const ctx = document.getElementById(id).getContext('2d');
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: data,
                                        borderColor: color,
                                        backgroundColor: color + '20',
                                        fill: true
                                    }]
                                },
                                options: commonOptions
                            });
                        }

                        const labels = [];
                        const cpuData = [];
                        const memData = [];
                        const diskData = [];
                        const netInData = [];
                        const netOutData = [];

                        <?php 
                            // Prepare data points from trends
                            $allClocks = [];
                            // Get all clocks from any of the relevant items
                            $chartItems = ['cpu', 'cpu_idle', 'mem_used', 'mem_pused', 'net_in', 'net_out'];
                            foreach ($chartItems as $key) {
                                if (isset($items[$key]) && isset($groupedTrends[$items[$key]['itemid']])) {
                                    foreach ($groupedTrends[$items[$key]['itemid']] as $t) $allClocks[] = $t['clock'];
                                }
                            }
                            // Add disk clocks
                            if (!empty($items['disks'])) {
                                foreach ($items['disks'] as $disk) {
                                    foreach (['total', 'used', 'free', 'pused'] as $dk) {
                                        if (isset($disk[$dk]) && $disk[$dk] && isset($groupedTrends[$disk[$dk]['itemid']])) {
                                            foreach ($groupedTrends[$disk[$dk]['itemid']] as $t) $allClocks[] = $t['clock'];
                                        }
                                    }
                                }
                            }
                            sort($allClocks);
                            $allClocks = array_unique($allClocks);
                        ?>

                        <?php foreach ($allClocks as $clock): ?>
                            labels.push('<?= date('d/m H:i', $clock) ?>');
                            
                            <?php 
                                $val = null;
                                if ($items['cpu'] && isset($groupedTrends[$items['cpu']['itemid']])) {
                                    foreach ($groupedTrends[$items['cpu']['itemid']] as $t) if ($t['clock'] == $clock) $val = $t['value_avg'];
                                } elseif ($items['cpu_idle'] && isset($groupedTrends[$items['cpu_idle']['itemid']])) {
                                    foreach ($groupedTrends[$items['cpu_idle']['itemid']] as $t) if ($t['clock'] == $clock) $val = 100 - $t['value_avg'];
                                }
                            ?>
                            cpuData.push(<?= $val !== null ? round($val, 2) : 'null' ?>);

                            <?php 
                                $val = null;
                                if ($items['mem_used'] && isset($groupedTrends[$items['mem_used']['itemid']])) {
                                    foreach ($groupedTrends[$items['mem_used']['itemid']] as $t) if ($t['clock'] == $clock) $val = $t['value_avg'];
                                } elseif ($items['mem_pused'] && $memTotal && isset($groupedTrends[$items['mem_pused']['itemid']])) {
                                    foreach ($groupedTrends[$items['mem_pused']['itemid']] as $t) if ($t['clock'] == $clock) $val = ($t['value_avg'] * $memTotal) / 100;
                                }
                            ?>
                            memData.push(<?= $val !== null ? round($val, 2) : 'null' ?>);

                            <?php 
                                $valIn = 0;
                                if ($items['net_in'] && isset($groupedTrends[$items['net_in']['itemid']])) {
                                    foreach ($groupedTrends[$items['net_in']['itemid']] as $t) if ($t['clock'] == $clock) $valIn = $t['value_avg'];
                                }
                                $valOut = 0;
                                if ($items['net_out'] && isset($groupedTrends[$items['net_out']['itemid']])) {
                                    foreach ($groupedTrends[$items['net_out']['itemid']] as $t) if ($t['clock'] == $clock) $valOut = $t['value_avg'];
                                }
                            ?>
                            netInData.push(<?= round($valIn, 2) ?>);
                            netOutData.push(<?= round($valOut, 2) ?>);
                        <?php endforeach; ?>

                        initChart('cpu-chart-<?= $idx ?>', labels, cpuData, getThemeColor('--primary') || '#27c4a8');
                        initChart('mem-chart-<?= $idx ?>', labels, memData, getThemeColor('--primary') || '#27c4a8');
                        initChart('net-chart-<?= $idx ?>', labels, netInData, getThemeColor('--primary') || '#27c4a8');

                        <?php 
                        $diskIdx = 0;
                        foreach ($items['disks'] as $mount => $disk): 
                            if (!$disk['total'] && !$disk['used'] && !$disk['pused']) continue;
                        ?>
                        const diskData_<?= $diskIdx ?> = [];
                        <?php foreach ($allClocks as $clock): ?>
                            <?php 
                                $val = null;
                                $dTotal = $getLastVal($disk['total']);
                                if ($disk['used'] && isset($groupedTrends[$disk['used']['itemid']])) {
                                    foreach ($groupedTrends[$disk['used']['itemid']] as $t) if ($t['clock'] == $clock) $val = $t['value_avg'];
                                } elseif ($disk['pused'] && $dTotal && isset($groupedTrends[$disk['pused']['itemid']])) {
                                    foreach ($groupedTrends[$disk['pused']['itemid']] as $t) if ($t['clock'] == $clock) $val = ($t['value_avg'] * $dTotal) / 100;
                                }
                            ?>
                            diskData_<?= $diskIdx ?>.push(<?= $val !== null ? round($val, 2) : 'null' ?>);
                        <?php endforeach; ?>
                        initChart('disk-chart-<?= $idx ?>-<?= $diskIdx ?>', labels, diskData_<?= $diskIdx ?>, getThemeColor('--primary') || '#27c4a8');
                        <?php 
                            $diskIdx++;
                        endforeach; 
                        ?>

                        // Net chart is special (two datasets)
                        const netCtx = document.getElementById('net-chart-<?= $idx ?>').getContext('2d');
                        new Chart(netCtx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Download',
                                        data: netInData,
                                        borderColor: getThemeColor('--primary') || '#27c4a8',
                                        backgroundColor: (getThemeColor('--primary') || '#27c4a8') + '20',
                                        fill: true
                                    },
                                    {
                                        label: 'Upload',
                                        data: netOutData,
                                        borderColor: '#ff5a5f',
                                        backgroundColor: '#ff5a5f20',
                                        fill: true
                                    }
                                ]
                            },
                            options: commonOptions
                        });
                    })();
                    </script>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:20px; text-align:right">
            <button onclick="window.print()" class="btn primary">Imprimir / Salvar PDF</button>
        </div>
    <?php elseif ($generate): ?>
            <div class="muted" style="text-align:center; padding:40px">Nenhum dado encontrado para os critérios selecionados.</div>
        <?php else: ?>
            <div class="report-content-card" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:40px; text-align:center">
                <div style="margin-bottom:20px">
                    <h3 style="margin:0">Relatório Técnico</h3>
                    <div class="muted">Dados de servidores, ativos, chamados e infraestrutura.</div>
                </div>
                <div class="muted">Clique em "Gerar Relatório" para visualizar os dados técnicos</div>
            </div>
        <?php endif; ?>
    <?php elseif ($tab === 'sla'): ?>
        <?php if ($generate && !empty($ticketData)): ?>
            <div class="dash-card" style="margin-top:20px; padding:20px">
                <h3>Métricas de SLA e Chamados</h3>
                <div class="muted">Desempenho de atendimento no período de <?= $period ?> dias.</div>

                <div class="kpi-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-top:20px">
                    <?php
                        $totalSli = 0; $countSli = 0;
                        $totalSlo = 0;
                        $withinSla = 0;
                        foreach ($ticketData as $tk) {
                            $totalSli += $tk['sli_seconds'];
                            $totalSlo += $tk['slo_seconds'];
                            if ($tk['sli_seconds'] <= $tk['slo_seconds']) $withinSla++;
                            $countSli++;
                        }
                        $avgSli = $countSli > 0 ? $totalSli / $countSli : 0;
                        $slaPercent = $countSli > 0 ? ($withinSla / $countSli) * 100 : 0;
                    ?>
                    <div class="dash-card" style="padding:15px; text-align:center">
                        <div class="muted" style="font-size:0.8em">SLA de Atendimento</div>
                        <div style="font-size:2em; font-weight:800; color:<?= $slaPercent >= 95 ? '#2ecc71' : ($slaPercent >= 80 ? '#f1c40f' : '#e74c3c') ?>">
                            <?= round($slaPercent, 1) ?>%
                        </div>
                    </div>
                    <div class="dash-card" style="padding:15px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Tempo Médio de Resposta (SLI)</div>
                        <div style="font-size:2em; font-weight:800; color:var(--primary)">
                            <?php
                                $totalSecs = (int)$avgSli;
                                $h = (int)floor($totalSecs / 3600);
                                $m = (int)floor(($totalSecs % 3600) / 60);
                                echo $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                            ?>
                        </div>
                    </div>
                    <div class="dash-card" style="padding:15px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Total de Chamados</div>
                        <div style="font-size:2em; font-weight:800; color:var(--primary)"><?= $countSli ?></div>
                    </div>
                </div>

                <table class="table" style="width:100%; margin-top:30px; border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border); text-align:left">
                            <th style="padding:10px">#</th>
                            <th style="padding:10px">Assunto</th>
                            <th style="padding:10px">Cliente</th>
                            <th style="padding:10px">SLA Alvo (SLO)</th>
                            <th style="padding:10px">Tempo Real (SLI)</th>
                            <th style="padding:10px">Status SLA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketData as $tk): ?>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:10px"><?= h((string)($tk['id'] ?? '')) ?></td>
                                <td style="padding:10px"><?= h((string)($tk['subject'] ?? 'Sem Assunto')) ?></td>
                                <td style="padding:10px"><?= h((string)($tk['client_name'] ?? 'N/A')) ?></td>
                                <td style="padding:10px">
                                    <?php
                                        $slo = (int)($tk['slo_seconds'] ?? 0);
                                        $h_slo = (int)floor($slo / 3600);
                                        $m_slo = (int)floor(($slo % 3600) / 60);
                                        echo $h_slo > 0 ? "{$h_slo}h {$m_slo}m" : "{$m_slo}m";
                                    ?>
                                </td>
                                <td style="padding:10px">
                                    <?php
                                        $sli = (int)($tk['sli_seconds'] ?? 0);
                                        $h_sli = (int)floor($sli / 3600);
                                        $m_sli = (int)floor(($sli % 3600) / 60);
                                        echo $h_sli > 0 ? "{$h_sli}h {$m_sli}m" : "{$m_sli}m";
                                    ?>
                                </td>
                                <td style="padding:10px">
                                    <?php if ($tk['sli_seconds'] <= $tk['slo_seconds']): ?>
                                        <span style="color:#2ecc71">● Dentro</span>
                                    <?php else: ?>
                                        <span style="color:#e74c3c">● Fora</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:20px; text-align:right">
                <button onclick="window.print()" class="btn primary">Imprimir / Salvar PDF</button>
            </div>
        <?php elseif ($generate): ?>
            <div class="muted" style="text-align:center; padding:40px">Nenhum chamado encontrado no período.</div>
        <?php else: ?>
            <div class="report-content-card" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:40px; text-align:center">
                <h3>Relatório de SLA</h3>
                <div class="muted">Selecione uma empresa e clique em "Gerar Relatório"</div>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'dashboard'): ?>
        <?php if ($generate && !empty($reportData)): ?>
            <div class="dashboard-executivo" style="background:var(--bg); border:1px solid var(--border); padding:30px; border-radius:12px; margin-top:20px; color:var(--text); font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px">
                    <h2 style="margin:0; color:var(--text); font-weight:300">Dashboard Executivo <span style="font-size:0.5em; vertical-align:middle; color:var(--primary)">LIVE</span></h2>
                    <div style="font-size:0.8em; color:var(--muted)">Período: <?= $period ?> dias · <?= date('d/m/Y') ?></div>
                </div>

                <!-- KPI Sparklines Row -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom:30px">
                    <?php
                        $kpis = [
                            ['title' => 'Novos Chamados', 'val' => array_sum(array_column($dashboardData['tickets_trend'] ?? [], 'total')), 'color' => '#4caf50', 'data' => array_column($dashboardData['tickets_trend'] ?? [], 'total')],
                            ['title' => 'SLA Global', 'val' => round($slaPercent, 1) . '%', 'color' => '#2196f3', 'data' => $dashboardData['sla_trend'] ?? []],
                            ['title' => 'Alertas Zabbix', 'val' => $dashboardData['zabbix_alerts_count'] ?? 0, 'color' => '#f44336', 'data' => $dashboardData['zabbix_alerts_trend'] ?? []]
                        ];
                        foreach ($kpis as $i => $kpi):
                    ?>
                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px; border-top:3px solid <?= $kpi['color'] ?>">
                        <div style="font-size:0.9em; color:var(--muted); margin-bottom:5px"><?= h($kpi['title']) ?></div>
                        <div style="font-size:2em; font-weight:700; color:var(--text); margin-bottom:10px"><?= $kpi['val'] ?></div>
                        <div style="height:40px">
                            <canvas id="sparkline-<?= $i ?>"></canvas>
                        </div>
                    </div>
                    <script>
                    (function() {
                        new Chart(document.getElementById('sparkline-<?= $i ?>'), {
                            type: 'line',
                            data: {
                                labels: <?= json_encode(array_fill(0, count($kpi['data']), '')) ?>,
                                datasets: [{
                                    data: <?= json_encode($kpi['data']) ?>,
                                    borderColor: '<?= $kpi['color'] ?>',
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: true,
                                    backgroundColor: '<?= $kpi['color'] ?>22',
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                                scales: { x: { display: false }, y: { display: false } }
                            }
                        });
                    })();
                    </script>
                    <?php endforeach; ?>
                </div>

                <!-- Active Alerts Section -->
                <?php
                $activeAlerts = $dashboardData['zabbix_active_alerts'] ?? [];
                if (!empty($activeAlerts)):
                ?>
                <div style="margin-bottom:30px">
                    <h4 style="margin:0 0 15px 0; color:var(--text); display:flex; align-items:center">
                        <span style="color:#f44336; margin-right:10px">●</span> Alertas Ativos no Momento
                    </h4>
                    <div style="display:flex; flex-direction:column; gap:10px">
                        <?php foreach ($activeAlerts as $alert): 
                            $severityColor = [0 => '#97aab3', 1 => '#7499ff', 2 => '#ffc859', 3 => '#ffa059', 4 => '#e97659', 5 => '#f44336'][$alert['priority']] ?? '#97aab3';
                        ?>
                        <div style="background:var(--panel); border:1px solid var(--border); padding:12px 20px; border-radius:8px; border-left:4px solid <?= $severityColor ?>; display:flex; align-items:center; gap:20px">
                            <div style="flex: 0 0 80px; font-size:0.7em; font-weight:700; color:<?= $severityColor ?>; text-transform:uppercase">
                                <?= ['Informativo', 'Atenção', 'Média', 'Alta', 'Desastre', 'Crítico'][$alert['priority']] ?? 'Desconhecido' ?>
                            </div>
                            <div style="flex: 1; font-size:0.9em; color:var(--text); font-weight:500;"><?= h($alert['description']) ?></div>
                            <div style="flex: 0 0 300px; font-size:0.8em; color:var(--muted); word-break:break-word;" title="<?= h($alert['hosts'][0]['name'] ?? 'N/A') ?>">
                                <span style="font-size:0.8em; opacity:0.7">Host:</span> <?= h($alert['hosts'][0]['name'] ?? 'N/A') ?>
                            </div>
                            <div style="flex: 0 0 100px; font-size:0.75em; color:var(--muted); text-align:right">
                                <?= date('d/m H:i', $alert['lastchange']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Main Charts Grid -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px">
                    <!-- Resource Consumption Area Chart -->
                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px; grid-column: span 2">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Consumo de Recursos (CPU vs Memória)</h4>
                        <div style="height:300px">
                            <canvas id="resourceChartDashboard"></canvas>
                        </div>
                    </div>

                    <!-- Ticket Volume Stacked Chart -->
                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Volume de Chamados</h4>
                        <div style="height:300px">
                            <canvas id="ticketChartDashboard"></canvas>
                        </div>
                    </div>

                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Distribuição por Categoria</h4>
                        <div style="height:300px">
                            <canvas id="categoryChartDashboard"></canvas>
                        </div>
                    </div>

                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Tempos Médios (Horas)</h4>
                        <div style="height:300px">
                            <canvas id="timeChartDashboard"></canvas>
                        </div>
                    </div>

                    <!-- Availability Bar Chart -->
                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Tempos Médios por Categoria (Horas)</h4>
                        <div style="height:300px">
                            <canvas id="categoryTimeChartDashboard"></canvas>
                        </div>
                    </div>

                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Conformidade SLA por Prioridade</h4>
                        <div style="height:300px">
                            <canvas id="prioritySlaChartDashboard"></canvas>
                        </div>
                    </div>

                    <div style="background:var(--panel); border:1px solid var(--border); padding:20px; border-radius:8px">
                    <h4 style="margin:0 0 20px 0; color:var(--text)">Disponibilidade por Ativo (Uptime %)</h4>
                        <div style="height:300px">
                            <canvas id="availabilityChartDashboard"></canvas>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    <?php
                        $dayMap = [];
                        foreach ($reportData as $data) {
                            $items = $data['items'];
                            $trends = $data['trends'];
                            $hostGrouped = [];
                            foreach ($trends as $t) $hostGrouped[$t['itemid']][] = $t;

                            foreach ($trends as $t) {
                                $d = date('d/m', $t['clock']);
                                if ($t['itemid'] == ($items['cpu']['itemid'] ?? 0)) {
                                    $dayMap[$d]['cpu'][] = $t['value_avg'];
                                }
                                
                                $memPusedId = $items['mem_pused']['itemid'] ?? 0;
                                if ($t['itemid'] == $memPusedId) {
                                    $dayMap[$d]['mem'][] = $t['value_avg'];
                                } elseif ($items['mem_used'] && $items['mem_total'] && $t['itemid'] == $items['mem_used']['itemid']) {
                                    $totalItemid = $items['mem_total']['itemid'];
                                    $totalVal = 0;
                                    if (isset($hostGrouped[$totalItemid])) {
                                        foreach ($hostGrouped[$totalItemid] as $tt) {
                                            if ($tt['clock'] == $t['clock']) {
                                                $totalVal = $tt['value_avg'];
                                                break;
                                            }
                                        }
                                    }
                                    if ($totalVal > 0) {
                                        $dayMap[$d]['mem'][] = ($t['value_avg'] / $totalVal) * 100;
                                    }
                                }
                            }
                        }
                        $chartCpu = []; $chartMem = [];
                        $sortedDays = array_keys($dayMap);
                        sort($sortedDays);
                        foreach ($sortedDays as $d) {
                            $vals = $dayMap[$d];
                            $chartCpu[] = count($vals['cpu'] ?? []) > 0 ? array_sum($vals['cpu']) / count($vals['cpu']) : 0;
                            $chartMem[] = count($vals['mem'] ?? []) > 0 ? array_sum($vals['mem']) / count($vals['mem']) : 0;
                        }

                        // Category distribution
                        $catDist = [];
                        $catTime = [];
                        $prioSla = [];
                        foreach ($ticketData as $tk) {
                            $cat = $tk['category_name'] ?? 'Outros';
                            $catDist[$cat] = ($catDist[$cat] ?? 0) + 1;
                            
                            $sli = (float)($tk['sli_seconds'] ?? 0);
                            $catTime[$cat][] = $sli;
                            
                            $prio = $tk['priority_name'] ?? 'Normal';
                            if (!isset($prioSla[$prio])) $prioSla[$prio] = ['total' => 0, 'met' => 0];
                            $prioSla[$prio]['total']++;
                            if ($tk['is_within_sla'] ?? false) $prioSla[$prio]['met']++;
                        }

                        $catAvgTime = [];
                        foreach ($catTime as $cat => $times) {
                            $catAvgTime[$cat] = round(array_sum($times) / count($times) / 3600, 1);
                        }

                        // Avg times
                        $totalResp = 0; $totalReso = 0; $countResp = 0; $countReso = 0;
                        foreach ($ticketData as $tk) {
                            if ($tk['first_response_at']) {
                                $totalResp += (strtotime($tk['first_response_at']) - strtotime($tk['created_at'])) / 3600;
                                $countResp++;
                            }
                            if ($tk['closed_at']) {
                                $totalReso += (strtotime($tk['closed_at']) - strtotime($tk['created_at'])) / 3600;
                                $countReso++;
                            }
                        }
                        $avgResp = $countResp > 0 ? round($totalResp / $countResp, 1) : 0;
                        $avgReso = $countReso > 0 ? round($totalReso / $countReso, 1) : 0;

                        // Real Availability Data
                        $availabilityLabels = [];
                        $availabilityData = [];
                        $availCount = 0;
                        foreach ($reportData as $data) {
                            if ($availCount >= 10) break; // Limit to 10 hosts for chart readability
                            $h = $data['host'];
                            $items = $data['items'];
                            $trends = $data['trends'];
                            
                            $uptime = 100;
                            if ($items['availability']) {
                                $iid = $items['availability']['itemid'];
                                $hTrends = array_filter($trends, function($t) use ($iid) { return $t['itemid'] == $iid; });
                                if (!empty($hTrends)) {
                                    $sum = 0; $count = 0;
                                    foreach ($hTrends as $t) { $sum += (float)$t['value_avg']; $count++; }
                                    $uptime = ($sum / $count) * 100;
                                } else {
                                    // Fallback to lastvalue if no trends
                                    $uptime = (float)($items['availability']['lastvalue'] ?? 1) * 100;
                                }
                            }
                            $availabilityLabels[] = $h['name'];
                            $availabilityData[] = round($uptime, 2);
                            $availCount++;
                        }
                        if (empty($availabilityLabels)) {
                            $availabilityLabels = ['Nenhum Ativo'];
                            $availabilityData = [0];
                        }
                    ?>

                    // Resource Chart
                    new Chart(document.getElementById('resourceChartDashboard'), {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($sortedDays) ?>,
                            datasets: [
                                {
                                    label: 'Média CPU %',
                                    data: <?= json_encode($chartCpu) ?>,
                                    borderColor: '#00d2ff',
                                    backgroundColor: 'rgba(0, 210, 255, 0.1)',
                                    fill: true, tension: 0.4
                                },
                                {
                                    label: 'Média Memória %',
                                    data: <?= json_encode($chartMem) ?>,
                                    borderColor: '#9d50bb',
                                    backgroundColor: 'rgba(157, 80, 187, 0.1)',
                                    fill: true, tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            scales: {
                                y: { grid: { color: getThemeColor('--border') }, ticks: { color: getThemeColor('--muted') }, min: 0, max: 100 },
                                x: { grid: { display: false }, ticks: { color: getThemeColor('--muted') } }
                            },
                            plugins: { legend: { labels: { color: getThemeColor('--text') } } }
                        }
                    });

                    new Chart(document.getElementById('categoryChartDashboard'), {
                        type: 'polarArea',
                        data: {
                            labels: <?= json_encode(array_keys($catDist)) ?>,
                            datasets: [{
                                data: <?= json_encode(array_values($catDist)) ?>,
                                backgroundColor: [
                                    'rgba(52, 152, 219, 0.6)', 'rgba(46, 204, 113, 0.6)', 
                                    'rgba(155, 89, 182, 0.6)', 'rgba(241, 194, 15, 0.6)',
                                    'rgba(231, 76, 60, 0.6)', 'rgba(230, 126, 34, 0.6)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { color: getThemeColor('--text') } } },
                            scales: { r: { grid: { color: getThemeColor('--border') }, ticks: { display: false } } }
                        }
                    });

                    new Chart(document.getElementById('timeChartDashboard'), {
                        type: 'bar',
                        data: {
                            labels: ['Primeira Resposta', 'Resolução Final'],
                            datasets: [{
                                label: 'Média de Horas',
                                data: [<?= $avgResp ?>, <?= $avgReso ?>],
                                backgroundColor: ['#3498db', '#2ecc71'],
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            scales: {
                                y: { grid: { color: getThemeColor('--border') }, ticks: { color: getThemeColor('--muted') } },
                                x: { grid: { display: false }, ticks: { color: getThemeColor('--muted') } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Ticket Chart
                    new Chart(document.getElementById('ticketChartDashboard'), {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode(array_map(function($d){ return date('d/m', strtotime($d['day'])); }, $dashboardData['tickets_trend'])) ?>,
                            datasets: [{
                                label: 'Chamados Abertos',
                                data: <?= json_encode(array_column($dashboardData['tickets_trend'], 'total')) ?>,
                                backgroundColor: '#4caf50',
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { grid: { color: getThemeColor('--border') }, ticks: { color: getThemeColor('--muted') } },
                                x: { grid: { display: false }, ticks: { color: getThemeColor('--muted') } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Availability Chart
                    new Chart(document.getElementById('availabilityChartDashboard'), {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($availabilityLabels) ?>,
                            datasets: [{
                                label: 'Uptime %',
                                data: <?= json_encode($availabilityData) ?>,
                                backgroundColor: ['#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#1abc9c', '#e74c3c', '#34495e', '#95a5a6', '#d35400']
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { grid: { color: getThemeColor('--border') }, ticks: { color: getThemeColor('--muted') }, min: 95, max: 100 },
                                y: { grid: { display: false }, ticks: { color: getThemeColor('--muted') } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Category Time Chart
                    new Chart(document.getElementById('categoryTimeChartDashboard'), {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode(array_keys($catAvgTime)) ?>,
                            datasets: [{
                                label: 'Horas',
                                data: <?= json_encode(array_values($catAvgTime)) ?>,
                                backgroundColor: '#3498db',
                                borderRadius: 5
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true, maintainAspectRatio: false,
                            scales: {
                                x: { grid: { color: getThemeColor('--border') }, ticks: { color: getThemeColor('--muted') } },
                                y: { grid: { display: false }, ticks: { color: getThemeColor('--muted') } }
                            },
                            plugins: { legend: { display: false } }
                        }
                    });

                    // Priority SLA Chart
                    <?php
                        $pLabels = array_keys($prioSla);
                        $pDataMet = [];
                        $pDataMissed = [];
                        foreach ($pLabels as $p) {
                            $pDataMet[] = $prioSla[$p]['met'];
                            $pDataMissed[] = $prioSla[$p]['total'] - $prioSla[$p]['met'];
                        }
                    ?>
                    new Chart(document.getElementById('prioritySlaChartDashboard'), {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($pLabels) ?>,
                            datasets: [{
                                label: 'Dentro do SLA',
                                data: <?= json_encode($pDataMet) ?>,
                                backgroundColor: '#2ecc71'
                            }, {
                                label: 'Fora do SLA',
                                data: <?= json_encode($pDataMissed) ?>,
                                backgroundColor: '#e74c3c'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            scales: {
                                x: { stacked: true, grid: { display: false }, ticks: { color: getThemeColor('--muted') } },
                                y: { stacked: true, grid: { color: getThemeColor('--border') }, ticks: { color: getThemeColor('--muted') } }
                            },
                            plugins: { legend: { position: 'bottom', labels: { color: getThemeColor('--text') } } }
                        }
                    });
                });
                </script>

                <div style="margin-top:20px; text-align:right">
                    <button onclick="generatePDF()" class="btn primary">Imprimir / Salvar PDF</button>
                </div>
                <script>
                function generatePDF() {
                    const originalTitle = document.title;
                    const company = "<?= h($c_name ?? 'Relatorio') ?>";
                    const period = "<?= $period ?>_dias";
                    const date = "<?= date('d-m-Y') ?>";
                    document.title = `Relatorio_${company}_${period}_${date}`;
                    window.print();
                    document.title = originalTitle;
                }
                </script>
            </div>
        <?php elseif ($generate): ?>
            <div class="muted" style="text-align:center; padding:40px">Gere um relatório técnico primeiro para alimentar o dashboard.</div>
        <?php else: ?>
            <div class="report-content-card" style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:40px; text-align:center">
                <h3>Dashboard Executivo</h3>
                <div class="muted">Visualize indicadores estratégicos em tempo real.</div>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'billing'): ?>
        <?php
            $boletos = [];
            if ($company_id) {
                // Get all users for this company
                $stmt = $pdo->prepare("SELECT user_id FROM client_profiles WHERE company_id = ?");
                $stmt->execute([$company_id]);
                $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($uids)) {
                    $placeholders = implode(',', array_fill(0, count($uids), "?"));
                    $stmt = $pdo->prepare("SELECT * FROM boletos WHERE client_user_id IN ($placeholders) AND created_at >= ? ORDER BY created_at DESC");
                    $params = array_merge($uids, [date('Y-m-d H:i:s', $timeFrom)]);
                    $stmt->execute($params);
                    $boletos = $stmt->fetchAll();
                }
            }

            // Ensure hosts is initialized for Billing tab even if not strictly fetched before
            // We need to fetch hosts if they weren't fetched (which happens if tab is not SLA, but logic above might have skipped if no company selected initially)
            // Actually, logic above fetches hosts if $tab !== 'sla'. But if $company_id is not set, hosts might be empty.
            // If $company_id is set, hosts should be populated.
            // However, if we are in Billing tab, we definitely need hosts to calculate cost.
            if (!isset($hosts)) $hosts = [];
            
            // If hosts are empty and we have a company_id, let's try to fetch them again if they weren't fetched
            if (empty($hosts) && $company_id && isset($zbxConfig, $auth)) {
                 // Reuse logic to get hosts for this company
                 try {
                    $stmt = $pdo->prepare('SELECT zh.hostgroupid, zh.name FROM zabbix_hostgroups zh JOIN client_profiles cp ON cp.user_id = zh.client_user_id WHERE cp.company_id = ?');
                    $stmt->execute([$company_id]);
                    $hgs = $stmt->fetchAll();
                    $gIds = [];
                    foreach ($hgs as $hg) {
                        $resp = zbx_rpc($zbxConfig, 'hostgroup.get', ['output' => ['groupid'], 'filter' => ['name' => [$hg['name']]]], $auth);
                        if ($resp) foreach ($resp as $r) $gIds[] = $r['groupid'];
                    }
                    if (!empty($gIds)) {
                        $hosts = zbx_rpc($zbxConfig, 'host.get', [
                            'groupids' => array_values(array_unique($gIds)),
                            'output' => ['hostid', 'name']
                        ], $auth);
                    }
                 } catch (Exception $e) {}
            }

            $hostCount = count($hosts);
            $costPerHost = 150.00;
            $basePlatformFee = 300.00;
            $estimatedMonthly = ($hostCount * $costPerHost) + $basePlatformFee;
            $totalBilled = count($boletos) * $estimatedMonthly;
        ?>
        <div class="dash-card" style="margin-top:20px; padding:20px">
            <h3>Billing & Custos</h3>
            <div class="muted">Visão de custos e faturamento no período de <?= $period ?> dias.</div>
            
            <?php if (!empty($boletos) || !empty($hosts)): ?>
                <div class="kpi-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-top:20px">
                    <div class="dash-card" style="padding:15px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Faturado no Período</div>
                        <div style="font-size:2em; font-weight:800; color:var(--primary)">
                            R$ <?= number_format($totalBilled, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="dash-card" style="padding:15px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Custo Estimado / Mês</div>
                        <div style="font-size:2em; font-weight:800; color:var(--primary)">
                            R$ <?= number_format($estimatedMonthly, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="dash-card" style="padding:15px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Qtd. Ativos Cobráveis</div>
                        <div style="font-size:2em; font-weight:800; color:var(--primary)"><?= $hostCount ?></div>
                    </div>
                </div>

                <div style="margin-top:30px">
                    <h4>Detalhamento de Faturas (Boletos)</h4>
                    <table class="table" style="width:100%; margin-top:10px; border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border); text-align:left">
                                <th style="padding:10px">Referência</th>
                                <th style="padding:10px">Data Emissão</th>
                                <th style="padding:10px">Valor (Est.)</th>
                                <th style="padding:10px">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($boletos as $b): ?>
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:10px"><?= h($b['reference']) ?></td>
                                    <td style="padding:10px"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
                                    <td style="padding:10px">R$ <?= number_format($estimatedMonthly, 2, ',', '.') ?></td>
                                    <td style="padding:10px">
                                        <a href="cliente_boleto.php?download=<?= (int)$b['id'] ?>" class="btn small">Download PDF</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($boletos)): ?>
                                <tr><td colspan="4" style="padding:20px; text-align:center" class="muted">Nenhuma fatura encontrada no período.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:30px">
                    <h4>Composição de Custos por Host</h4>
                    <div class="muted" style="font-size:0.9em; margin-bottom:10px">Baseado em licenciamento e suporte por ativo.</div>
                    <table class="table" style="width:100%; border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:2px solid var(--border); text-align:left">
                                <th style="padding:10px">Host</th>
                                <th style="padding:10px">Serviço</th>
                                <th style="padding:10px">Valor Unitário</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hosts as $h): ?>
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:10px"><?= h($h['name']) ?></td>
                                    <td style="padding:10px">Gestão & Monitoramento 24x7</td>
                                    <td style="padding:10px">R$ <?= number_format($costPerHost, 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top:20px; text-align:right">
                        <button onclick="window.print()" class="btn primary">Imprimir / Salvar PDF</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="muted" style="text-align:center; padding:40px">Selecione uma empresa com ativos para visualizar os custos.</div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'gerencial'): ?>
        <?php if ($generate && !empty($reportData)): ?>
            <div class="dash-card" style="margin-top:20px; padding:20px">
                <h3>Resumo Executivo</h3>
                <div class="muted">Visão geral consolidada de infraestrutura, atendimento e custos no período de <?= $period ?> dias.</div>
                
                <div class="kpi-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-top:20px; margin-bottom:30px">
                    <?php
                        // Consolidated Ticket Stats
                        $withinSla = 0; $totalTickets = count($ticketData);
                        foreach ($ticketData as $tk) if ($tk['sli_seconds'] <= $tk['slo_seconds']) $withinSla++;
                        $slaPercent = $totalTickets > 0 ? ($withinSla / $totalTickets) * 100 : 100;

                        // Host count
                        $hostCount = count($reportData);
                    ?>
                    <div style="background:var(--bg); padding:15px; border-radius:8px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Ativos Monitorados</div>
                        <div style="font-size:1.8em; font-weight:700; color:var(--primary)"><?= $hostCount ?></div>
                    </div>
                    <div style="background:var(--bg); padding:15px; border-radius:8px; text-align:center">
                        <div class="muted" style="font-size:0.8em">SLA de Atendimento</div>
                        <div style="font-size:1.8em; font-weight:700; color:<?= $slaPercent >= 95 ? '#2ecc71' : '#f1c40f' ?>"><?= round($slaPercent, 1) ?>%</div>
                    </div>
                    <div style="background:var(--bg); padding:15px; border-radius:8px; text-align:center">
                        <div class="muted" style="font-size:0.8em">Chamados Abertos</div>
                        <div style="font-size:1.8em; font-weight:700; color:var(--primary)"><?= $totalTickets ?></div>
                    </div>
                </div>

                <h4>Consumo de Recursos por Host</h4>
                <table class="table" style="width:100%; margin-top:10px; border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border); text-align:left">
                            <th style="padding:10px">Host</th>
                            <th style="padding:10px">CPU (Pico)</th>
                            <th style="padding:10px">Memória (Total)</th>
                            <th style="padding:10px">Disco (Total)</th>
                            <th style="padding:10px">Total Rede</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $data): ?>
                            <?php
                                $items = $data['items'];
                                $trends = $data['trends'];
                                $grouped = [];
                                foreach ($trends as $t) $grouped[$t['itemid']][] = $t;
                                
                                $maxCpu = 0;
                                if ($items['cpu'] && isset($grouped[$items['cpu']['itemid']])) {
                                    foreach ($grouped[$items['cpu']['itemid']] as $t) { 
                                        if ($t['value_max'] > $maxCpu) $maxCpu = $t['value_max'];
                                    }
                                    $maxCpu = round($maxCpu, 1);
                                }

                                $memTotalStr = 'N/A';
                                if ($items['mem_total'] && isset($grouped[$items['mem_total']['itemid']])) {
                                    $total = end($grouped[$items['mem_total']['itemid']])['value_avg'];
                                    $used = 0;
                                    if ($items['mem_used'] && isset($grouped[$items['mem_used']['itemid']])) {
                                        $used = end($grouped[$items['mem_used']['itemid']])['value_avg'];
                                    }
                                    $memTotalStr = format_bytes($used) . ' / ' . format_bytes($total);
                                } elseif ($items['mem_used'] && isset($grouped[$items['mem_used']['itemid']])) {
                                    $used = end($grouped[$items['mem_used']['itemid']])['value_avg'];
                                    $memTotalStr = format_bytes($used);
                                }

                                $diskTotalStr = 'N/A';
                                if ($items['disk_total'] && isset($grouped[$items['disk_total']['itemid']])) {
                                    $total = end($grouped[$items['disk_total']['itemid']])['value_avg'];
                                    $used = 0;
                                    if ($items['disk_used'] && isset($grouped[$items['disk_used']['itemid']])) {
                                        $used = end($grouped[$items['disk_used']['itemid']])['value_avg'];
                                    }
                                    $diskTotalStr = format_bytes($used) . ' / ' . format_bytes($total);
                                } elseif ($items['disk_used'] && isset($grouped[$items['disk_used']['itemid']])) {
                                    $used = end($grouped[$items['disk_used']['itemid']])['value_avg'];
                                    $diskTotalStr = format_bytes($used);
                                }

                                $totalNet = 0;
                                if ($items['net_in'] && isset($grouped[$items['net_in']['itemid']])) {
                                    foreach ($grouped[$items['net_in']['itemid']] as $t) { $totalNet += $t['value_avg'] * 3600; }
                                }
                                if ($items['net_out'] && isset($grouped[$items['net_out']['itemid']])) {
                                    foreach ($grouped[$items['net_out']['itemid']] as $t) { $totalNet += $t['value_avg'] * 3600; }
                                }
                                $netStr = $totalNet > 0 ? format_bytes($totalNet) : 'N/A';
                            ?>
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:10px"><?= h($data['host']['name']) ?></td>
                                <td style="padding:10px"><?= $maxCpu ?>%</td>
                                <td style="padding:10px"><?= $memTotalStr ?></td>
                                <td style="padding:10px"><?= $diskTotalStr ?></td>
                                <td style="padding:10px"><?= $netStr ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:20px; text-align:right">
                <button onclick="window.print()" class="btn primary">Imprimir / Salvar PDF</button>
            </div>
        <?php else: ?>
            <div class="muted" style="text-align:center; padding:40px">Gere um relatório técnico primeiro para ver o resumo gerencial.</div>
        <?php endif; ?>
    <?php elseif ($tab === 'kpi'): ?>
        <?php if ($generate && !empty($reportData)): ?>
            <?php
                // Aggregating data for KPI charts
                $catDist = [];
                $catTime = [];
                $prioSla = [];
                
                foreach ($ticketData as $tk) {
                    $cat = $tk['category_name'] ?? 'Outros';
                    $catDist[$cat] = ($catDist[$cat] ?? 0) + 1;
                    
                    $sli = (float)($tk['sli_seconds'] ?? 0);
                    $catTime[$cat][] = $sli;
                    
                    $prio = $tk['priority_name'] ?? 'Normal';
                    if (!isset($prioSla[$prio])) $prioSla[$prio] = ['total' => 0, 'met' => 0];
                    $prioSla[$prio]['total']++;
                    if ($tk['is_within_sla'] ?? false) $prioSla[$prio]['met']++;
                }
                
                $catAvgTime = [];
                foreach ($catTime as $cat => $times) {
                    $catAvgTime[$cat] = round(array_sum($times) / count($times) / 3600, 1); // in hours
                }
            ?>
            <div class="kpi-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-top:20px">
                <?php
                    $totalHosts = count($reportData);
                    $totalCpu = 0; $cpuCount = 0;
                    $totalMem = 0; $totalMemCap = 0;
                    $totalDisk = 0; $totalDiskCap = 0;
                    
                    foreach ($reportData as $data) {
                        $items = $data['items'];
                        $trends = $data['trends'];
                        $grouped = [];
                        foreach ($trends as $t) $grouped[$t['itemid']][] = $t;
                        
                        // CPU
                        if ($items['cpu'] && isset($grouped[$items['cpu']['itemid']])) {
                            $avg = 0; $c = 0;
                            foreach ($grouped[$items['cpu']['itemid']] as $t) { $avg += $t['value_avg']; $c++; }
                            if ($c > 0) { $totalCpu += ($avg / $c); $cpuCount++; }
                        }
                        
                        // Mem
                        if ($items['mem_used'] && isset($grouped[$items['mem_used']['itemid']])) {
                            $avg = 0; $c = 0;
                            foreach ($grouped[$items['mem_used']['itemid']] as $t) { $avg += $t['value_avg']; $c++; }
                            if ($c > 0) $totalMem += ($avg / $c);
                        }
                        if ($items['mem_total'] && isset($grouped[$items['mem_total']['itemid']])) {
                            $last = end($grouped[$items['mem_total']['itemid']]);
                            $totalMemCap += $last['value_avg'];
                        }

                        // Disk
                        if ($items['disk_used'] && isset($grouped[$items['disk_used']['itemid']])) {
                            $last = end($grouped[$items['disk_used']['itemid']]);
                            $totalDisk += $last['value_avg'];
                        }
                        if ($items['disk_total'] && isset($grouped[$items['disk_total']['itemid']])) {
                            $last = end($grouped[$items['disk_total']['itemid']]);
                            $totalDiskCap += $last['value_avg'];
                        }
                    }
                ?>
                <div class="dash-card" style="padding:20px; text-align:center">
                    <div class="muted">Total de Ativos</div>
                    <div style="font-size:2.5em; font-weight:800; color:var(--primary)"><?= $totalHosts ?></div>
                </div>
                <div class="dash-card" style="padding:20px; text-align:center">
                    <div class="muted">Média CPU Global</div>
                    <div style="font-size:2.5em; font-weight:800; color:var(--primary)"><?= $cpuCount > 0 ? round($totalCpu / $cpuCount, 1) : 0 ?>%</div>
                </div>
                <div class="dash-card" style="padding:20px; text-align:center">
                    <div class="muted">Uso Memória Global</div>
                    <div style="font-size:2.5em; font-weight:800; color:var(--primary)"><?= $totalMemCap > 0 ? round(($totalMem / $totalMemCap) * 100, 1) : 0 ?>%</div>
                    <div class="muted" style="font-size:0.8em"><?= format_bytes($totalMem) ?> / <?= format_bytes($totalMemCap) ?></div>
                </div>
                <div class="dash-card" style="padding:20px; text-align:center">
                    <div class="muted">Armazenamento Total</div>
                    <div style="font-size:2.5em; font-weight:800; color:var(--primary)"><?= $totalDiskCap > 0 ? round(($totalDisk / $totalDiskCap) * 100, 1) : 0 ?>%</div>
                    <div class="muted" style="font-size:0.8em"><?= format_bytes($totalDisk) ?> / <?= format_bytes($totalDiskCap) ?></div>
                </div>
            </div>

            <div class="dash-card" style="margin-top:20px; padding:20px">
                <h3>Disponibilidade e Saúde</h3>
                <div class="muted">Indicadores gerais de performance do ambiente.</div>
                <div style="margin-top:20px; height:10px; background:#eee; border-radius:5px; overflow:hidden">
                    <div style="width:99.9%; height:100%; background:var(--success)"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:5px; font-size:0.8em">
                    <span>SLA do Período</span>
                    <span style="font-weight:700; color:var(--success)">99.9%</span>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px; margin-top:20px">
                <div class="dash-card" style="padding:20px">
                    <h4>Distribuição de Chamados (SLA)</h4>
                    <div style="height:250px">
                        <canvas id="kpiTicketChart"></canvas>
                    </div>
                </div>
                <div class="dash-card" style="padding:20px">
                    <h4>Tendência de Recursos (Global)</h4>
                    <div style="height:250px">
                        <canvas id="kpiResourceChart"></canvas>
                    </div>
                </div>
                <div class="dash-card" style="padding:20px">
                    <h4>Volume por Categoria</h4>
                    <div style="height:250px">
                        <canvas id="kpiCategoryVolumeChart"></canvas>
                    </div>
                </div>
                <div class="dash-card" style="padding:20px">
                    <h4>Tempo Médio por Categoria (Horas)</h4>
                    <div style="height:250px">
                        <canvas id="kpiCategoryTimeChart"></canvas>
                    </div>
                </div>
                <div class="dash-card" style="padding:20px; grid-column: span 2">
                    <h4>Conformidade SLA por Prioridade</h4>
                    <div style="height:250px">
                        <canvas id="kpiPrioritySlaChart"></canvas>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Ticket Distribution Chart
                new Chart(document.getElementById('kpiTicketChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Dentro do SLA', 'Fora do SLA'],
                        datasets: [{
                            data: [<?= $slaMet ?>, <?= max(0, $slaTotal - $slaMet) ?>],
                            backgroundColor: ['#2ecc71', '#e74c3c'],
                            borderWidth: 2,
                            borderColor: 'var(--panel)'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });

                // Resource Chart (Global Trend)
                new Chart(document.getElementById('kpiResourceChart'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_keys($dayMap)) ?>,
                        datasets: [{
                            label: 'CPU %',
                            data: <?= json_encode($chartCpu) ?>,
                            borderColor: '#00d2ff',
                            backgroundColor: 'rgba(0, 210, 255, 0.1)',
                            fill: true, tension: 0.4
                        }, {
                            label: 'Memória %',
                            data: <?= json_encode($chartMem) ?>,
                            borderColor: '#9d50bb',
                            backgroundColor: 'rgba(157, 80, 187, 0.1)',
                            fill: true, tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            y: { min: 0, max: 100, ticks: { callback: v => v + '%' } }
                        }
                    }
                });

                // Category Volume Chart
                new Chart(document.getElementById('kpiCategoryVolumeChart'), {
                    type: 'polarArea',
                    data: {
                        labels: <?= json_encode(array_keys($catDist)) ?>,
                        datasets: [{
                            data: <?= json_encode(array_values($catDist)) ?>,
                            backgroundColor: [
                                'rgba(52, 152, 219, 0.7)', 'rgba(46, 204, 113, 0.7)', 
                                'rgba(155, 89, 182, 0.7)', 'rgba(241, 194, 15, 0.7)',
                                'rgba(231, 76, 60, 0.7)', 'rgba(230, 126, 34, 0.7)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'right' } }
                    }
                });

                // Category Time Chart
                new Chart(document.getElementById('kpiCategoryTimeChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_keys($catAvgTime)) ?>,
                        datasets: [{
                            label: 'Horas',
                            data: <?= json_encode(array_values($catAvgTime)) ?>,
                            backgroundColor: '#3498db'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });

                // Priority SLA Chart
                <?php
                    $pLabels = array_keys($prioSla);
                    $pDataMet = [];
                    $pDataMissed = [];
                    foreach ($pLabels as $p) {
                        $pDataMet[] = $prioSla[$p]['met'];
                        $pDataMissed[] = $prioSla[$p]['total'] - $prioSla[$p]['met'];
                    }
                ?>
                new Chart(document.getElementById('kpiPrioritySlaChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($pLabels) ?>,
                        datasets: [{
                            label: 'Dentro do SLA',
                            data: <?= json_encode($pDataMet) ?>,
                            backgroundColor: '#2ecc71'
                        }, {
                            label: 'Fora do SLA',
                            data: <?= json_encode($pDataMissed) ?>,
                            backgroundColor: '#e74c3c'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { x: { stacked: true }, y: { stacked: true } }
                    }
                });
            });
            </script>

            <div style="margin-top:20px; text-align:right">
                <button onclick="window.print()" class="btn primary">Imprimir / Salvar PDF</button>
            </div>
        <?php else: ?>
            <div class="muted" style="text-align:center; padding:40px">Gere um relatório técnico primeiro para ver os KPIs consolidados.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.tab-btn:hover { background: var(--panel); }
.tab-btn.active:hover { background: var(--primary); }

@media print {
    @page { 
        size: A4 landscape; 
        margin: 15mm 10mm 15mm 10mm;
    }
    
    html, body {
        background: #fff !important;
        color: #000 !important;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .btn, .filters-form, .tabs-container, header, footer, .sidebar, .nav-container, .no-print { 
        display: none !important; 
    }

    .card, .dash-card, .report-content-card, .dashboard-executivo, .report-resource-card {
        background: #fff !important;
        border: 1px solid #eee !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        margin-bottom: 30px !important;
        padding: 0 !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        width: 100% !important;
    }

    h1, h2, h3, h4 {
        color: #008b74 !important;
        margin-top: 0 !important;
        margin-bottom: 15px !important;
        font-weight: bold !important;
        page-break-after: avoid !important;
    }

    table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 20px !important;
        page-break-inside: auto !important;
    }
    
    tr {
        page-break-inside: avoid !important;
        page-break-after: auto !important;
    }

    th {
        background-color: #f8f9fa !important;
        color: #333 !important;
        border: 1px solid #dee2e6 !important;
        padding: 10px !important;
        text-align: left !important;
        font-size: 11px !important;
        -webkit-print-color-adjust: exact !important;
    }

    td {
        border: 1px solid #dee2e6 !important;
        padding: 8px !important;
        font-size: 10px !important;
        vertical-align: middle !important;
    }

    .chart-container, canvas {
        max-width: 100% !important;
        height: auto !important;
        margin: 0 auto !important;
        display: block !important;
        page-break-inside: avoid !important;
    }

    #report-header-print {
        display: block !important;
        border-bottom: 2px solid #008b74 !important;
        margin-bottom: 40px !important;
        padding-bottom: 20px !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        text-shadow: none !important;
        box-shadow: none !important;
    }

    .host-report {
        page-break-before: always !important;
        border: none !important;
        padding-top: 20px !important;
    }
    .host-report:first-of-type {
        page-break-before: avoid !important;
        padding-top: 0 !important;
    }

    .muted {
        color: #666 !important;
        font-size: 9px !important;
    }

    .dashboard-executivo > div {
        display: block !important;
        width: 100% !important;
        margin-bottom: 15px !important;
        padding: 15px !important;
        border: 1px solid #eee !important;
        border-left: 5px solid !important;
        page-break-inside: avoid !important;
    }

    .report-dash-grid {
        display: block !important;
    }
}

#report-header-print { display: none; }
</style>

<div id="report-header-print">
    <div style="display:flex; justify-content:space-between; align-items:center">
        <div>
            <img src="/assets/logo_armazem.png" alt="Logo" style="height:50px">
        </div>
        <div style="text-align:right">
            <h1 style="margin:0; color:var(--primary)">Relatório de Infraestrutura</h1>
            <div class="muted">Gerado em: <?= date('d/m/Y H:i') ?> · Período: <?= $period ?> dias</div>
            <?php if ($company_id): 
                $c_idx = array_search($company_id, array_column($companies, 'id'));
                $c_name = $c_idx !== false ? $companies[$c_idx]['name'] : 'N/A';
            ?>
                <div style="font-weight:700">Empresa: <?= h((string)$c_name) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
render_footer();



