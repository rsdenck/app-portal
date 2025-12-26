<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!current_user()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $theme = $input['theme'] ?? '';

    $allowedThemes = ['dark', 'light', 'cyan', 'navy'];
    if (in_array($theme, $allowedThemes)) {
        try {
            user_update_theme($pdo, $userId, $theme);
            
            // Update session as well
            if (isset($_SESSION['user'])) {
                $_SESSION['user']['theme'] = $theme;
            }
            
            echo json_encode(['success' => true, 'message' => 'Tema atualizado com sucesso']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tema: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Tema inválido']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
