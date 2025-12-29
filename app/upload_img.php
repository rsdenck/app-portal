<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (empty($_FILES['image'])) {
    echo json_encode(['error' => 'Nenhuma imagem enviada.']);
    exit;
}

$file = $_FILES['image'];
$uploaded = upload_file($file, __DIR__ . '/uploads/docs');

if ($uploaded) {
    // Para imagens coladas, não vinculamos a um doc_id imediatamente
    // Mas salvamos no banco para poder baixar via download.php
    $stmt = $pdo->prepare('INSERT INTO doc_attachments (doc_id, user_id, file_name, file_path, file_type, file_size) VALUES (?,?,?,?,?,?)');
    $stmt->execute([0, (int)$user['id'], $uploaded['name'], $uploaded['path'], $uploaded['type'], $uploaded['size']]);
    $id = $pdo->lastInsertId();

    echo json_encode([
        'url' => '/download.php?type=doc&id=' . $id,
        'name' => $uploaded['name']
    ]);
} else {
    echo json_encode(['error' => 'Falha ao fazer upload da imagem.']);
}
