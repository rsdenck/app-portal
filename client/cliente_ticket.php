<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('cliente');
$clientId = (int)$user['id'];
$ticketId = safe_int($_GET['id'] ?? ($_POST['id'] ?? null));
$error = '';
$success = '';

if (!$ticketId) {
    header('Location: /cliente_chamado.php');
    exit;
}

$ticket = ticket_find($pdo, $ticketId);
if (!$ticket || (int)$ticket['client_user_id'] !== $clientId) {
    die('Chamado não encontrado ou acesso negado.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'close') {
        if ($ticket['status_slug'] !== 'encerrado' && $ticket['status_slug'] !== 'fechado') {
            ticket_close($pdo, $ticketId, $clientId);
            $success = 'Chamado encerrado com sucesso.';
            $ticket = ticket_find($pdo, $ticketId);
        } else {
            $error = 'Este chamado já está encerrado.';
        }
    }

    if ($action === 'add_comment') {
        $content = trim((string)($_POST['content'] ?? ''));
        if ($content === '' && empty($_FILES['attachments']['name'][0])) {
            $error = 'O comentário ou um arquivo é obrigatório.';
        } else {
            $commentId = ticket_comment_create($pdo, $ticketId, $clientId, $content);
            
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
                            ticket_attachment_create($pdo, $clientId, $uploaded['name'], $uploaded['path'], $uploaded['type'], $uploaded['size'], $ticketId, $commentId);
                        }
                    }
                }
            }
            $success = 'Interação adicionada com sucesso.';
        }
    }
}

$history = ticket_history($pdo, $ticketId);
$comments = ticket_comments_list($pdo, $ticketId);
$attachments = ticket_attachments_list($pdo, $ticketId);
$metrics = ticket_calculate_metrics($ticket, $pdo);

ticket_mark_as_read($pdo, $ticketId, $clientId);

render_header('Cliente · Detalhes do Chamado', current_user());
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

    <div class="row" style="margin-bottom:20px">
        <div class="col" style="flex:2">
            <h2 style="margin:0 0 10px 0"><?= h((string)$ticket['subject']) ?></h2>
            <div class="muted" style="margin-bottom:15px">
                Categoria: <?= h((string)$ticket['category_name']) ?> · Criado em: <?= h((string)$ticket['created_at']) ?>
            </div>
            
            <div class="card" style="background: var(--panel); border: 1px solid var(--border); padding:15px; border-radius:4px; margin-bottom:30px">
                <div style="font-weight:700; margin-bottom:10px; color: var(--text)">Sua solicitação:</div>
                <div style="white-space:pre-wrap; color: var(--muted)"><?= h((string)$ticket['description']) ?></div>
            </div>

            <h3 style="margin-top:40px;border-bottom:1px solid var(--border);padding-bottom:10px">Interações</h3>
            
            <div style="display:flex;flex-direction:column;gap:20px;margin-top:20px">
                <?php if (!$comments): ?>
                    <div class="muted" style="text-align: center; padding: 40px; background: var(--panel); border-radius: 8px; border: 1px dashed var(--border)">
                        Nenhuma interação registrada ainda.
                    </div>
                <?php endif; ?>
                <?php foreach ($comments as $c): ?>
                    <?php 
                        $isAtendente = $c['user_role'] === 'atendente';
                        $align = $isAtendente ? 'flex-start' : 'flex-end';
                        $bgColor = $isAtendente ? 'var(--panel)' : 'rgba(var(--primary-rgb), 0.1)';
                        $borderColor = $isAtendente ? 'var(--border)' : 'var(--primary)';
                        $commentAttachments = array_filter($attachments, fn($a) => $a['comment_id'] == $c['id']);
                    ?>
                    <div style="align-self: <?= $align ?>; width: 90%; background: <?= $bgColor ?>; padding: 15px 20px; border-radius: 12px; border: 1px solid <?= $borderColor ?>; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.05)">
                        <div style="font-size: 0.85em; font-weight: 700; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center">
                            <span style="display: flex; align-items: center; gap: 6px; color: var(--text)">
                                <?php if ($isAtendente): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Atendente: <?= h($c['user_name']) ?>
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Você
                                <?php endif; ?>
                            </span>
                            <span class="muted" style="font-weight: 400"><?= h($c['created_at']) ?></span>
                        </div>
                        <div style="white-space: pre-wrap; line-height: 1.6; color: var(--text)"><?= h($c['content']) ?></div>
                        <?php if ($commentAttachments): ?>
                            <div style="margin-top:15px; padding-top:15px; border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 10px">
                                <?php foreach ($commentAttachments as $ca): ?>
                                    <a href="/download.php?id=<?= (int)$ca['id'] ?>" class="btn small" style="display: flex; align-items: center; gap: 8px; text-decoration: none; background: var(--panel); border: 1px solid var(--border); padding: 5px 10px; border-radius: 4px; font-size: 0.85em">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                        <?= h($ca['file_name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($ticket['status_slug'] !== 'encerrado' && $ticket['status_slug'] !== 'fechado'): ?>
                <div class="card" style="margin-top:40px; border: 1px solid var(--border); padding: 20px">
                    <h4 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Nova Interação
                    </h4>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                        <input type="hidden" name="action" value="add_comment">
                        
                        <textarea name="content" style="width:100%; height:150px; margin-bottom:15px; padding: 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--panel); color: var(--text)" placeholder="Digite sua dúvida ou resposta técnica aqui..."></textarea>
                        
                        <div style="margin-bottom:20px; padding: 15px; border: 2px dashed var(--border); border-radius: 4px; background: var(--panel)">
                            <label style="display:block; margin-bottom:10px; font-weight: 700; color: var(--text)">Anexar Documentos/Imagens</label>
                            <input type="file" name="attachments[]" multiple style="width:100%">
                            <div style="font-size: 0.75em; color: var(--muted); margin-top: 8px">
                                Formatos aceitos: PDF, Excel, Word, TXT, PNG, JPG, GIF.
                            </div>
                        </div>
                        
                        <button class="btn primary" type="submit" style="padding: 10px 24px; font-weight: 700">Enviar Interação</button>
                    </form>
                </div>
            <?php endif; ?>

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
                <div style="font-weight:700;margin-bottom:15px">Status do Chamado</div>
                
                <div style="margin-bottom:15px">
                    <span class="badge" style="font-size:1.1em;padding:8px 12px"><?= h((string)$ticket['status_name']) ?></span>
                </div>

                <div class="muted" style="margin-bottom:15px">
                    Atendente Responsável:<br>
                    <strong><?= h((string)$ticket['assigned_name'] ?: 'Aguardando atribuição') ?></strong>
                </div>

                <?php if ($ticket['status_slug'] !== 'encerrado' && $ticket['status_slug'] !== 'fechado'): ?>
                    <form method="post" onsubmit="return confirm('Tem certeza que deseja encerrar este chamado?')">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
                        <input type="hidden" name="action" value="close">
                        <button class="btn danger" type="submit" style="width:100%">Encerrar Chamado</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="margin-top:30px">
        <h3>Histórico de Atualizações</h3>
        <table class="table small">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Ação</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h_item): ?>
                    <tr>
                        <td><?= h((string)$h_item['created_at']) ?></td>
                        <td><span class="badge"><?= h((string)$h_item['action']) ?></span></td>
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
                    <tr><td colspan="3" class="muted">Nenhuma atualização registrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_footer();



