<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
$projectId = safe_int($_GET['id'] ?? null);

if (!$projectId) {
    redirect('/app/atendente_projetos.php');
}

$project = project_get_by_id($pdo, $projectId);
if (!$project) {
    redirect('/app/atendente_projetos.php');
}

// Handle updates via AJAX/POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_item') {
        $groupId = (int)$_POST['group_id'];
        $name = trim((string)($_POST['name'] ?? 'Nova Atividade'));
        project_item_create($pdo, $groupId, $name);
    } elseif ($action === 'update_item') {
        $itemId = (int)$_POST['item_id'];
        $field = (string)$_POST['field'];
        $value = $_POST['value'];
        project_item_update($pdo, $itemId, [$field => $value]);
    } elseif ($action === 'add_group') {
        $name = trim((string)($_POST['name'] ?? 'Novo Grupo'));
        project_group_create($pdo, $projectId, $name);
    } elseif ($action === 'delete_item') {
        $itemId = (int)$_POST['item_id'];
        project_item_delete($pdo, $itemId);
    } elseif ($action === 'archive_project') {
        project_archive($pdo, $projectId);
        redirect("/app/atendente_projetos.php");
    } elseif ($action === 'unarchive_project') {
        project_unarchive($pdo, $projectId);
        redirect("/app/atendente_projeto_view.php?id=$projectId");
    } elseif ($action === 'delete_project') {
        project_delete($pdo, $projectId);
        redirect("/app/atendente_projetos.php");
    }

    // Return for AJAX or redirect
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: atendente_projeto_view.php?id=$projectId");
    exit;
}

$structure = project_get_structure($pdo, $projectId);
$allUsers = attendant_list($pdo);

render_header('Gerenciar Projeto · ' . $project['name'], $user);
?>

