<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('cliente');
$clientId = (int)$user['id'];
$zbxError = '';
$host = null;
$items = [];

$hostId = trim((string)($_GET['hostid'] ?? ''));
if ($hostId === '') {
    http_response_code(400);
    exit('HostID obrigatório');
}

$zbxConfig = zbx_config_from_db($pdo, $config);

try {
    $auth = zbx_auth($zbxConfig);
    
    // Verificar se o host pertence ao cliente
    $clientGroups = zbx_hostgroups_for_client($pdo, $clientId);
    
    if (empty($clientGroups)) {
        http_response_code(403);
        exit('Acesso negado: nenhum hostgroup vinculado ao cliente.');
    }

    $allGroupIds = [];
    $groupNames = [];
    foreach ($clientGroups as $cg) {
        $gid = trim((string)($cg['hostgroupid'] ?? ''));
        if ($gid !== '' && ctype_digit($gid)) {
            $allGroupIds[] = $gid;
        }
        $gname = trim((string)($cg['name'] ?? ''));
        if ($gname !== '') {
            $groupNames[] = $gname;
        }
    }

    if (!empty($groupNames)) {
        try {
            $foundGroups = zbx_rpc($zbxConfig, 'hostgroup.get', [
                'output' => ['groupid'],
                'filter' => ['name' => $groupNames]
            ], $auth);
            if (is_array($foundGroups)) {
                foreach ($foundGroups as $fg) {
                    $fgid = (string)($fg['groupid'] ?? '');
                    if ($fgid !== '') {
                        $allGroupIds[] = $fgid;
                    }
                }
            }
        } catch (Throwable $e) {
            // Silently ignore name resolution errors, rely on IDs
        }
    }
    
    $allGroupIds = array_values(array_unique($allGroupIds));

    if (empty($allGroupIds)) {
        http_response_code(403);
        exit('Acesso negado: IDs de hostgroup inválidos.');
    }

    $hosts = zbx_rpc(
        $zbxConfig,
        'host.get',
        [
            'hostids' => [$hostId],
            'groupids' => $allGroupIds,
            'selectInterfaces' => ['ip'],
            'output' => ['hostid', 'host', 'name', 'status'],
        ],
        $auth
    );
    
    if (is_array($hosts) && isset($hosts[0])) {
        $host = $hosts[0];
    } else {
        http_response_code(403);
        exit('Acesso negado ou host não encontrado.');
    }

    $items = zbx_rpc(
        $zbxConfig,
        'item.get',
        [
            'hostids' => [$hostId],
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units', 'value_type'],
            'sortfield' => 'name',
            'limit' => 200,
            'webitems' => true,
        ],
        $auth
    );
    if (!is_array($items)) {
        $items = [];
    }
} catch (Throwable $e) {
    $zbxError = $e->getMessage();
}

$cpuUsage = null;
$cpuCount = null;
$memTotal = null;
$memUsed = null;
$memFree = null;
$memPercent = null;
$memPercentItem = null;
$diskTotal = null;
$diskUsed = null;
$diskFree = null;
$netIn = null;
$netOut = null;
$uptimeSeconds = null;
$osInfo = null;
$detectedOS = 'unknown';

// Pre-detection based on host name
$hostNameLower = strtolower((string)($host['name'] ?? $host['host'] ?? ''));
if (strpos($hostNameLower, 'lnx') !== false || strpos($hostNameLower, 'linux') !== false) {
    $detectedOS = 'linux';
} elseif (strpos($hostNameLower, 'win') !== false || strpos($hostNameLower, 'windows') !== false) {
    $detectedOS = 'windows';
}

$itemsToFetchHistory = [];

