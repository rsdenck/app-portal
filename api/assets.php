<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!current_user()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$user = current_user();
$isAdmin = ($user['role'] === 'atendente');
$clientId = (int)$user['id'];
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        $sql = "SELECT * FROM assets WHERE id = :id";
        $params = [':id' => $id];
        
        if (!$isAdmin) {
            $sql .= " AND client_user_id = :client_id";
            $params[':client_id'] = $clientId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $asset = $stmt->fetch();
        
        if ($asset) {
            echo json_encode(['success' => true, 'asset' => $asset]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ativo não encontrado']);
        }
    } 
    elseif ($action === 'add' || $action === 'edit') {
        $data = [
            'type_id' => (int)$_POST['type_id'],
            'name' => $_POST['name'],
            'manufacturer' => $_POST['manufacturer'] ?? '',
            'model' => $_POST['model'] ?? '',
            'serial_number' => $_POST['serial_number'] ?? '',
            'purchase_date' => $_POST['purchase_date'] ?: null,
            'warranty_expiry' => $_POST['warranty_expiry'] ?: null,
            'notes' => $_POST['notes'] ?? '',
            'cpu' => $_POST['cpu'] ?? '',
            'ram' => $_POST['ram'] ?? '',
            'storage' => $_POST['storage'] ?? '',
            'os_name' => $_POST['os_name'] ?? '',
            'os_version' => $_POST['os_version'] ?? '',
            'location' => $_POST['location'] ?? '',
            'responsible_person' => $_POST['responsible_person'] ?? '',
            'ip_address' => $_POST['ip_address'] ?? '',
            'subnet_mask' => $_POST['subnet_mask'] ?? '',
            'gateway' => $_POST['gateway'] ?? '',
            'dns_servers' => $_POST['dns_servers'] ?? '',
            'mac_address' => $_POST['mac_address'] ?? '',
            'switch_port' => $_POST['switch_port'] ?? '',
            'vlan' => $_POST['vlan'] ?? ''
        ];

        if ($isAdmin && isset($_POST['client_user_id'])) {
            $data['client_user_id'] = (int)$_POST['client_user_id'];
        }

        if ($action === 'add') {
            if (!$isAdmin) {
                $data['client_user_id'] = $clientId;
            }
            
            $fields = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $stmt = $pdo->prepare("INSERT INTO assets ($fields) VALUES ($placeholders)");
            $stmt->execute($data);
            
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
            exit;
        } else {
            $id = (int)$_POST['id'];
            
            // Check ownership
            if (!$isAdmin) {
                $check = $pdo->prepare("SELECT id FROM assets WHERE id = :id AND client_user_id = :client_id");
                $check->execute([':id' => $id, ':client_id' => $clientId]);
                if (!$check->fetch()) {
                    throw new Exception('Acesso negado');
                }
            }
            
            $updates = [];
            foreach ($data as $key => $val) {
                $updates[] = "$key = :$key";
            }
            $updateStr = implode(', ', $updates);
            
            $data['id'] = $id;
            $stmt = $pdo->prepare("UPDATE assets SET $updateStr WHERE id = :id");
            $stmt->execute($data);
            
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
            exit;
        }
    } 
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        $sql = "DELETE FROM assets WHERE id = :id";
        $params = [':id' => $id];
        
        if (!$isAdmin) {
            $sql .= " AND client_user_id = :client_id";
            $params[':client_id'] = $clientId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ativo não encontrado ou sem permissão']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
