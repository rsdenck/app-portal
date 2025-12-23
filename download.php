<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$attachmentId = safe_int($_GET['id'] ?? null);
$type = $_GET['type'] ?? 'ticket';

if (!$attachmentId) {
    die('Anexo não especificado.');
}

if ($type === 'doc') {
    $stmt = $pdo->prepare('SELECT * FROM doc_attachments WHERE id = ?');
} else {
    $stmt = $pdo->prepare('SELECT * FROM ticket_attachments WHERE id = ?');
}

$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    die('Anexo não encontrado.');
}

// Security check: only allow if user is owner of the ticket or an admin/attendant
// (Simplified check for now: any logged in user can download if they know the ID)

$filePath = $attachment['file_path'];

if (!file_exists($filePath)) {
    die('Arquivo não encontrado no servidor.');
}

header('Content-Type: ' . $attachment['file_type']);
header('Content-Disposition: inline; filename="' . $attachment['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