foreach ($items as $item) {
    $name = (string)($item['name'] ?? '');
    $key = (string)($item['key_'] ?? '');
    $value = (string)($item['lastvalue'] ?? '');
    $units = (string)($item['units'] ?? '');
    $itemid = (string)($item['itemid'] ?? '');
    $valueType = (int)($item['value_type'] ?? 0);
    $nameLower = strtolower($name);
    $keyLower = strtolower($key);

    // OS Detection based on Disks or Keys
    if ($detectedOS === 'unknown') {
        if (strpos($keyLower, 'vfs.fs.size[/,') !== false || strpos($nameLower, ' / ') !== false || $nameLower === '/' || strpos($keyLower, 'linux') !== false) {
            $detectedOS = 'linux';
        } elseif (strpos($keyLower, 'vfs.fs.size[c:,') !== false || strpos($nameLower, 'c:') !== false || strpos($keyLower, 'windows') !== false) {
            $detectedOS = 'windows';
        }
    }

    // CPU Count
    if ($cpuCount === null && (
        strpos($keyLower, 'system.cpu.num') !== false || 
        strpos($nameLower, 'vcpus') !== false || 
        strpos($nameLower, 'vcpu') !== false ||
        strpos($nameLower, 'número de cpus') !== false ||
        strpos($nameLower, 'cpu count') !== false
    )) {
        if (is_numeric($value)) $cpuCount = (int)round((float)$value);
    }

    // CPU Usage
    $isCpuUtilItem = ($units === '%' || strpos($keyLower, 'system.cpu.util') !== false || strpos($keyLower, 'cpu.util') !== false || strpos($nameLower, 'cpu %') !== false || strpos($nameLower, 'uso de cpu %') !== false || strpos($nameLower, 'porcentagem') !== false);
    $isMHzItem = (strpos(strtolower($units), 'hz') !== false || strpos($nameLower, 'mhz') !== false);

    if ($isCpuUtilItem && !$isMHzItem) {
        if (is_numeric($value)) {
            if ($cpuUsage === null || $units === '%') {
                $cpuUsage = (float)$value;
                $itemsToFetchHistory['cpu'] = ['id' => $itemid, 'type' => $valueType, 'name' => 'CPU Usage'];
            }
        }
    }
    if ($cpuUsage === null && !$isMHzItem && (
        strpos($keyLower, 'cpu.usage') !== false ||
        (strpos($nameLower, 'cpu') !== false && (
            strpos($nameLower, 'uso') !== false || 
            strpos($nameLower, 'utiliza') !== false || 
            strpos($nameLower, 'usage') !== false || 
            strpos($nameLower, 'utilização') !== false ||
            strpos($nameLower, 'load') !== false
        ))
    )) {
        if (is_numeric($value)) {
            $cpuUsage = (float)$value;
            $itemsToFetchHistory['cpu'] = ['id' => $itemid, 'type' => $valueType, 'name' => 'CPU Usage'];
        }
    }

    // Memory Total
    if ($memTotal === null && (
        strpos($keyLower, 'vm.memory.size[total]') !== false || 
        strpos($nameLower, 'total de memória') !== false || 
        strpos($nameLower, 'total memory') !== false ||
        strpos($nameLower, 'total de memória atribuída na vm') !== false ||
        (strpos($nameLower, 'memória') !== false && strpos($nameLower, 'total') !== false) ||
        (strpos($nameLower, 'ram') !== false && strpos($nameLower, 'total') !== false)
    )) {
        if (is_numeric($value)) $memTotal = (float)$value;
    }

    // Memory Used / Available / Free
    if (strpos($keyLower, 'vm.memory.size[available]') !== false || strpos($keyLower, 'vm.memory.size[free]') !== false || strpos($nameLower, 'memória disponível') !== false || strpos($nameLower, 'available memory') !== false || strpos($nameLower, 'memória livre') !== false) {
        if (is_numeric($value)) $memFree = (float)$value;
    }
    
    if ($memUsed === null && (
        strpos($keyLower, 'vm.memory.size[used]') !== false || 
        strpos($nameLower, 'memória usada') !== false || 
        strpos($nameLower, 'memória em uso') !== false || 
        strpos($nameLower, 'memory used') !== false ||
        strpos($nameLower, 'uso de memória da vm') !== false ||
        (strpos($nameLower, 'ram') !== false && strpos($nameLower, 'usada') !== false)
    )) {
        if (is_numeric($value)) {
            $memUsed = (float)$value;
            $itemsToFetchHistory['mem'] = ['id' => $itemid, 'type' => $valueType, 'name' => 'Memory Usage'];
        }
    }

    // Memory Usage Percentage
    if (strpos($nameLower, 'uso de memória %') !== false || strpos($nameLower, 'memory usage %') !== false) {
        if (is_numeric($value)) {
            $memPercent = (float)$value;
            $memPercentItem = ['id' => $itemid, 'type' => $valueType, 'name' => 'Memory Usage %'];
        }
    }

    // Disk Total (Main partition)
    if ($diskTotal === null && (
        (strpos($keyLower, 'vfs.fs.size') !== false && strpos($keyLower, 'total') !== false) ||
        strpos($nameLower, 'tamanho total do disco') !== false || 
        strpos($nameLower, 'total disk') !== false ||
        (strpos($nameLower, 'disco') !== false && strpos($nameLower, 'total') !== false) ||
        (strpos($keyLower, 'vfs.fs.size[/,total]') !== false) ||
        (strpos($keyLower, 'vfs.fs.size[c:,total]') !== false)
    )) {
        if (is_numeric($value)) $diskTotal = (float)$value;
    }

    // Disk Used / Free
    if (strpos($keyLower, 'vfs.fs.size') !== false && (strpos($keyLower, 'free') !== false || strpos($keyLower, 'pfree') !== false)) {
        if (is_numeric($value)) $diskFree = (float)$value;
    }

    if ($diskUsed === null && (
        (strpos($keyLower, 'vfs.fs.size') !== false && strpos($keyLower, 'used') !== false) ||
        strpos($nameLower, 'espaço usado no disco') !== false || 
        strpos($nameLower, 'used space on disk') !== false ||
        (strpos($nameLower, 'disco') !== false && (strpos($nameLower, 'usado') !== false || strpos($nameLower, 'uso') !== false)) ||
        (strpos($keyLower, 'vfs.fs.size[/,used]') !== false) ||
        (strpos($keyLower, 'vfs.fs.size[c:,used]') !== false)
    )) {
        if (is_numeric($value)) {
            $diskUsed = (float)$value;
            $itemsToFetchHistory['disk'] = ['id' => $itemid, 'type' => $valueType, 'name' => 'Disk Usage'];
        }
    }

    // Network In
    if ($netIn === null && (
        strpos($keyLower, 'net.if.in') !== false || 
        strpos($nameLower, 'bytes recebidos') !== false || 
        strpos($nameLower, 'network interface incoming') !== false ||
        strpos($nameLower, 'tráfego de entrada') !== false
    )) {
        if (is_numeric($value)) {
            $netIn = (float)$value;
            $itemsToFetchHistory['netIn'] = ['id' => $itemid, 'type' => $valueType, 'name' => 'Net In'];
        }
    }

    // Network Out
    if ($netOut === null && (
        strpos($keyLower, 'net.if.out') !== false || 
        strpos($nameLower, 'bytes transmitidos') !== false || 
        strpos($nameLower, 'network interface outgoing') !== false ||
        strpos($nameLower, 'tráfego de saída') !== false
    )) {
        if (is_numeric($value)) {
            $netOut = (float)$value;
            $itemsToFetchHistory['netOut'] = ['id' => $itemid, 'type' => $valueType, 'name' => 'Net Out'];
        }
    }

    // Uptime / VM State
    if ($uptimeSeconds === null && (
        strpos($keyLower, 'system.uptime') !== false || 
        strpos($nameLower, 'tempo que a vm esta online') !== false || 
        strpos($nameLower, 'uptime') !== false ||
        strpos($nameLower, 'tempo de atividade') !== false
    )) {
        if (is_numeric($value)) $uptimeSeconds = (float)$value;
    }

    // OS Info
    if ($osInfo === null && (
        strpos($keyLower, 'system.sw.os') !== false || 
        strpos($keyLower, 'system.uname') !== false ||
        strpos($nameLower, 'operating system') !== false ||
        strpos($nameLower, 'sistema operacional') !== false
    )) {
        $osInfo = $value;
        if ($detectedOS === 'unknown') {
            if (strpos(strtolower($value), 'windows') !== false) $detectedOS = 'windows';
            elseif (strpos(strtolower($value), 'linux') !== false) $detectedOS = 'linux';
        }
    }
}

