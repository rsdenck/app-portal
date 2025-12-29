<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
$success = '';
$error = '';

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$docId = safe_int($_POST['id'] ?? $_GET['id'] ?? null);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    
    if ($action === 'create' || $action === 'edit') {
        $title = trim((string)($_POST['title'] ?? ''));
        $categoryId = safe_int($_POST['category_id'] ?? null);
        $content = trim((string)($_POST['content'] ?? ''));
        // We no longer use a separate commands field in the form, 
        // but we keep the variable for the database function signature.
        $commands = ''; 

        if ($title === '' || $content === '' || !$categoryId) {
            $error = 'Preencha o título, selecione uma categoria/tag e insira o conteúdo.';
        } else {
            try {
                if ($action === 'create') {
                    $docId = doc_create($pdo, $title, $categoryId, $content, $commands, (int)$user['id']);
                    $success = 'Documento criado com sucesso.';
                } else {
                    doc_update($pdo, $docId, $title, $categoryId, $content, $commands, (int)$user['id']);
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
                    redirect('/app/tk_docs.php');
                } else {
                    $action = 'view';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && $docId) {
        try {
            doc_delete($pdo, $docId);
            $success = 'Documento excluído com sucesso.';
            $action = '';
            $docId = null;
        } catch (Throwable $e) {
            $error = $e->getMessage();
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
$all_categories = doc_categories_list($pdo);

render_header('Atendente · Documentação', current_user());
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div>
      <h2 style="margin:0">Documentação do Sistema</h2>
      <div class="muted">FAQ, manuais técnicos, comandos e procedimentos.</div>
    </div>
    <div style="display:flex;gap:10px">
        <?php if ($action !== 'create'): ?>
            <a class="btn primary" href="/app/tk_docs.php?action=create">Criar Nova Doc</a>
        <?php endif; ?>
    </div>
  </div>

  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <?php if ($action === 'create' || ($action === 'edit' && $doc)): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <div class="card" style="background: var(--bg); border: 1px solid var(--border)">
        <h3 style="margin-top:0"><?= $action === 'create' ? 'Nova Documentação' : 'Editar: ' . h($doc['title']) ?></h3>
        <form method="post" enctype="multipart/form-data" onsubmit="if(typeof easyMDE !== 'undefined') easyMDE.save();">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= h($action) ?>">
            <?php if ($docId): ?><input type="hidden" name="id" value="<?= (int)$docId ?>"><?php endif; ?>
            
            <div class="row">
                <div class="col">
                    <label>Título</label>
                    <input name="title" value="<?= h($doc['title'] ?? '') ?>" required style="width:100%">
                </div>
                <div class="col">
                    <label>Tag (Categoria > Subcategoria)</label>
                    <select name="category_id" style="width:100%" required>
                        <option value="">Selecione uma tag...</option>
                        <?php 
                        $currentCat = '';
                        foreach ($all_categories as $cat): 
                            $catName = (string)($cat['category'] ?? '');
                            if ($catName !== $currentCat):
                                if ($currentCat !== '') echo '</optgroup>';
                                $currentCat = $catName;
                                echo '<optgroup label="' . h($currentCat) . '">';
                            endif;
                        ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= ($doc['category_id'] ?? null) == $cat['id'] ? 'selected' : '' ?>>
                                <?= h((string)$cat['subcategory']) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($currentCat !== '') echo '</optgroup>'; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-top:15px">
                <label>Conteúdo da Documentação (Markdown)</label>
                <div class="muted" style="font-size: 0.8em; margin-bottom: 5px">Dica: Você pode colar imagens (CTRL+V) diretamente no editor. Use blocos de código (```) para comandos.</div>
                <?php 
                    $editorContent = $doc['content'] ?? '';
                    if (!empty($doc['commands'])) {
                        // Se houver comandos legados, anexa ao conteúdo para migração
                        if (strpos($editorContent, $doc['commands']) === false) {
                            $editorContent .= "\n\n### Comandos / Scripts (Migrados)\n```bash\n" . $doc['commands'] . "\n```";
                        }
                    }
                ?>
                <textarea id="markdown-editor" name="content"><?= h($editorContent) ?></textarea>
            </div>

            <div style="margin-top:15px; padding: 15px; border: 2px dashed var(--border); border-radius: 4px">
                <label style="display:block; margin-bottom:10px; font-weight: 700">Anexar Prints / Documentos</label>
                <input type="file" name="attachments[]" multiple>
                <div class="muted" style="font-size: 0.8em; margin-top: 5px">Imagens, PDFs, etc.</div>
            </div>

            <div style="margin-top:20px; display:flex; gap:10px">
                <button class="btn primary" type="submit"><?= $action === 'create' ? 'Salvar Documento' : 'Atualizar Documento' ?></button>
                <a class="btn" href="/app/tk_docs.php<?= $docId ? '?action=view&id='.$docId : '' ?>">Cancelar</a>
            </div>
        </form>
    </div>
    <script>
        const easyMDE = new EasyMDE({
            element: document.getElementById('markdown-editor'),
            spellChecker: false,
            minHeight: "400px",
            maxHeight: "600px",
            autosave: {
                enabled: true,
                uniqueId: "doc_editor_<?= $docId ? (int)$docId : 'new_' . time() ?>",
                delay: 1000,
            },
            status: ["lines", "words", "cursor"],
            placeholder: "Escreva sua documentação em Markdown aqui...",
            promptURLs: true,
            renderingConfig: {
                singleLineBreaks: false,
                codeSyntaxHighlighting: true,
            },
            toolbar: false,
            status: false,
        });

        // Suporte a CTRL+V de imagens
        easyMDE.codemirror.on("paste", function(editor, event) {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf("image") !== -1) {
                    const blob = items[i].getAsFile();
                    const formData = new FormData();
                    formData.append('image', blob);
                    formData.append('csrf_token', '<?= h(csrf_token()) ?>');

                    // Mostrar indicador de carregamento no editor
                    const cursor = editor.getCursor();
                    editor.replaceRange("![Enviando imagem...]()", cursor);

                    fetch('/app/upload_img.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.url) {
                            const newContent = editor.getValue().replace("![Enviando imagem...]()", `![${data.name}](${data.url})`);
                            editor.setValue(newContent);
                        } else {
                            alert(data.error || 'Erro ao enviar imagem');
                            editor.replaceRange("", {line: cursor.line, ch: 0}, {line: cursor.line, ch: 23});
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Erro ao enviar imagem');
                    });
                }
            }
        });
    </script>
    <style>
        .EasyMDEContainer { background: #1e1e1e; color: #d4d4d4; border-radius: 8px; border: 1px solid var(--border); overflow: hidden; }
        .CodeMirror { background: #1e1e1e !important; color: #d4d4d4 !important; border: none !important; font-family: 'Fira Code', 'Consolas', monospace; font-size: 14px; padding: 10px; }
        .CodeMirror-selected { background: rgba(39, 196, 168, 0.3) !important; }
        .CodeMirror-line::selection, .CodeMirror-line > span::selection, .CodeMirror-line > span > span::selection { background: rgba(39, 196, 168, 0.3) !important; color: inherit !important; }
        .CodeMirror-line::-moz-selection, .CodeMirror-line > span::-moz-selection, .CodeMirror-line > span > span::-moz-selection { background: rgba(39, 196, 168, 0.3) !important; color: inherit !important; }
        .CodeMirror-cursor { border-left: 2px solid var(--primary) !important; }
        ::selection { background: rgba(39, 196, 168, 0.3) !important; color: inherit !important; }
        ::-moz-selection { background: rgba(39, 196, 168, 0.3) !important; color: inherit !important; }
        .editor-preview { background: var(--bg) !important; color: var(--text) !important; padding: 20px; }
        .cm-s-easymde .cm-header-1 { color: var(--primary); font-size: 1.5em; }
        .cm-s-easymde .cm-header-2 { color: var(--primary); font-size: 1.3em; }
        .cm-s-easymde .cm-header-3 { color: var(--primary); font-size: 1.1em; }
        .cm-s-easymde .cm-comment { background: rgba(255,255,255,0.05); border-radius: 3px; }
    </style>

  <?php elseif ($action === 'view' && $doc): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.8.0/styles/github-dark.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.8.0/lib/highlight.min.js"></script>

    <div class="card" style="background: var(--panel); border: 1px solid var(--border); max-width: 1000px; margin: 0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; border-bottom: 1px solid var(--border); padding-bottom: 20px;">
            <div>
                <?php if ($doc['category']): ?>
                    <span class="badge" style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8em;">
                        <?= h($doc['category']) ?> <?= $doc['subcategory'] ? ' > ' . h($doc['subcategory']) : '' ?>
                    </span>
                <?php else: ?>
                    <span class="badge" style="background: var(--border); color: var(--text-muted); padding: 4px 12px; border-radius: 20px; font-size: 0.8em;">Sem Categoria</span>
                <?php endif; ?>
                <h1 style="margin:15px 0 5px 0; font-size: 2.2rem; font-weight: 800; letter-spacing: -0.02em;"><?= h($doc['title']) ?></h1>
                <div class="muted" style="font-size:0.9em; display: flex; align-items: center; gap: 15px;">
                    <span><svg style="vertical-align: middle; margin-right: 4px;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?= h($doc['created_at']) ?></span>
                    <?php if ($doc['author_name']): ?>
                        <span><svg style="vertical-align: middle; margin-right: 4px;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= h($doc['author_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a class="btn" href="/app/tk_docs.php?action=edit&id=<?= (int)$doc['id'] ?>" style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Editar
                </a>
                <form method="post" onsubmit="return confirm('Deseja realmente excluir esta documentação?')" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                    <button type="submit" class="btn danger" style="display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        Excluir
                    </button>
                </form>
            </div>
        </div>

        <div id="markdown-content" class="markdown-body" style="line-height: 1.7; margin-bottom: 40px; font-size: 1.1rem; color: var(--text);">
            <div class="loading">Carregando conteúdo...</div>
            <textarea id="raw-markdown" style="display:none"><?= h($doc['content']) ?></textarea>
        </div>

        <?php if ($attachments): ?>
            <div style="margin-top:40px; border-top: 1px solid var(--border); padding-top: 30px">
                <h4 style="margin-bottom:20px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    Anexos e Prints
                </h4>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:20px">
                    <?php foreach ($attachments as $a): ?>
                        <div class="attachment-card" style="border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--bg); transition: transform 0.2s, box-shadow 0.2s;">
                            <?php if (str_starts_with($a['file_type'], 'image/')): ?>
                                <a href="/download.php?type=doc&id=<?= (int)$a['id'] ?>" target="_blank" style="display: block; height: 120px;">
                                    <img src="/download.php?type=doc&id=<?= (int)$a['id'] ?>" style="width:100%; height:100%; object-fit: cover">
                                </a>
                            <?php else: ?>
                                <div style="height:120px; display:flex; align-items:center; justify-content:center; background: var(--panel);">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="muted"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                            <?php endif; ?>
                            <div style="padding: 12px; font-size: 0.85em; border-top: 1px solid var(--border);">
                                <a href="/download.php?type=doc&id=<?= (int)$a['id'] ?>" style="display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); font-weight: 500;" title="<?= h($a['file_name']) ?>">
                                    <?= h($a['file_name']) ?>
                                </a>
                                <div class="muted" style="font-size: 0.8em; margin-top: 4px;"><?= format_bytes((int)$a['file_size']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const raw = document.getElementById('raw-markdown').value;
            const target = document.getElementById('markdown-content');
            
            marked.setOptions({
                highlight: function(code, lang) {
                    if (lang && hljs.getLanguage(lang)) {
                        return hljs.highlight(code, { language: lang }).value;
                    }
                    return hljs.highlightAuto(code).value;
                },
                breaks: true,
                gfm: true
            });
            
            target.innerHTML = marked.parse(raw);

            // Adicionar botões de copiar em blocos de código
            document.querySelectorAll('#markdown-content pre code').forEach((block) => {
                const pre = block.parentElement;
                pre.style.position = 'relative';
                
                const btn = document.createElement('button');
                btn.className = 'btn small';
                btn.innerHTML = 'Copiar';
                btn.style.position = 'absolute';
                btn.style.top = '5px';
                btn.style.right = '5px';
                btn.style.fontSize = '0.7rem';
                btn.style.padding = '2px 8px';
                btn.style.opacity = '0.6';
                
                btn.onclick = () => {
                    navigator.clipboard.writeText(block.innerText).then(() => {
                        btn.innerHTML = 'Copiado!';
                        setTimeout(() => btn.innerHTML = 'Copiar', 2000);
                    });
                };
                
                pre.appendChild(btn);
                
                // Mostrar botão apenas no hover
                pre.onmouseenter = () => btn.style.opacity = '1';
                pre.onmouseleave = () => btn.style.opacity = '0.6';
            });
        });
    </script>
    <style>
        .markdown-body img { max-width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin: 20px 0; }
        .markdown-body h1, .markdown-body h2, .markdown-body h3 { border-bottom: 1px solid var(--border); padding-bottom: 0.3em; margin-top: 1.5em; }
        .markdown-body code { background: rgba(175, 184, 193, 0.2); padding: 0.2em 0.4em; border-radius: 6px; font-family: monospace; font-size: 85%; }
        .markdown-body pre { background: #1e1e1e; padding: 16px; border-radius: 8px; overflow: auto; }
        .markdown-body pre code { background: none; padding: 0; font-size: 90%; color: #d4d4d4; }
        .markdown-body blockquote { border-left: 4px solid var(--primary); color: var(--text-muted); padding: 0 1em; margin: 0; }
        .markdown-body table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        .markdown-body table th, .markdown-body table td { border: 1px solid var(--border); padding: 8px 13px; }
        .markdown-body table tr:nth-child(2n) { background: var(--bg); }
        .attachment-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-color: var(--primary); }
    </style>

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
            <td>
                <?php if ($d['category']): ?>
                    <span class="badge" title="<?= h($d['category']) ?>"><?= h($d['category']) ?> > <?= h($d['subcategory']) ?></span>
                <?php else: ?>
                    <span class="badge muted">Sem Tag</span>
                <?php endif; ?>
            </td>
            <td class="muted" style="font-size:0.9em"><?= h((string)($d['created_at'] ?? '')) ?></td>
            <td class="muted"><?= h(truncate((string)$d['content'], 80)) ?></td>
            <td style="text-align:right">
                <a class="btn small" href="/app/tk_docs.php?action=view&id=<?= (int)$d['id'] ?>">Visualizar</a>
                <a class="btn small primary" href="/app/tk_docs.php?action=edit&id=<?= (int)$d['id'] ?>">Editar</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Deseja realmente excluir esta documentação?')">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <button type="submit" class="btn small danger">Excluir</button>
                </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php
render_footer();



