<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
require_login();

if (current_user()['role'] !== 'atendente') {
    redirect('/client/cliente_chamado.php');
}

$sessionUser = current_user();

// Get asset types
$stmt = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC");
$assetTypes = $stmt->fetchAll();

// Get clients for filters and forms
$stmt = $pdo->query("SELECT u.id, u.name, cp.company_name FROM users u LEFT JOIN client_profiles cp ON u.id = cp.user_id WHERE u.role = 'cliente' ORDER BY cp.company_name ASC, u.name ASC");
$clients = $stmt->fetchAll();

// Handle search/filters
$search = $_GET['q'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$clientFilter = $_GET['client_id'] ?? '';

$sql = "SELECT a.*, t.name as type_name, cp.company_name as client_company, u.name as client_name 
        FROM assets a 
        JOIN asset_types t ON a.type_id = t.id 
        JOIN users u ON a.client_user_id = u.id 
        LEFT JOIN client_profiles cp ON u.id = cp.user_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (a.name LIKE :search OR a.serial_number LIKE :search OR a.manufacturer LIKE :search OR a.model LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($typeFilter) {
    $sql .= " AND a.type_id = :type_id";
    $params[':type_id'] = $typeFilter;
}

if ($clientFilter) {
    $sql .= " AND a.client_user_id = :client_id";
    $params[':client_id'] = $clientFilter;
}

$sql .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

render_header('Gestão de Ativos', $sessionUser);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px">
        <form method="GET" style="display:flex;gap:10px;flex:1;min-width:300px;flex-wrap:wrap">
            <input type="text" name="q" class="input" placeholder="Buscar..." value="<?= h($search) ?>" style="width:200px">
            <select name="type" class="input" style="width:150px">
                <option value="">Tipos</option>
                <?php foreach ($assetTypes as $type): ?>
                    <option value="<?= $type['id'] ?>" <?= $typeFilter == $type['id'] ? 'selected' : '' ?>><?= h($type['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="client_id" class="input" style="width:200px">
                <option value="">Todos os Clientes</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                        <?= h($client['company_name'] ?: $client['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn primary">Filtrar</button>
            <?php if ($search || $typeFilter || $clientFilter): ?>
                <a href="/app/atendente_ativos.php" class="btn">Limpar</a>
            <?php endif; ?>
        </form>
        <button class="btn primary" onclick="openModal('addAssetModal')">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Ativo
        </button>
    </div>

    <?php if (empty($assets)): ?>
        <div style="text-align:center;padding:60px 20px;background:var(--bg);border:1px dashed var(--border);border-radius:8px">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--muted)" stroke-width="1" style="margin-bottom:15px"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <h3 style="margin-bottom:10px">Nenhum ativo encontrado</h3>
            <p class="muted">Ajuste os filtros ou adicione um novo ativo.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:250px">Cliente</th>
                        <th>Nome do Ativo</th>
                        <th style="width:150px">Tipo</th>
                        <th style="width:150px">IP Principal</th>
                        <th style="width:100px; text-align:right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): 
                        $typeNames = [];
                        if (!empty($asset['type_ids'])) {
                            $ids = explode(',', $asset['type_ids']);
                            foreach ($ids as $tid) {
                                foreach ($assetTypes as $at) {
                                    if ($at['id'] == $tid) {
                                        $typeNames[] = $at['name'];
                                        break;
                                    }
                                }
                            }
                        } elseif (!empty($asset['type_name'])) {
                            $typeNames[] = $asset['type_name'];
                        }
                        $displayTypes = implode(', ', array_unique($typeNames));
                    ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; color:var(--primary-color)"><?= h($asset['client_company'] ?: $asset['client_name']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight:500"><?= $displayTypes ?: '<span class="muted">Sem tipo</span>' ?></div>
                            </td>
                            <td><span class="badge" style="background:rgba(0,0,0,0.05); color:var(--text-color); border:1px solid var(--border-color)"><?= h($asset['type_name']) ?></span></td>
                            <td><code style="font-size:12px; color:var(--muted-color)"><?= h($asset['ip_address'] ?: '-') ?></code></td>
                            <td style="text-align:right">
                                <div style="display:flex;gap:5px;justify-content:flex-end">
                                    <button class="btn btn-sm" onclick="editAsset(<?= $asset['id'] ?>)" title="Editar">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="btn btn-sm danger" onclick="deleteAsset(<?= $asset['id'] ?>, '<?= h($asset['name'] ?: $displayTypes) ?>')" title="Excluir">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for Add/Edit Asset -->
<div id="addAssetModal" class="modal">
    <div class="modal-content" style="max-width:800px">
        <div class="modal-header">
            <h2 id="modalTitle">Novo Ativo</h2>
            <button class="modal-close" onclick="closeModal('addAssetModal')">&times;</button>
        </div>
        <form id="assetForm" action="/api/assets.php" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="assetId" value="">
            
            <div class="tabs" style="margin-bottom:20px">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'tab-geral')">Geral</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-tecnico')">Técnico</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-rede')">Rede</button>
            </div>

            <div id="tab-geral" class="tab-content active">
                <div style="display:grid;grid-template-columns:1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">Cliente *</label>
                        <select name="client_user_id" class="input" required>
                            <option value="">Selecione o Cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= h($client['company_name'] ?: $client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="label" style="display:flex;justify-content:space-between;align-items:center;">
                            Tipos de Ativo *
                            <button type="button" class="btn-small" onclick="addTypeRow()" style="padding:2px 8px;font-size:11px">+ Adicionar Tipo</button>
                        </label>
                        <div id="asset-types-container" class="ip-list-container">
                            <!-- Dynamic type rows -->
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-tecnico" class="tab-content">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">vCPU Alocada</label>
                        <input type="text" name="cpu" class="input" placeholder="Ex: 4 vCPU">
                    </div>
                    <div class="form-group">
                        <label class="label">Memória RAM (GB)</label>
                        <input type="text" name="ram" class="input" placeholder="Ex: 8 GB">
                    </div>
                    <div class="form-group">
                        <label class="label">Armazenamento (Storage)</label>
                        <input type="text" name="storage" class="input" placeholder="Ex: 100 GB SSD">
                    </div>
                    <div class="form-group">
                        <label class="label">Total de Instâncias/VMs</label>
                        <input type="text" name="total_vms" class="input" placeholder="Ex: 10">
                    </div>
                    <div class="form-group">
                        <label class="label">Licenças Windows (vCPU)</label>
                        <input type="text" name="license_windows_vcpu" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Licenças VMware/vSphere</label>
                        <input type="text" name="license_vmware" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Licenças Outros Sistemas</label>
                        <input type="text" name="license_systems" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Banda Contratada (Link)</label>
                        <input type="text" name="link_limit" class="input" placeholder="Ex: 100 Mbps">
                    </div>
                    <div class="form-group">
                        <label class="label">Data de Início/Compra</label>
                        <input type="date" name="purchase_date" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Vencimento de Contrato/Garantia</label>
                        <input type="date" name="warranty_expiry" class="input">
                    </div>
                </div>
                <div class="form-group" style="margin-top:10px">
                    <label class="label">Notas de Faturamento / Observações de Cloud</label>
                    <textarea name="notes" class="input" style="height:60px"></textarea>
                </div>
            </div>

            <div id="tab-rede" class="tab-content">
                <div class="form-group">
                    <label class="label" style="display:flex;justify-content:space-between;align-items:center;">
                        IPs Externos
                        <button type="button" class="btn-small" onclick="addIpRow('external-ips-container', 'external_ips[]')" style="padding:2px 8px;font-size:11px">+ Adicionar</button>
                    </label>
                    <div id="external-ips-container" class="ip-list-container">
                        <!-- Dynamic rows will be added here -->
                    </div>
                </div>
                <div class="form-group">
                    <label class="label" style="display:flex;justify-content:space-between;align-items:center;">
                        IPs Internos / VLAN
                        <button type="button" class="btn-small" onclick="addIpRow('internal-ips-container', 'internal_ips[]')" style="padding:2px 8px;font-size:11px">+ Adicionar</button>
                    </label>
                    <div id="internal-ips-container" class="ip-list-container">
                        <!-- Dynamic rows will be added here -->
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">IP de Gerência (Principal)</label>
                        <input type="text" name="ip_address" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Máscara / Gateway</label>
                        <div style="display:flex;gap:5px">
                            <input type="text" name="subnet_mask" class="input" placeholder="Máscara">
                            <input type="text" name="gateway" class="input" placeholder="Gateway">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="label">VLAN ID / Nome</label>
                        <input type="text" name="vlan" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">MAC / Switch Port</label>
                        <div style="display:flex;gap:5px">
                            <input type="text" name="mac_address" class="input" placeholder="MAC">
                            <input type="text" name="switch_port" class="input" placeholder="Porta">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn" onclick="closeModal('addAssetModal')">Cancelar</button>
                <button type="submit" class="btn primary">Salvar Ativo</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); overflow-y:auto; }
.modal-content { background:var(--panel); margin:30px auto; padding:20px; border:1px solid var(--border); border-radius:8px; position:relative; max-width:800px; width:95%; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px; }
.modal-close { background:none; border:none; color:var(--text); font-size:24px; cursor:pointer; }
.tabs { display:flex; border-bottom:1px solid var(--border); gap:5px; margin-bottom:15px; }
.tab-btn { background:none; border:none; color:var(--muted); padding:8px 16px; cursor:pointer; border-bottom:2px solid transparent; transition:0.2s; font-weight:600; }
.tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
.tab-content { display:none; }
.tab-content.active { display:block; }
.form-group { margin-bottom:12px; }
.label { display:block; margin-bottom:5px; font-weight:600; font-size:13px; color:var(--muted); }
.input { width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:4px; background:var(--bg); color:var(--text); font-size:14px; }
.ip-list-container { display: flex; flex-direction: column; gap: 8px; margin-top: 5px; }
.ip-row { display: flex; gap: 8px; align-items: center; }
.btn-small { background: var(--border); color: var(--text); border: none; border-radius: 4px; cursor: pointer; transition: 0.2s; }
.btn-small:hover { background: var(--primary); color: white; }
.btn-remove { background: #ff4d4d22; color: #ff4d4d; border: 1px solid #ff4d4d44; padding: 6px 10px; border-radius: 4px; cursor: pointer; }
.btn-remove:hover { background: #ff4d4d; color: white; }
</style>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'block';
    if (id === 'addAssetModal') {
        document.getElementById('assetForm').reset();
        document.getElementById('external-ips-container').innerHTML = '';
        document.getElementById('internal-ips-container').innerHTML = '';
        document.getElementById('asset-types-container').innerHTML = '';
        addTypeRow(); // Add at least one row for new asset
        document.getElementById('modalTitle').textContent = 'Novo Ativo';
        document.getElementById('formAction').value = 'add';
        document.getElementById('assetId').value = '';
        switchTab(null, 'tab-geral');
    }
}

const availableTypes = <?= json_encode($assetTypes) ?>;

function addTypeRow(selectedId = '') {
    const container = document.getElementById('asset-types-container');
    const div = document.createElement('div');
    div.className = 'ip-row';
    
    let options = availableTypes.map(t => `<option value="${t.id}" ${t.id == selectedId ? 'selected' : ''}>${t.name}</option>`).join('');
    
    div.innerHTML = `
        <select name="type_ids[]" class="input" required>
            <option value="">Selecione o Tipo</option>
            ${options}
        </select>
        <button type="button" class="btn-remove" onclick="if(document.querySelectorAll('#asset-types-container .ip-row').length > 1) this.parentElement.remove()">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </button>
    `;
    container.appendChild(div);
}

function addIpRow(containerId, fieldName, value = '') {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'ip-row';
    div.innerHTML = `
        <input type="text" name="${fieldName}" class="input" value="${value}" placeholder="0.0.0.0">
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </button>
    `;
    container.appendChild(div);
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function switchTab(event, tabId) {
    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-btn');
    
    tabs.forEach(tab => tab.classList.remove('active'));
    buttons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    if (event) {
        event.currentTarget.classList.add('active');
    } else {
        document.querySelector(`.tab-btn[onclick*="${tabId}"]`).classList.add('active');
    }
}

function editAsset(id) {
    fetch(`/api/assets.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const asset = data.asset;
                openModal('addAssetModal');
                document.getElementById('modalTitle').textContent = 'Editar Ativo';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('assetId').value = asset.id;
                
                const form = document.getElementById('assetForm');
                document.getElementById('external-ips-container').innerHTML = '';
                document.getElementById('internal-ips-container').innerHTML = '';
                document.getElementById('asset-types-container').innerHTML = '';

                for (let key in asset) {
                    if (key === 'external_ips' && asset[key]) {
                        asset[key].split('\n').filter(ip => ip.trim()).forEach(ip => {
                            addIpRow('external-ips-container', 'external_ips[]', ip.trim());
                        });
                    } else if (key === 'internal_ips' && asset[key]) {
                        asset[key].split('\n').filter(ip => ip.trim()).forEach(ip => {
                            addIpRow('internal-ips-container', 'internal_ips[]', ip.trim());
                        });
                    } else if (key === 'type_ids' && asset[key]) {
                        asset[key].split(',').filter(id => id.trim()).forEach(id => {
                            addTypeRow(id.trim());
                        });
                    } else if (key === 'type_id' && asset[key] && !asset.type_ids) {
                        // Fallback for assets with only one type
                        addTypeRow(asset[key]);
                    } else {
                        const input = form.elements[key];
                        if (input) input.value = asset[key] || '';
                    }
                }
                if (document.querySelectorAll('#asset-types-container .ip-row').length === 0) {
                    addTypeRow();
                }
            } else {
                alert('Erro ao carregar dados do ativo.');
            }
        });
}

function deleteAsset(id, name) {
    if (confirm(`Tem certeza que deseja excluir o ativo "${name}"?`)) {
        fetch('/api/assets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir ativo: ' + (data.message || 'Erro desconhecido'));
            }
        });
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php render_footer(); ?>



