<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if (current_user()['role'] !== 'cliente') {
    redirect('/app/atendente_gestao.php');
}

$sessionUser = current_user();
$clientId = (int)$sessionUser['id'];

// Get asset types for filters and forms
$stmt = $pdo->query("SELECT * FROM asset_types ORDER BY name ASC");
$assetTypes = $stmt->fetchAll();

// Handle search/filters
$search = $_GET['q'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$sql = "SELECT a.*, t.name as type_name 
        FROM assets a 
        JOIN asset_types t ON a.type_id = t.id 
        WHERE a.client_user_id = :client_id";
$params = [':client_id' => $clientId];

if ($search) {
    $sql .= " AND (a.name LIKE :search OR a.serial_number LIKE :search OR a.manufacturer LIKE :search OR a.model LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($typeFilter) {
    $sql .= " AND a.type_id = :type_id";
    $params[':type_id'] = $typeFilter;
}

$sql .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

render_header('Inventário de Ativos', $sessionUser);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px">
        <form method="GET" style="display:flex;gap:10px;flex:1;min-width:300px">
            <input type="text" name="q" class="input" placeholder="Buscar por nome, serial, fabricante..." value="<?= h($search) ?>" style="flex:1">
            <select name="type" class="input" style="width:150px">
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
        <button class="btn primary" onclick="openModal('addAssetModal')">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Ativo
        </button>
    </div>

    <?php if (empty($assets)): ?>
        <div style="text-align:center;padding:60px 20px;background:var(--bg);border:1px dashed var(--border);border-radius:8px">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="var(--muted)" stroke-width="1" style="margin-bottom:15px"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <h3 style="margin-bottom:10px">Nenhum ativo encontrado</h3>
            <p class="muted" style="margin-bottom:20px">Comece adicionando seu primeiro ativo ao inventário.</p>
            <button class="btn primary" onclick="openModal('addAssetModal')">Adicionar Ativo</button>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Fabricante/Modelo</th>
                        <th>Nº de Série</th>
                        <th>IP</th>
                        <th>Localização</th>
                        <th style="text-align:right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td>
                                <strong><?= h($asset['name']) ?></strong>
                            </td>
                            <td><span class="badge"><?= h($asset['type_name']) ?></span></td>
                            <td><?= h($asset['manufacturer']) ?> <?= h($asset['model']) ?></td>
                            <td><code style="font-size:12px"><?= h($asset['serial_number'] ?: '-') ?></code></td>
                            <td><?= h($asset['ip_address'] ?: '-') ?></td>
                            <td><?= h($asset['location'] ?: '-') ?></td>
                            <td style="text-align:right">
                                <div style="display:flex;gap:5px;justify-content:flex-end">
                                    <button class="btn btn-sm" onclick="editAsset(<?= $asset['id'] ?>)" title="Editar">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="btn btn-sm danger" onclick="deleteAsset(<?= $asset['id'] ?>, '<?= h($asset['name']) ?>')" title="Excluir">
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
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">Nome do Ativo *</label>
                        <input type="text" name="name" class="input" required placeholder="Ex: Notebook CEO">
                    </div>
                    <div class="form-group">
                        <label class="label">Tipo de Ativo *</label>
                        <select name="type_id" class="input" required>
                            <?php foreach ($assetTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= h($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="label">Fabricante</label>
                        <input type="text" name="manufacturer" class="input" placeholder="Ex: Dell, HP, Cisco">
                    </div>
                    <div class="form-group">
                        <label class="label">Modelo</label>
                        <input type="text" name="model" class="input" placeholder="Ex: Latitude 3420">
                    </div>
                    <div class="form-group">
                        <label class="label">Número de Série</label>
                        <input type="text" name="serial_number" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Pessoa Responsável</label>
                        <input type="text" name="responsible_person" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Localização</label>
                        <input type="text" name="location" class="input" placeholder="Ex: Escritório Central, Sala 10">
                    </div>
                    <div class="form-group">
                        <label class="label">Data de Compra</label>
                        <input type="date" name="purchase_date" class="input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="label">Notas / Observações</label>
                    <textarea name="notes" class="input" style="height:80px"></textarea>
                </div>
            </div>

            <div id="tab-tecnico" class="tab-content">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">Processador (CPU)</label>
                        <input type="text" name="cpu" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Memória RAM</label>
                        <input type="text" name="ram" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Armazenamento</label>
                        <input type="text" name="storage" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Sistema Operacional</label>
                        <input type="text" name="os_name" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Versão do SO</label>
                        <input type="text" name="os_version" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Vencimento da Garantia</label>
                        <input type="date" name="warranty_expiry" class="input">
                    </div>
                </div>
            </div>

            <div id="tab-rede" class="tab-content">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label class="label">Endereço IP</label>
                        <input type="text" name="ip_address" class="input" placeholder="0.0.0.0">
                    </div>
                    <div class="form-group">
                        <label class="label">Máscara de Sub-rede</label>
                        <input type="text" name="subnet_mask" class="input" placeholder="255.255.255.0">
                    </div>
                    <div class="form-group">
                        <label class="label">Gateway</label>
                        <input type="text" name="gateway" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">Endereço MAC</label>
                        <input type="text" name="mac_address" class="input" placeholder="00:00:00:00:00:00">
                    </div>
                    <div class="form-group">
                        <label class="label">Porta do Switch</label>
                        <input type="text" name="switch_port" class="input">
                    </div>
                    <div class="form-group">
                        <label class="label">VLAN</label>
                        <input type="text" name="vlan" class="input">
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
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); }
.modal-content { background:var(--panel); margin:50px auto; padding:20px; border:1px solid var(--border); border-radius:8px; position:relative; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:10px; }
.modal-close { background:none; border:none; color:var(--text); font-size:24px; cursor:pointer; }
.tabs { display:flex; border-bottom:1px solid var(--border); gap:5px; }
.tab-btn { background:none; border:none; color:var(--muted); padding:10px 20px; cursor:pointer; border-bottom:2px solid transparent; transition:0.2s; }
.tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
.tab-content { display:none; }
.tab-content.active { display:block; }
.form-group { margin-bottom:15px; }
.badge { background:var(--border); color:var(--text); padding:2px 8px; border-radius:4px; font-size:12px; }
</style>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'block';
    if (id === 'addAssetModal') {
        document.getElementById('assetForm').reset();
        document.getElementById('modalTitle').textContent = 'Novo Ativo';
        document.getElementById('formAction').value = 'add';
        document.getElementById('assetId').value = '';
        switchTab(null, 'tab-geral');
    }
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
                for (let key in asset) {
                    const input = form.elements[key];
                    if (input) input.value = asset[key] || '';
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

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php render_footer(); ?>