// Post-processing to calculate missing values
if ($memTotal > 0) {
    if ($memUsed === null && $memFree !== null) $memUsed = $memTotal - $memFree;
    if ($memUsed === null && $memPercent !== null) {
        $memUsed = ($memTotal * $memPercent) / 100;
        if (!isset($itemsToFetchHistory['mem'])) {
            $itemsToFetchHistory['mem'] = $memPercentItem;
        }
    }
}

if ($diskTotal > 0) {
    if ($diskUsed === null && $diskFree !== null) $diskUsed = $diskTotal - $diskFree;
}

$historyData = [];
if ($itemsToFetchHistory) {
    try {
        foreach ($itemsToFetchHistory as $key => $info) {
            $hist = zbx_rpc(
                $zbxConfig,
                'history.get',
                [
                    'itemids' => [$info['id']],
                    'history' => $info['type'],
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 60,
                    'time_from' => time() - 3600,
                ],
                $auth
            );
            if (is_array($hist)) {
                $historyData[$key] = array_reverse($hist);
            }
        }
    } catch (Throwable $e) {
        // Silently fail history fetching
    }
}

function client_format_bytes(?float $bytes): string
{
    if ($bytes === null || $bytes < 0) {
        return 'N/A';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $idx = 0;
    while ($bytes >= 1024 && $idx < count($units) - 1) {
        $bytes /= 1024;
        $idx++;
    }
    return number_format($bytes, 2) . ' ' . $units[$idx];
}

function client_format_percent(?float $value): string
{
    if ($value === null) {
        return 'N/A';
    }
    return number_format($value, 1) . ' %';
}

function client_format_rate(?float $bps): string
{
    if ($bps === null) {
        return 'N/A';
    }
    if ($bps < 1000) {
        return number_format($bps, 0) . ' bps';
    }
    $kbps = $bps / 1000;
    if ($kbps < 1000) {
        return number_format($kbps, 2) . ' Kbps';
    }
    $mbps = $kbps / 1000;
    return number_format($mbps, 2) . ' Mbps';
}

function client_format_duration(?float $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return 'N/A';
    }
    $total = (int)round($seconds);
    $days = intdiv($total, 86400);
    $total -= $days * 86400;
    $hours = intdiv($total, 3600);
    $total -= $hours * 3600;
    $minutes = intdiv($total, 60);

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . 'd';
    }
    if ($hours > 0 || $days > 0) {
        $parts[] = $hours . 'h';
    }
    $parts[] = $minutes . 'm';
    return implode(' ', $parts);
}

