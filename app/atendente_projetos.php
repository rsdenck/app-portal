<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_project') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $error = 'O nome do projeto é obrigatório.';
        } else {
            project_create($pdo, $name);
            $success = 'Projeto criado com sucesso!';
        }
    }
}

$viewStatus = $_GET['status'] ?? 'active';
if (!in_array($viewStatus, ['active', 'archived'])) {
    $viewStatus = 'active';
}

$projects = project_get_all($pdo, $viewStatus);

render_header('Atendente · Projetos', $user);
?>

<div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
            <div style="font-weight:700;font-size:1.1rem">Gestor de Projetos</div>
            <div class="muted">Gerencie fluxos de trabalho e acompanhamento de projetos.</div>
        </div>
        <div style="display:flex;gap:8px">
            <div class="btn-group" style="display:flex;background:var(--panel-dark);padding:4px;border-radius:8px;border:1px solid var(--border)">
                <a href="?status=active" class="btn <?= $viewStatus === 'active' ? 'primary' : '' ?>" style="padding:4px 12px;font-size:0.8rem;border:none">Ativos</a>
                <a href="?status=archived" class="btn <?= $viewStatus === 'archived' ? 'primary' : '' ?>" style="padding:4px 12px;font-size:0.8rem;border:none">Arquivados</a>
            </div>
            <button class="btn primary" onclick="document.getElementById('modal-create').style.display='flex'">Criar Workflow</button>
        </div>
    </div>

    <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <div class="project-grid">
        <?php foreach ($projects as $project): ?>
            <div class="project-card">
                <div class="project-card-header">
                    <div class="project-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <div class="project-status-tag <?= h($project['status']) ?>">
                        <?= $project['status'] === 'active' ? 'Ativo' : 'Arquivado' ?>
                    </div>
                </div>
                <div class="project-title"><?= h((string)$project['name']) ?></div>
                <div class="project-meta">Criado em: <?= date('d/m/Y', strtotime((string)$project['created_at'])) ?></div>
                <div style="margin-top:auto;padding-top:12px">
                    <a href="/app/atendente_projeto_view.php?id=<?= (int)$project['id'] ?>" class="btn" style="width:100%;text-align:center">Gerenciar</a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($projects)): ?>
            <div class="muted" style="grid-column:1/-1;text-align:center;padding:40px">
                Nenhum projeto encontrado.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Criar Workflow -->
<div id="modal-create" class="modal-overlay" style="display:none">
    <div class="modal-content">
        <div style="font-weight:700;margin-bottom:12px">Criar Novo Workflow</div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_project">
            <div style="margin-bottom:14px">
                <label>Nome do Projeto / Workflow</label>
                <input name="name" required placeholder="Ex: Implantação de Servidor" style="width:100%">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn" onclick="document.getElementById('modal-create').style.display='none'">Cancelar</button>
                <button type="submit" class="btn primary">Criar</button>
            </div>
        </form>
    </div>
</div>

<style>
.project-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.project-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, border-color 0.2s;
}
.project-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}
.project-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.project-icon {
    width: 40px;
    height: 40px;
    background: rgba(87, 155, 252, 0.1);
    color: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.project-icon svg { width: 24px; height: 24px; }
.project-status-tag {
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
}
.project-status-tag.active { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.project-status-tag.archived { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.project-title {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 4px;
    color: var(--text);
}
.project-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: var(--panel);
    padding: 24px;
    border-radius: 12px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
</style>

<?php render_footer(); ?>



