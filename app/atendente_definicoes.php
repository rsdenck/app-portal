<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');
$success = '';
$error = '';

$type = (string)($_GET['type'] ?? 'tickets');
$action = (string)($_GET['action'] ?? 'list');
$id = safe_int($_GET['id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $postAction = (string)($_POST['action'] ?? '');
    $postType = (string)($_POST['type'] ?? 'tickets');

    if ($postAction === 'save') {
        $id = safe_int($_POST['id'] ?? null);
        $name = trim((string)($_POST['name'] ?? ''));

        if ($postType === 'tickets') {
            $slug = trim((string)($_POST['slug'] ?? ''));
            $schemaJson = trim((string)($_POST['schema_json'] ?? '[]'));
            
            if ($name === '') {
                $error = 'O nome da categoria é obrigatório.';
            } else {
                json_decode($schemaJson);
                if (json_last_error() !== JSON_ERROR_NONE) $schemaJson = '[]';
                if ($slug === '') $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

                if ($id) {
                    $stmt = $pdo->prepare('UPDATE ticket_categories SET name = ?, slug = ?, schema_json = ? WHERE id = ?');
                    $stmt->execute([$name, $slug, $schemaJson, $id]);
                    $success = 'Categoria de chamado atualizada.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO ticket_categories (name, slug, schema_json) VALUES (?, ?, ?)');
                    $stmt->execute([$name, $slug, $schemaJson]);
                    $success = 'Categoria de chamado criada.';
                }
            }
        } elseif ($postType === 'docs') {
            $parentId = safe_int($_POST['parent_id'] ?? null);
            if ($name === '') {
                $error = 'O nome da categoria/subcategoria é obrigatório.';
            } else {
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE doc_categories SET name = ?, parent_id = ? WHERE id = ?');
                    $stmt->execute([$name, $parentId, $id]);
                    $success = 'Tag de documentação atualizada.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO doc_categories (name, parent_id) VALUES (?, ?)');
                    $stmt->execute([$name, $parentId]);
                    $success = 'Tag de documentação criada.';
                }
            }
        } elseif ($postType === 'advanced') {
            // Asset Types management within advanced
            if ($name === '') {
                $error = 'O nome do tipo de ativo é obrigatório.';
            } else {
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE asset_types SET name = ? WHERE id = ?');
                    $stmt->execute([$name, $id]);
                    $success = 'Tipo de ativo atualizado.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO asset_types (name) VALUES (?)');
                    $stmt->execute([$name]);
                    $success = 'Tipo de ativo criado.';
                }
            }
        }
        if (!$error) $action = 'list';
    } elseif ($postAction === 'delete') {
        $id = safe_int($_POST['id'] ?? null);
        if ($id) {
            if ($postType === 'tickets') {
                $stmt = $pdo->prepare('DELETE FROM ticket_categories WHERE id = ?');
                $stmt->execute([$id]);
                $success = 'Categoria de chamado excluída.';
            } elseif ($postType === 'docs') {
                $stmt = $pdo->prepare('DELETE FROM doc_categories WHERE id = ?');
                $stmt->execute([$id]);
                $success = 'Tag de documentação excluída.';
            } elseif ($postType === 'advanced') {
                $stmt = $pdo->prepare('DELETE FROM asset_types WHERE id = ?');
                $stmt->execute([$id]);
                $success = 'Tipo de ativo excluído.';
            }
        }
        $action = 'list';
    }
}

$ticketCategories = ticket_categories($pdo);
$docCategories = doc_categories_list($pdo); // Flat list from repository helper
// Fetch parent categories for docs (those with parent_id IS NULL)
$docParents = $pdo->query('SELECT * FROM doc_categories WHERE parent_id IS NULL ORDER BY name')->fetchAll();
$assetTypesList = $pdo->query('SELECT * FROM asset_types ORDER BY name')->fetchAll();

