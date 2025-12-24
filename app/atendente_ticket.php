<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('atendente');
$ticketId = safe_int($_GET['id'] ?? ($_POST['id'] ?? null));
$error = '';
$success = '';

if (!$ticketId) {
    header('Location: /atendente_fila.php');
    exit;
}

$ticket = ticket_find($pdo, $ticketId);
if (!$ticket) {
    die('Chamado não encontrado.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $statusId = safe_int($_POST['status_id'] ?? null);
        if ($statusId) {
            ticket_update_status($pdo, $ticketId, $statusId, (int)$user['id']);
            $success = 'Status atualizado com sucesso.';
            $ticket = ticket_find($pdo, $ticketId);
        } else {
            $error = 'Selecione um status válido.';
        }
    }

    if ($action === 'add_comment') {
        $content = trim((string)($_POST['content'] ?? ''));
        if ($content === '' && empty($_FILES['attachments']['name'][0])) {
            $error = 'O comentário ou um arquivo é obrigatório.';
        } else {
            $commentId = ticket_comment_create($pdo, $ticketId, (int)$user['id'], $content);
            
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
                        $uploaded = upload_file($f, __DIR__ . '/uploads/tickets');
                        if ($uploaded) {
                            ticket_attachment_create($pdo, (int)$user['id'], $uploaded['name'], $uploaded['path'], $uploaded['type'], $uploaded['size'], $ticketId, $commentId);
                        }
                    }
                }
            }
            $success = 'Interação adicionada com sucesso.';
        }
    }
}

$statuses = ticket_statuses($pdo);
$history = ticket_history($pdo, $ticketId);
$comments = ticket_comments_list($pdo, $ticketId);
$attachments = ticket_attachments_list($pdo, $ticketId);
$metrics = ticket_calculate_metrics($ticket, $pdo);

ticket_mark_as_read($pdo, $ticketId, (int)$user['id']);

