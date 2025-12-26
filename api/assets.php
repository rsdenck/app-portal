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
        $typeIds = is_array($_POST['type_ids'] ?? null) ? array_filter($_POST['type_ids']) : [];
        
        $data = [
            'type_id' => !empty($typeIds) ? (int)$typeIds[0] : 0,
            'type_ids' => implode(',', $typeIds),
            'name' => $_POST['name'] ?? '',
            'manufacturer' => $_POST['manufacturer'] ?? '',
            'purchase_date' => $_POST['purchase_date'] ?: null,
            'warranty_expiry' => $_POST['warranty_expiry'] ?: null,
            'notes' => $_POST['notes'] ?? '',
            'resource_name' => $_POST['resource_name'] ?? '',
            'allocation_place' => $_POST['allocation_place'] ?? '',
            'cpu' => $_POST['cpu'] ?? '',
            'ram' => $_POST['ram'] ?? '',
            'storage' => $_POST['storage'] ?? '',
            'total_vms' => $_POST['total_vms'] ?? '',
            'license_windows_vcpu' => $_POST['license_windows_vcpu'] ?? '',
            'license_vmware' => $_POST['license_vmware'] ?? '',
            'license_systems' => $_POST['license_systems'] ?? '',
            'link_limit' => $_POST['link_limit'] ?? '',
            'ip_address' => $_POST['ip_address'] ?? '',
            'external_ips' => is_array($_POST['external_ips'] ?? null) ? implode("\n", array_filter($_POST['external_ips'])) : ($_POST['external_ips'] ?? ''),
            'internal_ips' => is_array($_POST['internal_ips'] ?? null) ? implode("\n", array_filter($_POST['internal_ips'])) : ($_POST['internal_ips'] ?? ''),
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
                if (empty($_POST['resource_name']) || empty($_POST['allocation_place'])) {
                    echo json_encode(['success' => false, 'message' => 'Os campos "Qual recurso" e "Onde alocar" são obrigatórios.']);
                    exit;
                }
            }
            $data['created_by_role'] = $user['role'];
            if (!$isAdmin) {
                $data['client_user_id'] = $clientId;
            }
            
            // If name is empty, use resource_name
            if (empty($data['name']) && !empty($data['resource_name'])) {
                $data['name'] = $data['resource_name'];
            }
            
            $fields = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $stmt = $pdo->prepare("INSERT INTO assets ($fields) VALUES ($placeholders)");
            $stmt->execute($data);
            $assetId = $pdo->lastInsertId();

            // Create ticket for resource request
            if (!$isAdmin) {
                try {
                    // Find category "Solicitação de Recurso"
                    $stmtCat = $pdo->prepare("SELECT id FROM ticket_categories WHERE slug = 'solicitacao-recurso' LIMIT 1");
                    $stmtCat->execute();
                    $cat = $stmtCat->fetch();
                    
                    if ($cat) {
                        $categoryId = (int)$cat['id'];
                        $subject = "Solicitação de Recurso: " . $data['resource_name'];
                        $description = "Nova solicitação de recurso via portal do cliente.\n\n" .
                                     "Recurso: " . $data['resource_name'] . "\n" .
                                     "Local: " . $data['allocation_place'] . "\n" .
                                     "CPU: " . $data['cpu'] . "\n" .
                                     "RAM: " . $data['ram'] . "\n" .
                                     "Storage: " . $data['storage'] . "\n" .
                                     "Notas: " . $data['notes'];
                        
                        $extra = [
                            'asset_id' => $assetId,
                            'resource_name' => $data['resource_name'],
                            'allocation_place' => $data['allocation_place']
                        ];
                        
                        ticket_create($pdo, $clientId, $categoryId, $subject, $description, $extra);
                    }
                } catch (Exception $e) {
                    // Log error but continue
                    error_log("Error creating ticket for asset request: " . $e->getMessage());
                }
            }
            
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
            exit;
        } else {
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Clientes não podem editar ativos diretamente. Entre em contato com o suporte.']);
                exit;
            }
            
            $id = (int)$_POST['id'];
            
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
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Clientes não podem excluir ativos diretamente.']);
            exit;
        }
        
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
