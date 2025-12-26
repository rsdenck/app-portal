<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');
$pluginName = $_GET['name'] ?? '';
$plugin = plugin_get_by_name($pdo, $pluginName);

if (!$plugin) {
    header('Location: /atendente_plugins.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    
    $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';
    plugin_update_status($pdo, $pluginName, $isActive);
    
    $config = $_POST['config'] ?? [];
    plugin_update_config($pdo, $pluginName, $config);
    
    // Special handling for Zabbix to maintain backward compatibility if needed
    if ($pluginName === 'zabbix') {
        zbx_settings_save(
            $pdo, 
            $config['url'] ?? '', 
            $config['username'] ?? '', 
            $config['password'] ?? '', 
            isset($config['ignore_ssl']) && $config['ignore_ssl'] === '1'
        );
    }
    
    $success = 'Configurações do plugin ' . h($plugin['label']) . ' salvas.';
    $plugin = plugin_get_by_name($pdo, $pluginName); // Refresh data
}

render_header('Configurar Plugin · ' . $plugin['label'], $user);
?>

<div class="card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div style="display:flex;gap:12px;align-items:center">
      <a href="/app/atendente_plugins.php" class="btn" style="padding:8px">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </a>
      <div style="font-weight:700;font-size:18px">Configurar <?= h($plugin['label']) ?></div>
    </div>
    <div class="plugin-category"><?= h($plugin['category']) ?></div>
  </div>

  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    
    <div class="config-section">
      <div style="font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:10px">
        Status do Plugin
      </div>
      <label class="switch-container">
        <input type="checkbox" name="is_active" value="1" <?= $plugin['is_active'] ? 'checked' : '' ?>>
        <span class="switch-label">Ativar este plugin</span>
      </label>
      <div class="muted" style="margin-top:6px">Plugins ativos podem adicionar novas opções ao menu lateral.</div>
    </div>

    <div class="config-section" style="margin-top:24px">
      <div style="font-weight:600;margin-bottom:12px">Configurações da API</div>
      
      <?php if ($pluginName === 'zabbix'): ?>
        <div class="row">
          <div class="col">
            <label>URL da API do Zabbix</label>
            <input name="config[url]" value="<?= h($plugin['config']['url'] ?? '') ?>" placeholder="https://zabbix.exemplo.com/api_jsonrpc.php">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Usuário</label>
            <input name="config[username]" value="<?= h($plugin['config']['username'] ?? '') ?>">
          </div>
          <div class="col">
            <label>Senha</label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>
              <input type="checkbox" name="config[ignore_ssl]" value="1" <?= ($plugin['config']['ignore_ssl'] ?? '') === '1' ? 'checked' : '' ?>>
              Ignorar validação SSL
            </label>
          </div>
        </div>

      <?php elseif ($pluginName === 'security_gateway'): ?>
        <div class="row">
          <div class="col">
            <label>URL do Security Gateway (JSON-RPC)</label>
            <input name="config[url]" value="<?= h($plugin['config']['url'] ?? '') ?>" placeholder="https://10.0.0.1">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Usuário</label>
            <input name="config[username]" value="<?= h($plugin['config']['username'] ?? '') ?>">
          </div>
          <div class="col">
            <label>Senha</label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>">
          </div>
        </div>

      <?php elseif ($pluginName === 'vcenter'): ?>
        <div id="vcenter-servers-container">
          <?php 
          $servers = $plugin['config']['servers'] ?? [];
          if (empty($servers)) {
              if (!empty($plugin['config']['url'])) {
                  $servers[] = [
                      'label' => 'vCenter Principal',
                      'url' => $plugin['config']['url'],
                      'username' => $plugin['config']['username'] ?? '',
                      'password' => $plugin['config']['password'] ?? ''
                  ];
              } else {
                  $servers[] = ['label' => '', 'url' => '', 'username' => '', 'password' => ''];
              }
          }
          foreach ($servers as $idx => $srv): 
          ?>
            <div class="card vcenter-server-item" style="margin-bottom:16px; border:1px solid var(--border)">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                <div style="font-weight:600">Servidor vCenter #<?= $idx + 1 ?></div>
                <button type="button" class="btn danger small remove-vcenter-server" style="padding:4px 8px">Remover</button>
              </div>
              <div class="row">
                <div class="col">
                  <label>Nome de Exibição (Ex: vCenter Matriz)</label>
                  <input name="config[servers][<?= $idx ?>][label]" value="<?= h($srv['label'] ?? '') ?>" placeholder="Um nome para você identificar este servidor no portal">
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <label>URL do vCenter (Ex: https://vcenter.exemplo.com)</label>
                  <input name="config[servers][<?= $idx ?>][url]" value="<?= h($srv['url'] ?? '') ?>" placeholder="https://...">
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <label>Usuário (ex: administrator@vsphere.local)</label>
                  <input name="config[servers][<?= $idx ?>][username]" value="<?= h($srv['username'] ?? '') ?>">
                </div>
                <div class="col">
                  <label>Senha</label>
                  <input name="config[servers][<?= $idx ?>][password]" type="password" value="<?= h($srv['password'] ?? '') ?>">
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" id="add-vcenter-server" class="btn secondary" style="margin-bottom:24px">+ Adicionar vCenter</button>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vContainer = document.getElementById('vcenter-servers-container');
            const vAddButton = document.getElementById('add-vcenter-server');
            
            if (vAddButton) {
                vAddButton.addEventListener('click', function() {
                    const idx = vContainer.querySelectorAll('.vcenter-server-item').length;
                    const div = document.createElement('div');
                    div.className = 'card vcenter-server-item';
                    div.style.marginBottom = '16px';
                    div.style.border = '1px solid var(--border)';
                    div.innerHTML = `
                      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                        <div style="font-weight:600">Servidor vCenter #${idx + 1}</div>
                        <button type="button" class="btn danger small remove-vcenter-server" style="padding:4px 8px">Remover</button>
                      </div>
                      <div class="row">
                        <div class="col">
                          <label>Nome de Exibição (Ex: vCenter Matriz)</label>
                          <input name="config[servers][${idx}][label]" placeholder="Um nome para você identificar este servidor no portal">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col">
                          <label>URL do vCenter</label>
                          <input name="config[servers][${idx}][url]" placeholder="https://...">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col">
                          <label>Usuário</label>
                          <input name="config[servers][${idx}][username]">
                        </div>
                        <div class="col">
                          <label>Senha</label>
                          <input name="config[servers][${idx}][password]" type="password">
                        </div>
                      </div>
                    `;
                    vContainer.appendChild(div);
                    bindVcenterRemoves();
                });
            }

            function bindVcenterRemoves() {
                vContainer.querySelectorAll('.remove-vcenter-server').forEach(btn => {
                    btn.onclick = function() {
                        if (vContainer.querySelectorAll('.vcenter-server-item').length > 1) {
                            btn.closest('.vcenter-server-item').remove();
                        }
                    };
                });
            }
            bindVcenterRemoves();
        });
        </script>

      <?php elseif ($pluginName === 'veeam'): ?>
        <div id="veeam-servers-container">
          <?php 
          $servers = $plugin['config']['servers'] ?? [];
          if (empty($servers)) {
              // Migration/Initial state: convert old single config to new multi format if exists
              if (!empty($plugin['config']['url'])) {
                  $servers[] = [
                      'type' => 'vbr',
                      'label' => 'Servidor Principal',
                      'url' => $plugin['config']['url'],
                      'username' => $plugin['config']['username'] ?? '',
                      'password' => $plugin['config']['password'] ?? ''
                  ];
              } else {
                  $servers[] = ['type' => 'vbr', 'label' => '', 'url' => '', 'username' => '', 'password' => ''];
              }
          }
          foreach ($servers as $idx => $srv): 
          ?>
            <div class="card veeam-server-item" style="margin-bottom:16px; border:1px solid var(--border)">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                <div style="font-weight:600">Servidor #<?= $idx + 1 ?></div>
                <button type="button" class="btn danger small remove-server" style="padding:4px 8px">Remover</button>
              </div>
              <div class="row">
                <div class="col" style="flex:0 0 150px">
                  <label>Tipo</label>
                  <select name="config[servers][<?= $idx ?>][type]" class="btn" style="width:100%; background:var(--input-bg); cursor:pointer">
                    <option value="vbr" <?= ($srv['type'] ?? '') === 'vbr' ? 'selected' : '' ?>>VBR (Enterprise Manager)</option>
                    <option value="vcsp" <?= ($srv['type'] ?? '') === 'vcsp' ? 'selected' : '' ?>>VCSP (Service Provider)</option>
                  </select>
                </div>
                <div class="col">
                  <label>Identificador (Ex: DC-Principal)</label>
                  <input name="config[servers][<?= $idx ?>][label]" value="<?= h($srv['label'] ?? '') ?>" placeholder="Nome para identificar este servidor">
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <label>URL da API (Ex: https://veeam.exemplo.com:9398/api)</label>
                  <input name="config[servers][<?= $idx ?>][url]" value="<?= h($srv['url'] ?? '') ?>" placeholder="https://...">
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <label>Usuário</label>
                  <input name="config[servers][<?= $idx ?>][username]" value="<?= h($srv['username'] ?? '') ?>">
                </div>
                <div class="col">
                  <label>Senha</label>
                  <input name="config[servers][<?= $idx ?>][password]" type="password" value="<?= h($srv['password'] ?? '') ?>">
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" id="add-veeam-server" class="btn secondary" style="margin-bottom:24px">+ Adicionar Servidor (VBR ou VCSP)</button>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('veeam-servers-container');
            const addButton = document.getElementById('add-veeam-server');
            
            addButton.addEventListener('click', function() {
                const idx = container.querySelectorAll('.veeam-server-item').length;
                const div = document.createElement('div');
                div.className = 'card veeam-server-item';
                div.style.marginBottom = '16px';
                div.style.border = '1px solid var(--border)';
                div.innerHTML = `
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
                    <div style="font-weight:600">Servidor #${idx + 1}</div>
                    <button type="button" class="btn danger small remove-server" style="padding:4px 8px">Remover</button>
                  </div>
                  <div class="row">
                    <div class="col" style="flex:0 0 150px">
                      <label>Tipo</label>
                      <select name="config[servers][${idx}][type]" class="btn" style="width:100%; background:var(--input-bg); cursor:pointer">
                        <option value="vbr">VBR (Enterprise Manager)</option>
                        <option value="vcsp">VCSP (Service Provider)</option>
                      </select>
                    </div>
                    <div class="col">
                      <label>Identificador</label>
                      <input name="config[servers][${idx}][label]" placeholder="Nome para identificar este servidor">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <label>URL da API</label>
                      <input name="config[servers][${idx}][url]" placeholder="https://...">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <label>Usuário</label>
                      <input name="config[servers][${idx}][username]">
                    </div>
                    <div class="col">
                      <label>Senha</label>
                      <input name="config[servers][${idx}][password]" type="password">
                    </div>
                  </div>
                `;
                container.appendChild(div);
                bindRemoves();
            });

            function bindRemoves() {
                container.querySelectorAll('.remove-server').forEach(btn => {
                    btn.onclick = function() {
                        if (container.querySelectorAll('.veeam-server-item').length > 1) {
                            btn.closest('.veeam-server-item').remove();
                        } else {
                            alert('Pelo menos um servidor deve ser configurado.');
                        }
                    };
                });
            }
            bindRemoves();
        });
        </script>

      <?php elseif ($pluginName === 'acronis'): ?>
        <div class="row">
          <div class="col">
            <label>Datacenter URL</label>
            <input name="config[url]" value="<?= h($plugin['config']['url'] ?? '') ?>" placeholder="https://eu-cloud.acronis.com">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Client ID / Username</label>
            <input name="config[username]" value="<?= h($plugin['config']['username'] ?? '') ?>">
          </div>
          <div class="col">
            <label>Client Secret / Password</label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>">
          </div>
        </div>

      <?php elseif ($pluginName === 'zimbra'): ?>
        <div class="row">
          <div class="col">
            <label>Zimbra Admin URL</label>
            <input name="config[url]" value="<?= h($plugin['config']['url'] ?? '') ?>" placeholder="https://mail.exemplo.com:7071">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Admin Username</label>
            <input name="config[username]" value="<?= h($plugin['config']['username'] ?? '') ?>">
          </div>
          <div class="col">
            <label>Admin Password</label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>">
          </div>
        </div>

      <?php elseif ($pluginName === 'whm'): ?>
        <div class="row">
          <div class="col">
            <label>WHM Hostname/IP</label>
            <input name="config[url]" value="<?= h($plugin['config']['url'] ?? '') ?>" placeholder="https://servidor.exemplo.com:2087">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Username (geralmente root)</label>
            <input name="config[username]" value="<?= h($plugin['config']['username'] ?? '') ?>">
          </div>
          <div class="col">
            <label>API Token</label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>">
          </div>
        </div>

      <?php elseif ($pluginName === 'nsx'): ?>
        <div id="nsx-managers">
          <?php 
          $managers = $plugin['config']['managers'] ?? [['url' => '', 'username' => '', 'password' => '']];
          foreach ($managers as $idx => $m): 
          ?>
            <div class="config-section" style="margin-bottom:15px; background: rgba(0,0,0,0.1)">
              <div style="font-weight:600; margin-bottom:10px">NSX Manager #<?= $idx + 1 ?></div>
              <div class="row">
                <div class="col">
                  <label>URL</label>
                  <input name="config[managers][<?= $idx ?>][url]" value="<?= h($m['url'] ?? '') ?>" placeholder="https://nsx.exemplo.com">
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <label>Usuário</label>
                  <input name="config[managers][<?= $idx ?>][username]" value="<?= h($m['username'] ?? '') ?>">
                </div>
                <div class="col">
                  <label>Senha</label>
                  <input name="config[managers][<?= $idx ?>][password]" type="password" value="<?= h($m['password'] ?? '') ?>">
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm" onclick="addNsxManager()" style="margin-top:10px">+ Adicionar Outro Manager</button>
        <script>
          function addNsxManager() {
            const container = document.getElementById('nsx-managers');
            const idx = container.children.length;
            const div = document.createElement('div');
            div.className = 'config-section';
            div.style.marginBottom = '15px';
            div.style.background = 'rgba(0,0,0,0.1)';
            div.innerHTML = `
              <div style="font-weight:600; margin-bottom:10px">NSX Manager #${idx + 1}</div>
              <div class="row"><div class="col"><label>URL</label><input name="config[managers][${idx}][url]" placeholder="https://nsx.exemplo.com"></div></div>
              <div class="row">
                <div class="col"><label>Usuário</label><input name="config[managers][${idx}][username]"></div>
                <div class="col"><label>Senha</label><input name="config[managers][${idx}][password]" type="password"></div>
              </div>
            `;
            container.appendChild(div);
          }
        </script>

      <?php elseif ($pluginName === 'snmp'): ?>
        <div id="snmp-devices">
          <?php 
          $devices = $plugin['config']['devices'] ?? [['ip' => '', 'port' => '161', 'version' => '2c', 'community' => 'public', 'v3_user' => '', 'v3_auth_proto' => 'SHA', 'v3_auth_pass' => '', 'v3_priv_proto' => 'AES', 'v3_priv_pass' => '', 'v3_sec_level' => 'authPriv']];
          foreach ($devices as $idx => $d): 
            $version = $d['version'] ?? '2c';
          ?>
            <div class="config-section snmp-device-item" style="margin-bottom:15px; background: rgba(0,0,0,0.1); padding: 15px; border-radius: 8px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px">
                <div style="font-weight:600">Equipamento SNMP #<?= $idx + 1 ?></div>
                <button type="button" class="btn danger small" onclick="this.closest('.snmp-device-item').remove()" style="padding:2px 8px">Remover</button>
              </div>
              <div class="row">
                <div class="col" style="flex:2">
                  <label>Endereço IP / Hostname</label>
                  <input name="config[devices][<?= $idx ?>][ip]" value="<?= h($d['ip'] ?? '') ?>" placeholder="192.168.1.1">
                </div>
                <div class="col">
                  <label>Porta</label>
                  <input name="config[devices][<?= $idx ?>][port]" value="<?= h($d['port'] ?? '161') ?>">
                </div>
                <div class="col">
                  <label>Versão</label>
                  <select name="config[devices][<?= $idx ?>][version]" onchange="toggleSnmpVersion(this, <?= $idx ?>)">
                    <option value="1" <?= $version === '1' ? 'selected' : '' ?>>v1</option>
                    <option value="2c" <?= $version === '2c' ? 'selected' : '' ?>>v2c</option>
                    <option value="3" <?= $version === '3' ? 'selected' : '' ?>>v3</option>
                  </select>
                </div>
              </div>

              <!-- v1/v2c Config -->
              <div class="snmp-v12-fields" id="snmp-v12-<?= $idx ?>" style="display: <?= $version !== '3' ? 'block' : 'none' ?>">
                <div class="row">
                  <div class="col">
                    <label>Community</label>
                    <input name="config[devices][<?= $idx ?>][community]" value="<?= h($d['community'] ?? 'public') ?>">
                  </div>
                </div>
              </div>

              <!-- v3 Config -->
              <div class="snmp-v3-fields" id="snmp-v3-<?= $idx ?>" style="display: <?= $version === '3' ? 'block' : 'none' ?>">
                <div class="row">
                  <div class="col">
                    <label>Security Level</label>
                    <select name="config[devices][<?= $idx ?>][v3_sec_level]">
                      <option value="noAuthNoPriv" <?= ($d['v3_sec_level'] ?? '') === 'noAuthNoPriv' ? 'selected' : '' ?>>noAuthNoPriv</option>
                      <option value="authNoPriv" <?= ($d['v3_sec_level'] ?? '') === 'authNoPriv' ? 'selected' : '' ?>>authNoPriv</option>
                      <option value="authPriv" <?= ($d['v3_sec_level'] ?? '') === 'authPriv' ? 'selected' : '' ?>>authPriv</option>
                    </select>
                  </div>
                  <div class="col">
                    <label>Usuário (Security Name)</label>
                    <input name="config[devices][<?= $idx ?>][v3_user]" value="<?= h($d['v3_user'] ?? '') ?>">
                  </div>
                </div>
                <div class="row">
                  <div class="col">
                    <label>Auth Protocol</label>
                    <select name="config[devices][<?= $idx ?>][v3_auth_proto]">
                      <option value="MD5" <?= ($d['v3_auth_proto'] ?? '') === 'MD5' ? 'selected' : '' ?>>MD5</option>
                      <option value="SHA" <?= ($d['v3_auth_proto'] ?? '') === 'SHA' ? 'selected' : '' ?>>SHA</option>
                      <option value="SHA-256" <?= ($d['v3_auth_proto'] ?? '') === 'SHA-256' ? 'selected' : '' ?>>SHA-256</option>
                    </select>
                  </div>
                  <div class="col">
                    <label>Auth Password</label>
                    <input name="config[devices][<?= $idx ?>][v3_auth_pass]" type="password" value="<?= h($d['v3_auth_pass'] ?? '') ?>">
                  </div>
                </div>
                <div class="row">
                  <div class="col">
                    <label>Priv Protocol</label>
                    <select name="config[devices][<?= $idx ?>][v3_priv_proto]">
                      <option value="DES" <?= ($d['v3_priv_proto'] ?? '') === 'DES' ? 'selected' : '' ?>>DES</option>
                      <option value="AES" <?= ($d['v3_priv_proto'] ?? '') === 'AES' ? 'selected' : '' ?>>AES</option>
                      <option value="AES-256" <?= ($d['v3_priv_proto'] ?? '') === 'AES-256' ? 'selected' : '' ?>>AES-256</option>
                    </select>
                  </div>
                  <div class="col">
                    <label>Priv Password</label>
                    <input name="config[devices][<?= $idx ?>][v3_priv_pass]" type="password" value="<?= h($d['v3_priv_pass'] ?? '') ?>">
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm" onclick="addSnmpDevice()" style="margin-top:10px">+ Adicionar Equipamento</button>
        <script>
          function toggleSnmpVersion(select, idx) {
            const v12 = document.getElementById('snmp-v12-' + idx);
            const v3 = document.getElementById('snmp-v3-' + idx);
            if (select.value === '3') {
              v12.style.display = 'none';
              v3.style.display = 'block';
            } else {
              v12.style.display = 'block';
              v3.style.display = 'none';
            }
          }

          function addSnmpDevice() {
            const container = document.getElementById('snmp-devices');
            const idx = container.querySelectorAll('.snmp-device-item').length;
            const div = document.createElement('div');
            div.className = 'config-section snmp-device-item';
            div.style.marginBottom = '15px';
            div.style.background = 'rgba(0,0,0,0.1)';
            div.style.padding = '15px';
            div.style.borderRadius = '8px';
            div.innerHTML = `
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px">
                <div style="font-weight:600">Equipamento SNMP #${idx + 1}</div>
                <button type="button" class="btn danger small" onclick="this.closest('.snmp-device-item').remove()" style="padding:2px 8px">Remover</button>
              </div>
              <div class="row">
                <div class="col" style="flex:2"><label>Endereço IP / Hostname</label><input name="config[devices][${idx}][ip]" placeholder="192.168.1.1"></div>
                <div class="col"><label>Porta</label><input name="config[devices][${idx}][port]" value="161"></div>
                <div class="col">
                  <label>Versão</label>
                  <select name="config[devices][${idx}][version]" onchange="toggleSnmpVersion(this, ${idx})">
                    <option value="1">v1</option>
                    <option value="2c" selected>v2c</option>
                    <option value="3">v3</option>
                  </select>
                </div>
              </div>
              <div class="snmp-v12-fields" id="snmp-v12-${idx}">
                <div class="row"><div class="col"><label>Community</label><input name="config[devices][${idx}][community]" value="public"></div></div>
              </div>
              <div class="snmp-v3-fields" id="snmp-v3-${idx}" style="display:none">
                <div class="row">
                  <div class="col"><label>Security Level</label><select name="config[devices][${idx}][v3_sec_level]"><option value="noAuthNoPriv">noAuthNoPriv</option><option value="authNoPriv">authNoPriv</option><option value="authPriv" selected>authPriv</option></select></div>
                  <div class="col"><label>Usuário</label><input name="config[devices][${idx}][v3_user]"></div>
                </div>
                <div class="row">
                  <div class="col"><label>Auth Protocol</label><select name="config[devices][${idx}][v3_auth_proto]"><option value="MD5">MD5</option><option value="SHA" selected>SHA</option></select></div>
                  <div class="col"><label>Auth Password</label><input name="config[devices][${idx}][v3_auth_pass]" type="password"></div>
                </div>
                <div class="row">
                  <div class="col"><label>Priv Protocol</label><select name="config[devices][${idx}][v3_priv_proto]"><option value="DES">DES</option><option value="AES" selected>AES</option></select></div>
                  <div class="col"><label>Priv Password</label><input name="config[devices][${idx}][v3_priv_pass]" type="password"></div>
                </div>
              </div>
            `;
            container.appendChild(div);
          }
        </script>

      <?php elseif (in_array($pluginName, ['wazuh', 'nuclei', 'deepflow', 'guacamole', 'cloudflare', 'elasticsearch', 'netflow'])): ?>
        <div class="row">
          <div class="col">
            <label>URL do Servidor / API</label>
            <input name="config[url]" value="<?= h($plugin['config']['url'] ?? ($pluginName === 'cloudflare' ? 'https://api.cloudflare.com/client/v4' : '')) ?>" placeholder="https://api.exemplo.com">
            <?php if ($pluginName === 'elasticsearch'): ?>
              <div class="muted" style="font-size: 12px; margin-top: 4px">Para versões antigas, use apenas a URL (ex: http://10.2.40.125:9200). Usuário/Senha são opcionais.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label><?= $pluginName === 'cloudflare' ? 'E-mail da Conta' : 'Usuário / API Key ID' ?></label>
            <input name="config[username]" value="<?= h($plugin['config']['username'] ?? '') ?>">
          </div>
          <div class="col">
            <label><?= $pluginName === 'cloudflare' ? 'Global API Key / Token' : 'Senha / Secret Key' ?></label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>">
          </div>
        </div>

      <?php elseif (in_array($pluginName, ['abuseipdb', 'shodan', 'ipinfo'])): ?>
        <div class="row">
          <div class="col">
            <?php if (in_array($pluginName, ['netflow'])): ?>
              <label><?= ucfirst($pluginName) ?> API URL</label>
              <input name="config[url]" value="<?= h($plugin['config']['url'] ?? '') ?>" placeholder="https://api.exemplo.com">
            <?php endif; ?>
            
            <label>API Token / Access Key</label>
            <input name="config[password]" type="password" value="<?= h($plugin['config']['password'] ?? '') ?>" placeholder="Insira seu token de acesso">
          </div>
        </div>
      <?php elseif ($pluginName === 'bgpview'): ?>
        <div class="row">
          <div class="col">
            <label>Meu ASN (Global)</label>
            <input name="config[my_asn]" value="<?= h($plugin['config']['my_asn'] ?? '') ?>" placeholder="Ex: 15169">
            <div class="muted" style="margin-top:6px">O ASN definido aqui será usado como base para o Dashboard da Network.</div>
          </div>
          <div class="col">
            <label>Blocos de IP (Vírgula para múltiplos)</label>
            <input name="config[ip_blocks]" value="<?= h($plugin['config']['ip_blocks'] ?? '') ?>" placeholder="Ex: 1.1.1.0/24, 8.8.8.0/24">
            <div class="muted" style="margin-top:6px">Estes blocos serão usados para monitoramento de ameaças (Shodan).</div>
          </div>
        </div>
        <div class="muted" style="margin-top:12px">Este plugin utiliza a API pública do BGPView para consultas.</div>
      <?php endif; ?>
    </div>

    <div style="margin-top:24px">
      <button class="btn primary" type="submit">Salvar Configurações</button>
    </div>
  </form>
</div>

<style>
.plugin-category {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: var(--border);
  padding: 4px 10px;
  border-radius: 6px;
  color: var(--muted);
  font-weight: 600;
}
.config-section {
  padding: 20px;
  background: rgba(255, 255, 255, 0.02);
  border-radius: 12px;
  border: 1px solid var(--border);
}
.switch-container {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  user-select: none;
}
.switch-label {
  font-weight: 500;
  color: var(--text);
}
</style>

<?php
render_footer();