$editingItem = null;
if ($action === 'edit' && $id) {
    if ($type === 'tickets') {
        $stmt = $pdo->prepare('SELECT * FROM ticket_categories WHERE id = ?');
        $stmt->execute([$id]);
        $editingItem = $stmt->fetch();
    } elseif ($type === 'docs') {
        $stmt = $pdo->prepare('SELECT * FROM doc_categories WHERE id = ?');
        $stmt->execute([$id]);
        $editingItem = $stmt->fetch();
    } elseif ($type === 'advanced') {
        $stmt = $pdo->prepare('SELECT * FROM asset_types WHERE id = ?');
        $stmt->execute([$id]);
        $editingItem = $stmt->fetch();
    }
}

render_header('Atendente · Definições', $user);
?>

<div class="card" style="margin-bottom:18px">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <div>
            <div style="font-weight:700; font-size:1.2rem">Definições do Sistema</div>
            <div class="muted">Gerencie as configurações avançadas de todos os módulos.</div>
        </div>
        <a href="/app/atendente_config.php" class="btn">Voltar</a>
    </div>

    <!-- Abas de Navegação -->
    <div class="tabs" style="display:flex; gap:10px; border-bottom:1px solid var(--border-color); margin-bottom:25px; padding-bottom:10px">
        <a href="?type=tickets" class="tab-btn <?= $type === 'tickets' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            Configurações de Chamados
        </a>
        <a href="?type=docs" class="tab-btn <?= $type === 'docs' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Tags de Documentação
        </a>
        <a href="?type=advanced" class="tab-btn <?= $type === 'advanced' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px; vertical-align:middle"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            Configurações Avançadas
        </a>
    </div>

    <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <?php if ($type === 'tickets'): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; background:rgba(0,0,0,0.02); padding:15px; border-radius:8px">
            <div>
                <h3 style="margin:0">Categorias de Chamados</h3>
                <div class="muted" style="font-size:0.85rem">Defina os tipos de atendimentos e seus formulários dinâmicos.</div>
            </div>
            <?php if ($action === 'list'): ?>
                <a href="?type=tickets&action=create" class="btn primary small">Nova Categoria</a>
            <?php else: ?>
                <a href="?type=tickets" class="btn small">Voltar para Lista</a>
            <?php endif; ?>
        </div>

        <?php if ($action === 'list'): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nome da Categoria</th>
                            <th>Slug / Identificador</th>
                            <th style="text-align:right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketCategories as $c): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px">
                                        <div style="width:32px; height:32px; background:var(--primary); color:white; border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.8rem">
                                            <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                        </div>
                                        <strong><?= h($c['name']) ?></strong>
                                    </div>
                                </td>
                                <td><code style="background:rgba(0,0,0,0.05); padding:2px 6px; border-radius:4px"><?= h($c['slug']) ?></code></td>
                                <td style="text-align:right">
                                    <div style="display:flex; gap:8px; justify-content:flex-end">
                                        <a href="?type=tickets&action=edit&id=<?= (int)$c['id'] ?>" class="btn small" title="Editar">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                        <form method="post" onsubmit="return confirm('Excluir esta categoria?')" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="type" value="tickets">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn small danger" title="Excluir">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <form method="post" class="config-form-box">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="type" value="tickets">
                <?php if ($editingItem): ?><input type="hidden" name="id" value="<?= (int)$editingItem['id'] ?>"><?php endif; ?>

                <div class="row">
                    <div class="col">
                        <label>Nome da Categoria</label>
                        <input name="name" value="<?= h($editingItem['name'] ?? '') ?>" required placeholder="Ex: Suporte Técnico, Infraestrutura...">
                    </div>
                    <div class="col">
                        <label>Slug / Identificador (Opcional)</label>
                        <input name="slug" value="<?= h($editingItem['slug'] ?? '') ?>" placeholder="suporte-tecnico">
                        <div class="muted" style="font-size:0.75rem; margin-top:4px">Deixe em branco para gerar automaticamente.</div>
                    </div>
                </div>
                <div style="margin-top:20px">
                    <label style="display:block; margin-bottom:8px">Schema JSON (Campos Dinâmicos)</label>
                    <textarea name="schema_json" style="height:150px; font-family:'Fira Code', 'Consolas', monospace; font-size:0.85rem; background:#1e1e1e; color:#d4d4d4; border-radius:4px; padding:12px"><?= h($editingItem['schema_json'] ?? '[]') ?></textarea>
                    <div class="muted" style="font-size:0.75rem; margin-top:5px">Defina campos extras para esta categoria usando o formato JSON de formulários.</div>
                </div>
                <div style="margin-top:25px; border-top:1px solid var(--border-color); padding-top:20px; display:flex; gap:10px">
                    <button type="submit" class="btn primary">Salvar Categoria</button>
                    <a href="?type=tickets" class="btn">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>

    <?php elseif ($type === 'docs'): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; background:rgba(0,0,0,0.02); padding:15px; border-radius:8px">
            <div>
                <h3 style="margin:0">Hierarquia de Documentação</h3>
                <div class="muted" style="font-size:0.85rem">Organize as tags e categorias para facilitar a busca de manuais.</div>
            </div>
            <?php if ($action === 'list'): ?>
                <div style="display:flex; gap:10px">
                    <a href="?type=docs&action=create" class="btn primary small">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px; vertical-align:middle"><path d="M12 5v14M5 12h14"></path></svg>
                        Nova Categoria Pai
                    </a>
                </div>
            <?php else: ?>
                <a href="?type=docs" class="btn small">Voltar para Lista</a>
            <?php endif; ?>
        </div>

        <?php if ($action === 'list'): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:250px">Categoria Pai</th>
                            <th>Tag / Subcategoria</th>
                            <th style="text-align:right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $docsList = $pdo->query("
                            SELECT c1.id, c1.name as subname, c2.name as parentname, c1.parent_id
                            FROM doc_categories c1
                            LEFT JOIN doc_categories c2 ON c1.parent_id = c2.id
                            ORDER BY COALESCE(c2.name, c1.name), c2.name IS NOT NULL, c1.name
                        ")->fetchAll();

                        foreach ($docsList as $d): ?>
                            <tr style="<?= !$d['parent_id'] ? 'background:rgba(39, 196, 168, 0.03)' : '' ?>">
                                <td>
                                    <?php if ($d['parent_id']): ?>
                                        <div style="display:flex; align-items:center; gap:8px; padding-left:15px">
                                            <div style="width:6px; height:6px; border-radius:50%; background:var(--primary); opacity:0.5"></div>
                                            <span style="font-weight:500; color:var(--text-muted); font-size:0.85rem"><?= h($d['parentname']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div style="display:flex; align-items:center; gap:8px">
                                            <div style="width:10px; height:10px; border-radius:3px; background:var(--primary)"></div>
                                            <span style="font-weight:700; color:var(--text); font-size:0.95rem">CATEGORIA PAI</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="<?= !$d['parent_id'] ? 'font-weight:bold; color:var(--primary); font-size:1rem' : 'padding-left:25px; color:var(--text);' ?>">
                                        <?= $d['parent_id'] ? '<span style="color:var(--border); margin-right:8px">└─</span>' : '' ?> <?= h($d['subname']) ?>
                                    </div>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center">
                                        <?php if (!$d['parent_id']): ?>
                                            <a href="?type=docs&action=create&parent_id=<?= (int)$d['id'] ?>" class="btn small primary" title="Adicionar Subtag" style="padding:2px 8px !important; font-size:0.7rem !important; background:#27c4a8; border-color:#27c4a8; color:white">
                                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:3px; vertical-align:middle"><path d="M12 5v14M5 12h14"></path></svg>
                                                SUBTAG
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?type=docs&action=edit&id=<?= (int)$d['id'] ?>" class="btn small" title="Editar">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                        <form method="post" onsubmit="return confirm('Excluir este item? Subcategorias serão excluídas se esta for uma categoria pai.')" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="type" value="docs">
                                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                            <button type="submit" class="btn small danger" title="Excluir" style="background:transparent; border:none; cursor:pointer">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--danger)" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <form method="post" class="config-form-box">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="type" value="docs">
                <?php if ($editingItem): ?><input type="hidden" name="id" value="<?= (int)$editingItem['id'] ?>"><?php endif; ?>

                <div class="row">
                    <div class="col">
                        <label><?= ($editingItem && $editingItem['parent_id']) || isset($_GET['parent_id']) ? 'Nome da Tag (Subcategoria)' : 'Nome da Categoria Pai' ?></label>
                        <input name="name" value="<?= h($editingItem['name'] ?? '') ?>" required placeholder="<?= isset($_GET['parent_id']) ? 'Ex: Docker, SSH, Nginx...' : 'Ex: Windows, Linux, Zabbix...' ?>">
                    </div>
                    <div class="col">
                        <label>Vincular a Categoria Pai (Opcional)</label>
                        <select name="parent_id">
                            <option value="">-- Nenhuma (Tornar Categoria Raiz) --</option>
                            <?php 
                            $preSelectedParent = safe_int($_GET['parent_id'] ?? null);
                            foreach ($docParents as $p): 
                                if ($editingItem && (int)$editingItem['id'] === (int)$p['id']) continue;
                                $selected = '';
                                if ($editingItem && (int)$editingItem['parent_id'] === (int)$p['id']) $selected = 'selected';
                                elseif ($preSelectedParent === (int)$p['id']) $selected = 'selected';
                            ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $selected ?>>
                                    <?= h($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="muted" style="font-size:0.75rem; margin-top:5px">Categorias raiz servem para agrupar as tags. Deixe em branco para criar uma nova Categoria Pai.</div>
                    </div>
                </div>
                <div style="margin-top:25px; border-top:1px solid var(--border-color); padding-top:20px; display:flex; gap:10px">
                    <button type="submit" class="btn primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px; vertical-align:middle"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        <?= $editingItem ? 'Atualizar' : 'Criar' ?> Item
                    </button>
                    <a href="?type=docs" class="btn">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>

    <?php elseif ($type === 'advanced'): ?>
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0">Configurações Avançadas: Tipos de Ativo</h3>
                <?php if ($action === 'list'): ?>
                    <a href="?type=advanced&action=add" class="btn btn-primary" style="padding:6px 12px; font-size:13px;">Novo Tipo</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="type" value="advanced">
                        <input type="hidden" name="action" value="save">
                        <?php if ($id): ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom:15px;">
                            <label style="display:block; margin-bottom:5px; font-weight:500;">Nome do Tipo de Ativo</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editingItem['name'] ?? '') ?>" required placeholder="Ex: Servidor Cloud, Firewall, Switch...">
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn btn-primary">Salvar</button>
                            <a href="?type=advanced" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th style="width:100px; text-align:right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assetTypesList)): ?>
                                    <tr>
                                        <td colspan="2" style="text-align:center; padding:20px; color:#666;">Nenhum tipo de ativo cadastrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assetTypesList as $cat): ?>
                                        <tr>
                                            <td style="font-weight:500;"><?= htmlspecialchars($cat['name']) ?></td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="?type=advanced&action=edit&id=<?= $cat['id'] ?>" class="btn-icon" title="Editar"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este tipo de ativo?')">
                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="type" value="advanced">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                    <button type="submit" class="btn-icon" style="color:#e53e3e; background:none; border:none; padding:0; cursor:pointer;" title="Excluir"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<style>
.tab-btn {
    padding: 8px 16px;
    text-decoration: none;
    color: var(--muted-color);
    border-radius: 4px;
    font-weight: 600;
    transition: all 0.2s;
}
.tab-btn:hover {
    background: rgba(0,0,0,0.05);
    color: var(--text-color);
}
.tab-btn.active {
    background: var(--primary);
    color: white !important;
}
.config-form-box {
    background: rgba(0,0,0,0.02);
    border: 1px solid var(--border-color);
    padding: 20px;
    border-radius: 8px;
}
.small {
    padding: 4px 10px !important;
    font-size: 0.85rem !important;
}
.danger {
    color: var(--error-color) !important;
}
.table-container {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th {
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid var(--border-color);
    font-weight: 600;
    color: var(--muted-color);
    text-transform: uppercase;
    font-size: 0.75rem;
}
td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
}
tr:hover {
    background: rgba(0,0,0,0.01);
}
</style>

<?php
render_footer();



