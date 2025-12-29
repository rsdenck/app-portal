<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */
require_login();

if (current_user()['role'] !== 'cliente') {
    redirect('/app/atendente_gestao.php');
}

$sessionUser = current_user();
$clientId = (int)$sessionUser['id'];

// Get asset types
$stmt = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC");
$assetTypes = $stmt->fetchAll();

// Handle search/filters
$search = $_GET['q'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$sql = "SELECT a.*, t.name as type_name 
        FROM assets a 
        LEFT JOIN asset_types t ON a.type_id = t.id 
        WHERE a.client_user_id = :client_id";
$params = [':client_id' => $clientId];

if ($search) {
    $sql .= " AND (a.name LIKE :search OR a.notes LIKE :search OR a.ip_address LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($typeFilter) {
    $sql .= " AND (a.type_id = :type_id OR FIND_IN_SET(:type_id, a.type_ids))";
    $params[':type_id'] = $typeFilter;
}

$sql .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

render_header('Meus Ativos e Recursos', $sessionUser);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px">
        <form method="GET" style="display:flex;gap:10px;flex:1;min-width:300px;flex-wrap:wrap">
            <input type="text" name="q" class="input" placeholder="Buscar ativos..." value="<?= h($search) ?>" style="width:250px">
            <select name="type" class="input" style="width:180px">
                <option value="">Todos os Tipos</option>
                <?php foreach ($assetTypes as $type): ?>
                    <option value="<?= $type['id'] ?>" <?= $typeFilter == $type['id'] ? 'selected' : '' ?>><?= h($type['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn primary">Filtrar</button>
            <?php if ($search || $typeFilter): ?>
                <a href="/client/cliente_ativos.php" class="btn">Limpar</a>
            <?php endif; ?>
        </form>
        <button class="btn primary" onclick="openModal('assetModal')">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Solicitar/Adicionar Recurso
        </button>
    </div>

    <?php if (empty($assets)): ?>
        <div style="text-align:center;padding:60px 20px;background:var(--bg);border:1px dashed var(--border);border-radius:8px">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--muted)" stroke-width="1" style="margin-bottom:15px"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <h3 style="margin-bottom:10px">Nenhum ativo encontrado</h3>
            <p class="muted">Você ainda não possui ativos registrados ou recursos alocados.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ativo / Recurso</th>
                        <th style="width:200px">IP Principal</th>
                        <th style="width:150px">Status</th>
                        <th style="width:100px; text-align:right">Visualizar</th>
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
                                <div style="font-weight:600; color:var(--primary-color)"><?= $displayTypes ?: 'Recurso' ?></div>
                                <?php if ($asset['created_by_role'] === 'atendente'): ?>
                                    <span style="font-size:10px; color:var(--success-color); font-weight:bold">[ VALIDADO PELA ENGENHARIA ]</span>
                                <?php else: ?>
                                    <span style="font-size:10px; color:var(--warning-color); font-weight:bold">[ AGUARDANDO VALIDAÇÃO ]</span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size:12px; color:var(--muted-color)"><?= h($asset['ip_address'] ?: '-') ?></code></td>
                            <td>
                                <?php if ($asset['created_by_role'] === 'atendente'): ?>
                                    <span class="badge" style="background:rgba(46, 204, 113, 0.1); color:#2ecc71; border:1px solid rgba(46, 204, 113, 0.2)">Ativo / Billing</span>
                                <?php else: ?>
                                    <span class="badge" style="background:rgba(241, 196, 15, 0.1); color:#f1c40f; border:1px solid rgba(241, 196, 15, 0.2)">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <button class="btn btn-sm" onclick="viewAsset(<?= $asset['id'] ?>)" title="Visualizar Detalhes">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for View/Add Asset -->
<div id="assetModal" class="modal">
    <div class="modal-content" style="max-width:800px">
        <div class="modal-header">
            <h2 id="modalTitle">Detalhes do Ativo</h2>
            <button class="modal-close" onclick="closeModal('assetModal')">&times;</button>
        </div>
        <form id="assetForm" action="/api/assets.php" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="assetId" value="">
            
            <div class="tabs" style="margin-bottom:20px">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'tab-geral')">Geral</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-tecnico')">Recursos</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-rede')">Rede / IPs</button>
            </div>

            <div id="tab-geral" class="tab-content active">
                <div style="display:grid;grid-template-columns:1fr;gap:15px">
                    <div class="form-group">
                        <label class="label" style="display:flex;justify-content:space-between;align-items:center;">
                            Tipos de Ativo
                            <button type="button" class="btn-small add-btn" onclick="addTypeRow()" style="padding:2px 8px;font-size:11px">+ Adicionar</button>
                        </label>
                        <div id="asset-types-container" class="ip-list-container">
                            <!-- Dynamic type rows -->
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                        <div class="form-group">
                            <label class="label">Qual Recurso (Obrigatório) <span style="color:red">*</span></label>
                            <input type="text" name="resource_name" id="resource_name" class="input" placeholder="Ex: Servidor Web, Banco de Dados" required>
                        </div>
                        <div class="form-group">
                            <label class="label">Onde Alocar (Obrigatório) <span style="color:red">*</span></label>
                            <input type="text" name="allocation_place" id="allocation_place" class="input" placeholder="Ex: VDC-PRODUCAO, Rack 02" required>
                        </div>
                    </div>
                    <input type="hidden" name="name" id="asset_name_hidden">
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
                </div>
                <div class="form-group" style="margin-top:10px">
                    <label class="label">Observações / Solicitações Especiais</label>
                    <textarea name="notes" class="input" style="height:60px"></textarea>
                </div>
            </div>

            <div id="tab-rede" class="tab-content">
                <div class="form-group">
                    <label class="label" style="display:flex;justify-content:space-between;align-items:center;">
                        IPs Externos
                        <button type="button" class="btn-small add-btn" onclick="addIpRow('external-ips-container', 'external_ips[]')" style="padding:2px 8px;font-size:11px">+ Adicionar</button>
                    </label>
                    <div id="external-ips-container" class="ip-list-container"></div>
                </div>
                <div class="form-group">
                    <label class="label" style="display:flex;justify-content:space-between;align-items:center;">
                        IPs Internos / VLAN
                        <button type="button" class="btn-small add-btn" onclick="addIpRow('internal-ips-container', 'internal_ips[]')" style="padding:2px 8px;font-size:11px">+ Adicionar</button>
                    </label>
                    <div id="internal-ips-container" class="ip-list-container"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">IP de Gerência (Principal)</label>
                        <input type="text" name="ip_address" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">VLAN ID / Nome</label>
                        <input type="text" name="vlan" class="input">
                    </div>
                </div>
            </div>

            <div class="modal-footer" id="modalFooter" style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn" onclick="closeModal('assetModal')">Fechar</button>
                <button type="submit" id="saveBtn" class="btn primary">Salvar Solicitação</button>
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
.input:disabled { opacity: 0.7; cursor: not-allowed; background: var(--border); }
.ip-list-container { display: flex; flex-direction: column; gap: 8px; margin-top: 5px; }
.ip-row { display: flex; gap: 8px; align-items: center; }
.btn-small { background: var(--border); color: var(--text); border: none; border-radius: 4px; cursor: pointer; transition: 0.2s; }
.btn-small:hover { background: var(--primary); color: white; }
.btn-remove { background: #ff4d4d22; color: #ff4d4d; border: 1px solid #ff4d4d44; padding: 6px 10px; border-radius: 4px; cursor: pointer; }
.btn-remove:hover { background: #ff4d4d; color: white; }
</style>

<script>
const availableTypes = <?= json_encode($assetTypes) ?>;

function openModal(id) {
    console.log('Opening modal:', id);
    const modal = document.getElementById(id);
    if (!modal) {
        console.error('Modal not found:', id);
        return;
    }
    modal.style.display = 'block';
    if (id === 'assetModal') {
        document.getElementById('assetForm').reset();
        document.getElementById('external-ips-container').innerHTML = '';
        document.getElementById('internal-ips-container').innerHTML = '';
        document.getElementById('asset-types-container').innerHTML = '';
        
        // Enable everything for ADD
        enableForm(true);
        document.getElementById('modalTitle').textContent = 'Solicitar Novo Recurso';
        document.getElementById('formAction').value = 'add';
        document.getElementById('saveBtn').style.display = 'block';
        
        addTypeRow();
        switchTab(null, 'tab-geral');
    }
}

// Sync resource_name to hidden name field
document.getElementById('resource_name').addEventListener('input', function() {
    document.getElementById('asset_name_hidden').value = this.value;
});

function viewAsset(id) {
    console.log('Viewing asset:', id);
    fetch(`/api/assets.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            console.log('Asset data received:', data);
            if (data.success && data.asset) {
                const asset = data.asset;
                document.getElementById('assetModal').style.display = 'block';
                const form = document.getElementById('assetForm');
                form.reset();
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
                        addTypeRow(asset[key]);
                    } else {
                        const input = form.elements[key];
                        if (input) {
                            input.value = asset[key] || '';
                        } else {
                            // Try by ID if name doesn't work
                            const elById = document.getElementById(key);
                            if (elById) elById.value = asset[key] || '';
                        }
                    }
                }
                
                // Read-only mode
                enableForm(false);
                document.getElementById('modalTitle').textContent = 'Visualizar Ativo';
                document.getElementById('saveBtn').style.display = 'none';
                switchTab(null, 'tab-geral');
            } else {
                alert('Erro ao carregar detalhes do ativo: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Erro de rede ao carregar detalhes do ativo.');
        });
}

function enableForm(enabled) {
    const form = document.getElementById('assetForm');
    const elements = form.querySelectorAll('input, select, textarea');
    elements.forEach(el => el.disabled = !enabled);
    
    const removeBtns = form.querySelectorAll('.btn-remove');
    removeBtns.forEach(btn => btn.style.display = enabled ? 'block' : 'none');
    
    const addBtns = form.querySelectorAll('.add-btn');
    addBtns.forEach(btn => btn.style.display = enabled ? 'block' : 'none');
}

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
    if (event) event.currentTarget.classList.add('active');
}
</script>
<?php render_footer(); ?>