$ip = '';
if (isset($host['interfaces']) && is_array($host['interfaces']) && isset($host['interfaces'][0]['ip'])) {
    $ip = (string)$host['interfaces'][0]['ip'];
}

$backUrl = '/cliente_hosts.php';

$detailName = (string)($host['name'] ?? ($host['host'] ?? ''));
$detailHost = (string)($host['host'] ?? '');
$subject = 'Monitoramento - host ' . $detailName;
$description = "Solicito suporte para o host {$detailName} ({$detailHost}).\nIP: {$ip}";
$query = [
    'subject' => $subject,
    'description' => $description,
];
$ticketUrl = '/cliente_abrir_ticket.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

render_header('Cliente · Host ' . (string)$host['host'], $user);
?>
<style>
.dash-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.resource-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.resource-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.resource-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 14px;
    color: var(--muted);
}
.resource-card-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary);
}
.resource-card-icon {
    width: 20px;
    height: 20px;
    color: var(--primary);
}
.resource-chart-container {
    width: 100%;
    height: 100px;
    margin-top: 10px;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}
.info-item {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 16px;
}
.info-item-label {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 4px;
}
.info-item-value {
    font-weight: 600;
    font-size: 14px;
}
</style>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <h1 style="margin: 0; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px;">
            <?php if ($detectedOS === 'linux'): ?>
                <img src="/assets/linux.png" alt="Linux" class="os-icon" style="width: 28px; height: 28px; object-fit: contain;">
            <?php elseif ($detectedOS === 'windows'): ?>
                <img src="/assets/windows.png" alt="Windows" class="os-icon" style="width: 28px; height: 28px; object-fit: contain;">
            <?php endif; ?>
            <?= h((string)($host['name'] ?? $host['host'])) ?>
        </h1>
        <div class="muted" style="margin-top: 4px;">
            HostID <?= h((string)$host['hostid']) ?> · <?= h((string)($host['host'] ?? '')) ?> · IP: <?= h($ip) ?>
        </div>
    </div>
    <div style="display:flex; gap: 8px;">
        <a class="btn primary" href="<?= h($ticketUrl) ?>">Abrir chamado</a>
    </div>
