<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
$success = '';
$error = '';

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$docId = safe_int($_POST['id'] ?? $_GET['id'] ?? null);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    
    if ($action === 'create' || $action === 'edit') {
        $title = trim((string)($_POST['title'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $commands = trim((string)($_POST['commands'] ?? ''));

        if ($title === '' || $content === '') {
            $error = 'Preencha pelo menos título e conteúdo da documentação.';
        } else {
            try {
                if ($action === 'create') {
                    $docId = doc_create($pdo, $title, $category, $content, $commands, (int)$user['id']);
                    $success = 'Documento criado com sucesso.';
                } else {
                    doc_update($pdo, $docId, $title, $category, $content, $commands, (int)$user['id']);
                    $success = 'Documento atualizado com sucesso.';
                }

                // Handle attachments
                if (!empty($_FILES['attachments'])) {
                    $files = $_FILES['attachments'];
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $f = [
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];
                            $uploaded = upload_file($f, __DIR__ . '/uploads/docs');
                            if ($uploaded) {
                                doc_attachment_create($pdo, $docId, (int)$user['id'], $uploaded['name'], $uploaded['path'], $uploaded['type'], $uploaded['size']);
                            }
                        }
                    }
                }
                
                if ($action === 'create') {
                    $action = '';
                } else {
                    $action = 'view';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$doc = null;
$attachments = [];
if ($docId) {
    $doc = doc_find($pdo, $docId);
    if ($doc) {
        $attachments = doc_attachments_list($pdo, $docId);
    }
}

$docs = docs_list($pdo);

render_header('Atendente · Documentação', current_user());
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div>
      <h2 style="margin:0">Documentação do Sistema</h2>
      <div class="muted">FAQ, manuais técnicos, comandos e procedimentos.</div>
    </div>
    <div style="display:flex;gap:10px">
        <?php if ($action !== ''): ?>
            <a class="btn" href="/tk_docs.php">Voltar para Lista</a>
        <?php endif; ?>
        <?php if ($action !== 'create'): ?>
            <a class="btn primary" href="/tk_docs.php?action=create">Criar Nova Doc</a>
        <?php endif; ?>
    </div>
  </div>

  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <?php if ($action === 'create' || ($action === 'edit' && $doc)): ?>
    <div class="card" style="background: var(--bg); border: 1px solid var(--border)">
        <h3 style="margin-top:0"><?= $action === 'create' ? 'Nova Documentação' : 'Editar: ' . h($doc['title']) ?></h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= h($action) ?>">
            <?php if ($docId): ?><input type="hidden" name="id" value="<?= (int)$docId ?>"><?php endif; ?>
            
            <div class="row">
                <div class="col">
                    <label>Título</label>
                    <input name="title" value="<?= h($doc['title'] ?? '') ?>" required style="width:100%">
                </div>
                <div class="col">
                    <label>Categoria</label>
                    <input name="category" value="<?= h($doc['category'] ?? '') ?>" placeholder="Ex: Linux, Zabbix, Backup..." style="width:100%">
                </div>
            </div>
            
            <div style="margin-top:15px">
                <label>Descrição / Procedimento</label>
                <textarea name="content" required style="width:100%; height:200px"><?= h($doc['content'] ?? '') ?></textarea>
            </div>
            
            <div style="margin-top:15px">
                <label>Comandos (opcional)</label>
                <textarea name="commands" style="width:100%; height:100px; font-family: monospace; background: var(--input-bg); color: var(--text)" placeholder="Digite aqui os comandos um por linha..."><?= h($doc['commands'] ?? '') ?></textarea>
            </div>

            <div style="margin-top:15px; padding: 15px; border: 2px dashed var(--border); border-radius: 4px">
                <label style="display:block; margin-bottom:10px; font-weight: 700">Anexar Prints / Documentos</label>
                <input type="file" name="attachments[]" multiple>
                <div class="muted" style="font-size: 0.8em; margin-top: 5px">Imagens, PDFs, etc.</div>
            </div>

            <div style="margin-top:20px; display:flex; gap:10px">
                <button class="btn primary" type="submit"><?= $action === 'create' ? 'Salvar Documento' : 'Atualizar Documento' ?></button>
                <a class="btn" href="/tk_docs.php<?= $docId ? '?action=view&id='.$docId : '' ?>">Cancelar</a>
            </div>
        </form>
    </div>

  <?php elseif ($action === 'view' && $doc): ?>
    <div class="card" style="background: var(--panel); border: 1px solid var(--border)">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px">
            <div>
                <span class="badge"><?= h($doc['category'] ?: 'Geral') ?></span>
                <h1 style="margin:10px 0"><?= h($doc['title']) ?></h1>
                <div class="muted" style="font-size:0.9em">
                    Criado em: <?= h($doc['created_at']) ?> 
                    <?php if ($doc['author_name']): ?> por <?= h($doc['author_name']) ?><?php endif; ?>
                </div>
            </div>
            <a class="btn" href="/tk_docs.php?action=edit&id=<?= (int)$doc['id'] ?>">Editar Documento</a>
        </div>

        <div style="white-space: pre-wrap; line-height: 1.6; margin-bottom: 30px; font-size: 1.1em">
            <?= h($doc['content']) ?>
        </div>

        <?php if ($doc['commands']): ?>
            <div style="margin-bottom:30px">
                <h4 style="margin-bottom:10px">Comandos / Scripts:</h4>
                <div style="background: var(--input-bg); color: var(--text); padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; position: relative">
                    <?= h($doc['commands']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($attachments): ?>
            <div style="margin-top:30px; border-top: 1px solid var(--border); padding-top: 20px">
                <h4 style="margin-bottom:15px">Anexos e Prints:</h4>
                <div style="display:flex; flex-wrap:wrap; gap:15px">
                    <?php foreach ($attachments as $a): ?>
                        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; width: 200px; background: var(--bg)">
                            <?php if (str_starts_with($a['file_type'], 'image/')): ?>
                                <a href="/download.php?type=doc&id=<?= (int)$a['id'] ?>" target="_blank">
                                    <img src="/download.php?type=doc&id=<?= (int)$a['id'] ?>" style="width:100%; height:120px; object-fit: cover">
                                </a>
                            <?php else: ?>
                                <div style="height:120px; display:flex; align-items:center; justify-content:center">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                            <?php endif; ?>
                            <div style="padding: 10px; font-size: 0.85em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis" title="<?= h($a['file_name']) ?>">
                                <a href="/download.php?type=doc&id=<?= (int)$a['id'] ?>"><?= h($a['file_name']) ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Título</th>
          <th>Categoria</th>
          <th>Criado em</th>
          <th>Resumo</th>
          <th style="text-align:right">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$docs): ?>
          <tr><td colspan="5" class="muted">Nenhuma documentação cadastrada ainda.</td></tr>
        <?php endif; ?>
        <?php foreach ($docs as $d): ?>
          <tr>
            <td style="font-weight:700"><?= h((string)$d['title']) ?></td>
            <td><span class="badge"><?= h((string)($d['category'] ?: 'Geral')) ?></span></td>
            <td class="muted" style="font-size:0.9em"><?= h((string)($d['created_at'] ?? '')) ?></td>
            <td class="muted"><?= h(truncate((string)$d['content'], 80)) ?></td>
            <td style="text-align:right">
                <a class="btn small" href="/tk_docs.php?action=view&id=<?= (int)$d['id'] ?>">Visualizar</a>
                <a class="btn small primary" href="/tk_docs.php?action=edit&id=<?= (int)$d['id'] ?>">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
render_footer();