render_header('Atendente · Detalhes do Chamado', current_user());
?>
<div class="card" style="max-width:900px;margin:0 auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div style="display:flex;gap:10px">
            <span class="badge" style="background: var(--panel); border: 1px solid var(--border); font-weight: 700; text-transform: uppercase; font-size: 11px">
                Prioridade: <?= h(strtoupper((string)$ticket['priority'])) ?>
            </span>
        </div>
        <div style="display:flex;gap:15px;align-items:center">
            <div class="badge <?= $metrics['sla_status'] === 'Dentro do Prazo' ? 'success' : 'danger' ?>" style="padding: 6px 12px; font-weight: 700" title="Meta: <?= $metrics['slo_formatted'] ?>">
                SLA: <?= $metrics['sla_status'] ?> (<?= $metrics['sli_formatted'] ?> / <?= $metrics['slo_formatted'] ?>)
            </div>
            <div class="muted">Chamado #<?= (int)$ticket['id'] ?></div>
        </div>
    </div>

    <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <div class="row" style="margin-bottom:20px; gap: 20px">
        <div class="col" style="flex:2">
            <div class="card" style="border-left: 5px solid #27c4a8; margin-bottom: 25px">
                <h2 style="margin:0 0 10px 0"><?= h((string)$ticket['subject']) ?></h2>
                <div class="muted" style="margin-bottom:15px; display: flex; gap: 15px; align-items: center">
                    <span><strong>Cliente:</strong> <?= h((string)$ticket['client_name']) ?></span>
                    <span><strong>Categoria:</strong> <?= h((string)$ticket['category_name']) ?></span>
                    <span><strong>Abertura:</strong> <?= h((string)$ticket['created_at']) ?></span>
                </div>
                <div style="background: var(--panel); border: 1px solid var(--border); padding:15px; border-radius:4px">
                    <div style="font-weight:700;margin-bottom:10px; color: var(--text)">Descrição da Solicitação:</div>
                    <div style="white-space:pre-wrap; color: var(--muted)"><?= h((string)$ticket['description']) ?></div>
                </div>
            </div>

            <h3 style="margin-top:40px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                Timeline de Interações
            </h3>
            
            <div style="display:flex;flex-direction:column;gap:20px;margin-bottom: 40px">
                <?php if (!$comments): ?>
                    <div class="card muted" style="text-align: center; padding: 30px">Nenhuma interação registrada até o momento.</div>
                <?php endif; ?>
                <?php foreach ($comments as $c): ?>
                    <?php 
                        $isAtendente = $c['user_role'] === 'atendente';
                        $borderColor = $isAtendente ? '#27c4a8' : '#3498db';
                        $bg = $isAtendente ? 'rgba(39, 196, 168, 0.05)' : 'rgba(52, 152, 219, 0.05)';
                        $commentAttachments = array_filter($attachments, fn($a) => $a['comment_id'] == $c['id']);
                    ?>
                    <div class="card" style="border-left: 4px solid <?= $borderColor ?>; background: <?= $bg ?>; padding: 15px">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px">
                            <div style="display: flex; align-items: center; gap: 10px">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= $borderColor ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px">
                                    <?= strtoupper(substr($c['user_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--text)"><?= h($c['user_name']) ?></div>
                                    <div style="font-size: 0.75em; color: var(--muted)"><?= $isAtendente ? 'Atendente' : 'Cliente' ?></div>
                                </div>
                            </div>
                            <div style="font-size: 0.8em; color: var(--muted)"><?= h($c['created_at']) ?></div>
                        </div>
                        <div style="white-space: pre-wrap; margin-left: 42px; color: var(--text)"><?= h($c['content']) ?></div>
                        <?php if ($commentAttachments): ?>
                            <div style="margin-top:15px; margin-left: 42px; padding-top:10px; border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 10px">
                                <?php foreach ($commentAttachments as $ca): ?>
                                    <a href="/download.php?id=<?= (int)$ca['id'] ?>" class="btn small" style="display: flex; align-items: center; gap: 6px; text-decoration: none">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                        <?= h($ca['file_name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card" style="border: 1px solid var(--border); padding: 20px">
                <h4 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Nova Interação
                </h4>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                    <input type="hidden" name="action" value="add_comment">
                    
                    <textarea name="content" style="width:100%; height:150px; margin-bottom:15px; padding: 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--panel); color: var(--text)" placeholder="Digite sua resposta técnica ou orientações para o cliente..."></textarea>
                    
                    <div style="margin-bottom:20px; padding: 15px; border: 2px dashed var(--border); border-radius: 4px; background: var(--bg)">
                        <label style="display:block; margin-bottom:10px; font-weight: 700; color: var(--text)">Anexar Documentos/Imagens</label>
                        <input type="file" name="attachments[]" multiple style="width:100%">
                        <div style="font-size: 0.75em; color: var(--muted); margin-top: 8px">
                            Formatos aceitos: PDF, Excel, Word, TXT, PNG, JPG, GIF.
                        </div>
                    </div>
                    
                    <button class="btn primary" type="submit" style="padding: 10px 24px; font-weight: 700">Enviar Interação</button>
                </form>
            </div>

            <?php if (!empty($ticket['extra_json'])): ?>
                <?php $extra = json_decode($ticket['extra_json'], true); ?>
                <?php if ($extra): ?>
                    <div style="margin-top:20px">
                        <div style="font-weight:700;margin-bottom:10px">Dados Adicionais:</div>
                        <table class="table small">
                            <?php foreach ($extra as $k => $v): ?>
                                <tr>
                                    <td style="width:150px;font-weight:700"><?= h((string)$k) ?></td>
                                    <td><?= h(is_array($v) ? json_encode($v) : (string)$v) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="col" style="flex:1">
            <div class="card" style="border:1px solid var(--border);padding:15px;border-radius:4px">
                <div style="font-weight:700;margin-bottom:15px">Gerenciar Chamado</div>
                
                <form method="post" style="margin-bottom:15px">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                    <input type="hidden" name="action" value="update_status">
                    
                    <label>Alterar Status</label>
                    <select name="status_id" style="width:100%;margin-bottom:10px">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ($s['id'] == $ticket['status_id']) ? 'selected' : '' ?>>
                                <?= h((string)$s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn primary" type="submit" style="width:100%">Atualizar Status</button>
                </form>

                <div style="display:flex;flex-direction:column;gap:10px">
                    <a class="btn" href="/app/atendente_transfere.php?ticket_id=<?= (int)$ticket['id'] ?>" style="text-align:center">Transferir Chamado</a>
                    <a class="btn danger" href="/app/atendente_encerrar.php?ticket_id=<?= (int)$ticket['id'] ?>" style="text-align:center">Encerrar Chamado</a>
                </div>
            </div>

            <div style="margin-top:20px">
                <div style="font-weight:700;margin-bottom:10px">Informações Atuais:</div>
                <div class="muted">Status: <span class="badge"><?= h((string)$ticket['status_name']) ?></span></div>
                <div class="muted">Atendente: <?= h((string)$ticket['assigned_name'] ?: 'Não atribuído') ?></div>
            </div>
        </div>
    </div>

    <div style="margin-top:30px">
        <h3>Histórico</h3>
        <table class="table small">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Ação</th>
                    <th>Responsável</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h_item): ?>
                    <tr>
                        <td><?= h((string)$h_item['created_at']) ?></td>
                        <td><span class="badge"><?= h((string)$h_item['action']) ?></span></td>
                        <td><?= h((string)$h_item['actor_name']) ?></td>
                        <td>
                            <?php 
                            $payload = json_decode($h_item['payload_json'], true);
                            if ($payload) {
                                foreach ($payload as $pk => $pv) {
                                    echo "<div><strong>" . h((string)$pk) . ":</strong> " . h((string)$pv) . "</div>";
                                }
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?>
                    <tr><td colspan="4" class="muted">Nenhum histórico registrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_footer();