</div>

<?php if ($zbxError): ?><div class="error"><?= h($zbxError) ?></div><?php endif; ?>

<div class="dash-card-grid">
    <!-- CPU Card -->
    <div class="resource-card">
        <div class="resource-card-header">
            <div class="resource-card-title">
                <svg class="resource-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="4" y="4" width="16" height="16" rx="2" />
                    <rect x="9" y="9" width="6" height="6" />
                    <path d="M15 2v2M9 2v2M20 15h2M20 9h2M15 20v2M9 20v2M2 15h2M2 9h2" />
                </svg>
                CPU
            </div>
            <div class="badge"><?= h($cpuCount ?? 'N/A') ?> vCPUs</div>
        </div>
        <div class="resource-card-value"><?= h(format_percent($cpuUsage)) ?></div>
        <div class="resource-chart-container">
            <canvas id="cpuChart"></canvas>
        </div>
    </div>

    <!-- Memory Card -->
    <div class="resource-card">
        <div class="resource-card-header">
            <div class="resource-card-title">
                <svg class="resource-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 19v2M10 19v2M14 19v2M18 19v2M8 11V9a4 4 0 1 1 8 0v2M5 15h14a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z" />
                </svg>
                Memória
            </div>
            <div class="badge"><?= h(format_bytes($memTotal)) ?></div>
        </div>
        <div class="resource-card-value">
            <?php
            if ($memUsed !== null && $memTotal > 0) {
                echo h(format_bytes($memUsed));
            } else {
                echo 'N/A';
            }
            ?>
        </div>
        <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">
            Livre: <?= h(format_bytes($memTotal !== null && $memUsed !== null ? ($memTotal - $memUsed) : null)) ?>
        </div>
        <div class="resource-chart-container">
            <canvas id="memChart"></canvas>
        </div>
    </div>

    <!-- Disk Card -->
    <div class="resource-card">
        <div class="resource-card-header">
            <div class="resource-card-title">
                <svg class="resource-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <ellipse cx="12" cy="5" rx="9" ry="3" />
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                </svg>
                Disco
            </div>
            <div class="badge"><?= h(format_bytes($diskTotal)) ?></div>
        </div>
        <div class="resource-card-value">
            <?php
            if ($diskUsed !== null && $diskTotal > 0) {
                echo h(format_bytes($diskUsed));
            } else {
                echo 'N/A';
            }
            ?>
        </div>
        <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">
            Livre: <?= h(format_bytes($diskTotal !== null && $diskUsed !== null ? ($diskTotal - $diskUsed) : null)) ?>
        </div>
        <div class="resource-chart-container">
            <canvas id="diskChart"></canvas>
        </div>
    </div>

    <!-- Network Card -->
    <div class="resource-card">
        <div class="resource-card-header">
            <div class="resource-card-title">
                <svg class="resource-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                Rede
            </div>
        </div>
        <div style="display: flex; gap: 16px; margin-top: 4px;">
            <div>
                <div class="info-item-label">Download</div>
                <div style="font-weight: 700; color: var(--primary);"><?= h(client_format_rate($netIn)) ?></div>
            </div>
            <div>
                <div class="info-item-label">Upload</div>
                <div style="font-weight: 700; color: var(--primary);"><?= h(client_format_rate($netOut)) ?></div>
            </div>
        </div>
        <div class="resource-chart-container">
            <canvas id="netChart"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <div style="font-weight: 700; margin-bottom: 16px; font-size: 16px;">Informações Gerais</div>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-item-label">Uptime</div>
            <div class="info-item-value"><?= h(client_format_duration($uptimeSeconds)) ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Status</div>
            <div class="info-item-value">
                <span class="badge" style="background: <?= (string)($host['status'] ?? '0') === '0' ? 'rgba(39,196,168,0.1)' : 'rgba(255,90,95,0.1)' ?>; color: <?= (string)($host['status'] ?? '0') === '0' ? 'var(--primary)' : 'var(--danger)' ?>; border-color: transparent;">
                    <?= (string)($host['status'] ?? '0') === '0' ? 'Ativo' : 'Inativo' ?>
                </span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Endereço IP</div>
            <div class="info-item-value"><?= h($ip) ?></div>
        </div>
        <?php if ($osInfo): ?>
        <div class="info-item">
            <div class="info-item-label">Sistema Operacional</div>
            <div class="info-item-value"><?= h($osInfo) ?></div>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <div class="info-item-label">Zabbix HostID</div>
            <div class="info-item-value"><?= h((string)$host['hostid']) ?></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const historyData = <?= json_encode($historyData) ?>;
    const chartConfig = {
        type: 'line',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: false },
                y: {
                    display: false,
                    beginAtZero: true
                }
            },
            elements: {
                point: { radius: 0 },
                line: { tension: 0.4, borderWidth: 2 }
            }
        }
    };

    function createChart(id, data, color) {
        const ctx = document.getElementById(id).getContext('2d');
        const labels = data.map(d => new Date(d.clock * 1000).toLocaleTimeString());
        const values = data.map(d => parseFloat(d.value));
        
        return new Chart(ctx, {
            ...chartConfig,
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    borderColor: color,
                    backgroundColor: color + '20',
                    fill: true
                }]
            }
        });
    }

    if (historyData.cpu) createChart('cpuChart', historyData.cpu, '#27c4a8');
    if (historyData.mem) createChart('memChart', historyData.mem, '#27c4a8');
    if (historyData.disk) createChart('diskChart', historyData.disk, '#27c4a8');
    
    if (historyData.netIn || historyData.netOut) {
        const netCtx = document.getElementById('netChart').getContext('2d');
        const labels = (historyData.netIn || historyData.netOut).map(d => new Date(d.clock * 1000).toLocaleTimeString());
        new Chart(netCtx, {
            ...chartConfig,
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Download',
                        data: (historyData.netIn || []).map(d => parseFloat(d.value)),
                        borderColor: '#27c4a8',
                        backgroundColor: '#27c4a820',
                        fill: true
                    },
                    {
                        label: 'Upload',
                        data: (historyData.netOut || []).map(d => parseFloat(d.value)),
                        borderColor: '#ff5a5f',
                        backgroundColor: '#ff5a5f20',
                        fill: true
                    }
                ]
            }
        });
    }
});
</script>
<?php
render_footer();