<div class="project-view-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="/app/atendente_projetos.php" class="btn" style="padding: 4px 8px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <h1 style="margin:0;font-size:1.5rem;font-weight:700;"><?= h((string)$project['name']) ?></h1>
    </div>
    <div style="display:flex;gap:8px">
        <?php if ($project['status'] === 'active'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Deseja realmente ARQUIVAR este projeto?')">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="archive_project">
                <button type="submit" class="btn" style="border-color: #f59e0b; color: #f59e0b;">Arquivar</button>
            </form>
        <?php else: ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="unarchive_project">
                <button type="submit" class="btn" style="border-color: #10b981; color: #10b981;">Desarquivar</button>
            </form>
        <?php endif; ?>

        <form method="post" style="display:inline" onsubmit="return confirm('Deseja realmente EXCLUIR este projeto permanentemente?')">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_project">
            <button type="submit" class="btn" style="border-color: #ef4444; color: #ef4444;">Excluir</button>
        </form>

        <button class="btn primary" onclick="addGroup()">Novo Grupo</button>
    </div>
</div>

<div class="monday-board">
    <?php foreach ($structure as $group): ?>
        <div class="monday-group" data-group-id="<?= (int)$group['id'] ?>">
            <div class="monday-group-header" style="border-left: 6px solid <?= h($group['color']) ?>">
                <span class="monday-group-title" style="color: <?= h($group['color']) ?>"><?= h((string)$group['name']) ?></span>
                <button class="monday-add-item-btn" onclick="addItem(<?= (int)$group['id'] ?>)">+</button>
            </div>
            
            <table class="monday-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Atividade</th>
                        <th style="width: 180px;">Proprietário</th>
                        <th style="width: 160px;">Status</th>
                        <th style="width: 160px;">Progresso</th>
                        <th style="width: 120px;">Custos</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['items'] as $item): ?>
                        <tr data-item-id="<?= (int)$item['id'] ?>">
                            <td style="border-left: 6px solid <?= h((string)$group['color']) ?>;"></td>
                            <td class="editable" data-field="name" contenteditable="true"><?= h((string)$item['name']) ?></td>
                            <td>
                                <select class="monday-select owner-select" data-field="owner_user_id">
                                    <option value="">Ninguém</option>
                                    <?php foreach ($allUsers as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= ((int)$item['owner_user_id'] === (int)$u['id']) ? 'selected' : '' ?>>
                                            <?= h((string)$u['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php
                                $sMap = [
                                    'Working on it' => 'Em andamento',
                                    'Done' => 'Concluído',
                                    'Stuck' => 'Travado',
                                    'Waiting' => 'Aguardando'
                                ];
                                $curStatus = (string)$item['status'];
                                $displayStatus = $sMap[$curStatus] ?? $curStatus;
                                
                                // CSS safe class (no accents)
                                $sClassMap = [
                                    'Em andamento' => 'em-andamento',
                                    'Concluído' => 'concluido',
                                    'Travado' => 'travado',
                                    'Aguardando' => 'aguardando',
                                    'Working on it' => 'em-andamento',
                                    'Done' => 'concluido',
                                    'Stuck' => 'travado',
                                    'Waiting' => 'aguardando'
                                ];
                                $sClass = $sClassMap[$curStatus] ?? 'aguardando';
                                ?>
                                <div class="status-cell status-<?= h($sClass) ?>" onclick="toggleStatus(this)">
                                    <?= h($displayStatus) ?>
                                </div>
                            </td>
                            <td>
                                <div class="baseline-container">
                                    <div class="baseline-bar" style="width: <?= (int)$item['baseline'] ?>%"></div>
                                    <input type="range" class="baseline-slider" min="0" max="100" value="<?= (int)$item['baseline'] ?>" onchange="updateProgress(<?= (int)$item['id'] ?>, this.value)">
                                </div>
                            </td>
                            <td class="editable" data-field="expenses" contenteditable="true">R$ <?= number_format((float)$item['expenses'], 2, ',', '.') ?></td>
                            <td>
                                <button class="delete-item-btn" onclick="deleteItem(<?= (int)$item['id'] ?>)">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="add-row">
                        <td style="border-left: 6px solid <?= h($group['color']) ?>;"></td>
                        <td colspan="6" onclick="addItem(<?= (int)$group['id'] ?>)" style="cursor:pointer;color:var(--text-muted);font-style:italic;">
                            + Adicionar atividade
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<style>
.project-view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.monday-board {
    display: flex;
    flex-direction: column;
    gap: 32px;
}
.monday-group {
    margin-bottom: 20px;
}
.monday-group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    margin-bottom: 8px;
}
.monday-group-title {
    font-weight: 700;
    font-size: 1.2rem;
}
.monday-add-item-btn {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}
.monday-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--panel);
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.monday-table th {
    text-align: left;
    padding: 10px 12px;
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 500;
    border-bottom: 1px solid var(--border);
}
.monday-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
}
.monday-table tr:hover {
    background: rgba(255,255,255,0.02);
}
.editable:focus {
    outline: 2px solid var(--primary);
    background: rgba(255,255,255,0.05);
}
.monday-select {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text);
    padding: 4px;
    width: 100%;
    border-radius: 4px;
}
.monday-select:hover {
    border-color: var(--border);
}
.status-cell {
    padding: 6px;
    border-radius: 4px;
    text-align: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    text-transform: capitalize;
}
.status-concluido { background-color: #00c875; }
.status-em-andamento { background-color: #fdab3d; }
.status-travado { background-color: #e2445c; }
.status-aguardando { background-color: #797e93; }

.baseline-container {
    height: 10px;
    background: #e1e1e1;
    border-radius: 5px;
    position: relative;
    overflow: hidden;
}
.baseline-bar {
    height: 100%;
    background: #a25ddc;
    transition: width 0.3s;
}
.baseline-slider {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    opacity: 0;
    cursor: pointer;
}
.delete-item-btn {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0;
}
tr:hover .delete-item-btn { opacity: 1; }
</style>

<script>
const csrfToken = '<?= h(csrf_token()) ?>';

function updateField(itemId, field, value) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'update_item');
    formData.append('item_id', itemId);
    formData.append('field', field);
    formData.append('value', value);

    fetch('atendente_projeto_view.php?id=<?= $projectId ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    });
}

document.querySelectorAll('.editable').forEach(el => {
    el.addEventListener('blur', function() {
        const itemId = this.closest('tr').dataset.itemId;
        const field = this.dataset.field;
        let value = this.textContent.trim();
        
        if (field === 'expenses') {
            value = value.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
        }
        
        updateField(itemId, field, value);
    });
});

document.querySelectorAll('.owner-select').forEach(el => {
    el.addEventListener('change', function() {
        const itemId = this.closest('tr').dataset.itemId;
        updateField(itemId, 'owner_user_id', this.value);
    });
});

function toggleStatus(el) {
    const statuses = [
        { display: 'Em andamento', class: 'em-andamento' },
        { display: 'Concluído', class: 'concluido' },
        { display: 'Travado', class: 'travado' },
        { display: 'Aguardando', class: 'aguardando' }
    ];
    
    const current = el.textContent.trim();
    let currentIndex = statuses.findIndex(s => s.display === current);
    let nextIndex = (currentIndex + 1) % statuses.length;
    const next = statuses[nextIndex];
    
    el.textContent = next.display;
    el.className = 'status-cell status-' + next.class;
    
    const itemId = el.closest('tr').dataset.itemId;
    // We save the display name (Portuguese) to the DB for consistency
    updateField(itemId, 'status', next.display);
}

function updateProgress(itemId, value) {
    const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
    row.querySelector('.baseline-bar').style.width = value + '%';
    updateField(itemId, 'baseline', value);
}

function addItem(groupId) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'add_item');
    formData.append('group_id', groupId);
    
    fetch('atendente_projeto_view.php?id=<?= $projectId ?>', {
        method: 'POST',
        body: formData
    }).then(() => location.reload());
}

function addGroup() {
    const name = prompt('Nome do grupo:');
    if (!name) return;
    
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'add_group');
    formData.append('name', name);
    
    fetch('atendente_projeto_view.php?id=<?= $projectId ?>', {
        method: 'POST',
        body: formData
    }).then(() => location.reload());
}

function deleteItem(itemId) {
    if (!confirm('Tem certeza que deseja excluir esta atividade?')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'delete_item');
    formData.append('item_id', itemId);
    
    fetch('atendente_projeto_view.php?id=<?= $projectId ?>', {
        method: 'POST',
        body: formData
    }).then(() => location.reload());
}
</script>

<?php render_footer(); ?>



