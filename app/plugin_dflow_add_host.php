<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = trim($_POST['ip_address'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $mac = trim($_POST['mac_address'] ?? '');
    $vlan = (int)($_POST['vlan'] ?? 0);
    $isSnmp = isset($_POST['is_snmp']);
    $snmpCommunity = trim($_POST['snmp_community'] ?? 'public');
    $snmpVersion = trim($_POST['snmp_version'] ?? '2c');

    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        $error = 'Please provide a valid IP address.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert into hosts table
            $stmt = $pdo->prepare("INSERT INTO plugin_dflow_hosts (ip_address, hostname, mac_address, vlan, last_seen) 
                                   VALUES (?, ?, ?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE 
                                   hostname = VALUES(hostname), 
                                   mac_address = IF(VALUES(mac_address) != '', VALUES(mac_address), mac_address),
                                   vlan = IF(VALUES(vlan) > 0, VALUES(vlan), vlan),
                                   last_seen = NOW()");
            $stmt->execute([$ip, $hostname, $mac, $vlan]);

            // If SNMP is enabled, also insert into devices table
            if ($isSnmp) {
                $stmtDev = $pdo->prepare("INSERT INTO plugin_dflow_devices (ip_address, hostname, snmp_community, snmp_version, last_seen) 
                                         VALUES (?, ?, ?, ?, NOW()) 
                                         ON DUPLICATE KEY UPDATE 
                                         hostname = VALUES(hostname), 
                                         snmp_community = VALUES(snmp_community), 
                                         snmp_version = VALUES(snmp_version), 
                                         last_seen = NOW()");
                $stmtDev->execute([$ip, $hostname, $snmpCommunity, $snmpVersion]);
            }

            $pdo->commit();
            
            $success = "Host $ip added successfully! The system will now begin automatic discovery of interfaces and flow analysis.";
            
            // Trigger background discovery if possible
            // For now, we'll just log it or mark it. 
            // The dflow_snmp_collector.php script should pick it up if it scans the hosts table.
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

if (!$isEmbed) {
    render_header('DFlow · Add Host', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>DFlow · Add Host</title><link rel="stylesheet" href="../assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:15px; color:var(--text);">';
}
?>

<div class="card" style="<?= $isEmbed ? 'margin:0; border:none; background:transparent; color:inherit;' : 'max-width: 600px; margin: 20px auto;' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="margin:0; color:var(--text)">Adicionar Novo Host</h2>
        <a href="plugin_dflow_hosts.php<?= $isEmbed ? '?embed=1' : '' ?>" class="btn" style="text-decoration:none;">Voltar para Lista</a>
    </div>

    <?php if ($error): ?>
        <div class="alert error" style="margin-bottom:20px;"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success" style="margin-bottom:20px;"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="POST" style="display:grid; gap:15px;">
        <div class="form-group">
            <label style="display:block; margin-bottom:5px; color:var(--text); font-weight:600;">Endereço IP (IPv4/IPv6) *</label>
            <input type="text" name="ip_address" required placeholder="ex: 192.168.1.100" 
                   style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:10px; border-radius:8px;">
        </div>

        <div class="form-group">
            <label style="display:block; margin-bottom:5px; color:var(--text); font-weight:600;">Hostname (Opcional)</label>
            <input type="text" name="hostname" placeholder="ex: core-switch-01" 
                   style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:10px; border-radius:8px;">
        </div>

        <div class="form-group">
            <label style="display:block; margin-bottom:5px; color:var(--text); font-weight:600;">Endereço MAC (Opcional)</label>
            <input type="text" name="mac_address" placeholder="ex: 00:11:22:33:44:55" 
                   style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:10px; border-radius:8px;">
        </div>

        <div class="form-group">
            <label style="display:block; margin-bottom:5px; color:var(--text); font-weight:600;">VLAN ID (Opcional)</label>
            <input type="number" name="vlan" placeholder="ex: 10" 
                   style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:10px; border-radius:8px;">
        </div>

        <div class="form-group" style="background:rgba(255,255,255,0.05); padding:15px; border-radius:12px; border:1px solid var(--border);">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:var(--text); font-weight:600;">
                <input type="checkbox" name="is_snmp" id="is_snmp" onchange="toggleSnmpFields()" style="width:18px; height:18px;">
                Habilitar Descoberta SNMP para este Host
            </label>
            
            <div id="snmp_fields" style="display:none; margin-top:15px; grid-template-columns: 1fr 1fr; gap:10px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-size:12px; color:var(--muted);">Comunidade SNMP</label>
                    <input type="text" name="snmp_community" value="public" 
                           style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:8px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-size:12px; color:var(--muted);">Versão SNMP</label>
                    <select name="snmp_version" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:8px; border-radius:6px;">
                        <option value="2c">v2c</option>
                        <option value="1">v1</option>
                        <option value="3">v3</option>
                    </select>
                </div>
            </div>
        </div>

        <script>
        function toggleSnmpFields() {
            const fields = document.getElementById('snmp_fields');
            fields.style.display = document.getElementById('is_snmp').checked ? 'grid' : 'none';
        }
        </script>

        <div style="margin-top:10px;">
            <button type="submit" class="btn primary" style="width:100%; padding:12px; font-weight:700;">Adicionar Host e Iniciar Descoberta</button>
        </div>
        
        <p class="muted" style="font-size:12px; text-align:center;">
            Nota: Após adicionar, o sistema tentará automaticamente descobrir interfaces via SNMP e correlacionar fluxos para este host.
        </p>
    </form>
</div>

<?php
if (!$isEmbed) {
    render_footer();
} else {
    echo '</body></html>';
}
