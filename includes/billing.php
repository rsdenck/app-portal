<?php

declare(strict_types=1);

/** @var PDO $pdo */

function billing_ensure_schema(PDO $pdo): void
{
    // Tabelas já criadas via script setup_billing.php
}

/**
 * Verifica e marca faturas atrasadas como inadimplentes
 */
function billing_check_overdue_invoices(PDO $pdo): int
{
    $stmt = $pdo->prepare("UPDATE billing_invoices SET status = 'overdue' WHERE status = 'issued' AND due_date < CURDATE()");
    $stmt->execute();
    return $stmt->rowCount();
}

function billing_get_account(PDO $pdo, int $clientUserId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM billing_accounts WHERE client_user_id = ?');
    $stmt->execute([$clientUserId]);
    $account = $stmt->fetch();
    return $account ?: null;
}

function billing_ensure_account(PDO $pdo, int $clientUserId): array
{
    $account = billing_get_account($pdo, $clientUserId);
    if (!$account) {
        $stmt = $pdo->prepare('INSERT INTO billing_accounts (client_user_id, balance) VALUES (?, 0.00)');
        $stmt->execute([$clientUserId]);
        return billing_get_account($pdo, $clientUserId);
    }
    return $account;
}

/**
 * Extrai o valor numérico de uma string (ex: "4 vCPU" -> 4, "8 GB" -> 8)
 */
function billing_parse_numeric($value): float
{
    if (empty($value)) return 0.0;
    if (is_numeric($value)) return (float)$value;
    
    // Remove tudo que não é número ou ponto/vírgula
    $cleaned = preg_replace('/[^0-9,.]/', '', (string)$value);
    $cleaned = str_replace(',', '.', $cleaned);
    
    return (float)$cleaned;
}

/**
 * Retorna os preços configurados para cada recurso
 */
function billing_get_prices(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM billing_prices');
    $prices = [];
    while ($row = $stmt->fetch()) {
        $prices[$row['resource_key']] = (float)$row['price_per_unit'];
    }
    return $prices;
}

/**
 * Calcula o faturamento de um cliente baseado em seus ativos
 */
function billing_calculate_client_assets(PDO $pdo, int $clientUserId): array
{
    $prices = billing_get_prices($pdo);
    
    // Se não houver preços, não calcula nada
    if (empty($prices)) return [];

    $stmt = $pdo->prepare('SELECT * FROM assets WHERE client_user_id = ?');
    $stmt->execute([$clientUserId]);
    $assets = $stmt->fetchAll();
    
    $items = [];
    $total = 0.0;

    foreach ($assets as $asset) {
        // vCPU
        $vcpu = billing_parse_numeric($asset['cpu']);
        if ($vcpu > 0 && isset($prices['vcpu'])) {
            $unitPrice = $prices['vcpu'];
            $cost = $vcpu * $unitPrice;
            $items[] = [
                'description' => "vCPU Alocada: $vcpu vCPU",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $vcpu,
                'unit' => 'vCPU',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'vcpu'
            ];
            $total += $cost;
        }

        // RAM
        $ram = billing_parse_numeric($asset['ram']);
        if ($ram > 0 && isset($prices['ram'])) {
            $unitPrice = $prices['ram'];
            $cost = $ram * $unitPrice;
            $items[] = [
                'description' => "Memória RAM: $ram GB",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $ram,
                'unit' => 'GB',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'ram'
            ];
            $total += $cost;
        }

        // Storage
        $storage = billing_parse_numeric($asset['storage']);
        if ($storage > 0 && isset($prices['storage'])) {
            $unitPrice = $prices['storage'];
            $cost = $storage * $unitPrice;
            $items[] = [
                'description' => "Armazenamento: $storage GB",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $storage,
                'unit' => 'GB',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'storage'
            ];
            $total += $cost;
        }

        // VMs
        $vms = (int)billing_parse_numeric($asset['total_vms']);
        if ($vms > 0 && isset($prices['vm'])) {
            $unitPrice = $prices['vm'];
            $cost = $vms * $unitPrice;
            $items[] = [
                'description' => "Instâncias/VMs: $vms unidades",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $vms,
                'unit' => 'VM',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'vm'
            ];
            $total += $cost;
        }

        // Licença Windows
        $licWin = billing_parse_numeric($asset['license_windows_vcpu']);
        if ($licWin > 0 && isset($prices['license_windows'])) {
            $unitPrice = $prices['license_windows'];
            $cost = $licWin * $unitPrice;
            $items[] = [
                'description' => "Licença Windows: $licWin vCPU",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $licWin,
                'unit' => 'vCPU',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'license'
            ];
            $total += $cost;
        }

        // Licença VMware
        if (!empty($asset['license_vmware']) && isset($prices['license_vmware'])) {
            $unitPrice = $prices['license_vmware'];
            $items[] = [
                'description' => "Licença VMware",
                'amount' => $unitPrice,
                'unit_price' => $unitPrice,
                'quantity' => 1,
                'unit' => 'Un',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'license'
            ];
            $total += $unitPrice;
        }

        // Banda/Link
        $bandwidth = billing_parse_numeric($asset['link_limit']);
        if ($bandwidth > 0 && isset($prices['bandwidth'])) {
            $unitPrice = $prices['bandwidth'];
            $cost = $bandwidth * $unitPrice;
            $items[] = [
                'description' => "Banda Contratada: $bandwidth Mbps",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $bandwidth,
                'unit' => 'Mbps',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'rede'
            ];
            $total += $cost;
        }

        // IPs Externos
        if (!empty($asset['external_ips']) && isset($prices['external_ip'])) {
            $ips = array_filter(explode("\n", $asset['external_ips']));
            $count = count($ips);
            if ($count > 0) {
                $unitPrice = $prices['external_ip'];
                $cost = $count * $unitPrice;
                $items[] = [
                    'description' => "IPs Externos: $count unidades",
                    'amount' => $cost,
                    'unit_price' => $unitPrice,
                    'quantity' => $count,
                    'unit' => 'IP',
                    'asset_id' => $asset['id'],
                    'asset_name' => $asset['name'],
                    'type' => 'rede'
                ];
                $total += $cost;
            }
        }

        // Licença Go Global
        $licGoGlobal = billing_parse_numeric($asset['license_systems']);
        if ($licGoGlobal > 0 && isset($prices['license_goglobal'])) {
            $unitPrice = $prices['license_goglobal'];
            $cost = $licGoGlobal * $unitPrice;
            $items[] = [
                'description' => "Licença Go Global: $licGoGlobal unidades",
                'amount' => $cost,
                'unit_price' => $unitPrice,
                'quantity' => $licGoGlobal,
                'unit' => 'Un',
                'asset_id' => $asset['id'],
                'asset_name' => $asset['name'],
                'type' => 'license'
            ];
            $total += $cost;
        }
    }

    return [
        'items' => $items,
        'total' => $total
    ];
}

function billing_add_item(PDO $pdo, int $clientUserId, string $description, float $amount, string $type, string $date, int $actionUserId): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO billing_items (client_user_id, description, amount, type, status, billing_date) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$clientUserId, $description, $amount, $type, 'pending', $date]);
        
        $details = "Item adicionado: $description - $type - R$ " . number_format($amount, 2, ',', '.');
        billing_log_history($pdo, $clientUserId, $actionUserId, 'item_added', $details);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function billing_log_history(PDO $pdo, int $clientUserId, int $actionUserId, string $action, string $details): void
{
    $stmt = $pdo->prepare('INSERT INTO billing_history (client_user_id, action_user_id, action, details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$clientUserId, $actionUserId, $action, $details]);
    
    // Log na auditoria global do sistema para segurança extra
    $user = ['id' => $actionUserId];
    if ($clientUserId !== $actionUserId) {
        $user['tenant_id'] = $clientUserId;
    }
    audit_log($pdo, $user, "billing.$action", ['details' => $details, 'target_client' => $clientUserId]);
}

function billing_get_items(PDO $pdo, int $clientUserId, ?string $status = null, ?int $month = null, ?int $year = null): array
{
    $sql = 'SELECT * FROM billing_items WHERE client_user_id = ?';
    $params = [$clientUserId];
    
    if ($status) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }

    if ($month) {
        $sql .= ' AND MONTH(billing_date) = ?';
        $params[] = $month;
    }

    if ($year) {
        $sql .= ' AND YEAR(billing_date) = ?';
        $params[] = $year;
    }
    
    $sql .= ' ORDER BY billing_date DESC, created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function billing_get_history(PDO $pdo, int $clientUserId, ?int $month = null, ?int $year = null): array
{
    $sql = '
        SELECT h.*, u.name as action_user_name 
        FROM billing_history h 
        JOIN users u ON u.id = h.action_user_id 
        WHERE h.client_user_id = ?
    ';
    $params = [$clientUserId];

    if ($month) {
        $sql .= ' AND MONTH(h.created_at) = ?';
        $params[] = $month;
    }

    if ($year) {
        $sql .= ' AND YEAR(h.created_at) = ?';
        $params[] = $year;
    }

    $sql .= ' ORDER BY h.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function billing_get_invoices(PDO $pdo, int $clientUserId): array
{
    $stmt = $pdo->prepare('SELECT * FROM billing_invoices WHERE client_user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$clientUserId]);
    return $stmt->fetchAll();
}

function billing_get_invoice(PDO $pdo, int $invoiceId, int $clientUserId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM billing_invoices WHERE id = ? AND client_user_id = ? LIMIT 1');
    $stmt->execute([$invoiceId, $clientUserId]);
    return $stmt->fetch() ?: null;
}

/**
 * Atualiza o status de uma fatura e sincroniza com o sistema
 */
function billing_update_invoice_status(PDO $pdo, int $invoiceId, string $status, int $actionUserId): bool
{
    $stmt = $pdo->prepare('UPDATE billing_invoices SET status = ? WHERE id = ?');
    if ($stmt->execute([$status, $invoiceId])) {
        // Logar no histórico
        $stmtInv = $pdo->prepare('SELECT client_user_id, invoice_number FROM billing_invoices WHERE id = ?');
        $stmtInv->execute([$invoiceId]);
        $inv = $stmtInv->fetch();
        
        if ($inv) {
            $details = "Status da fatura {$inv['invoice_number']} alterado para: $status";
            billing_log_history($pdo, (int)$inv['client_user_id'], $actionUserId, 'invoice_status_updated', $details);
        }
        return true;
    }
    return false;
}

/**
 * Gera uma fatura baseada nos itens pendentes do cliente
 */
function billing_generate_invoice(PDO $pdo, int $clientUserId, string $dueDate, int $actionUserId): ?int
{
    $items = billing_get_items($pdo, $clientUserId, 'pending');
    if (empty($items)) return null;

    $total = 0.0;
    foreach ($items as $item) {
        $total += (float)$item['amount'];
    }

    $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO billing_invoices (client_user_id, invoice_number, total_amount, status, due_date) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$clientUserId, $invoiceNumber, $total, 'issued', $dueDate]);
        $invoiceId = (int)$pdo->lastInsertId();

        $stmtUpdate = $pdo->prepare('UPDATE billing_items SET status = ?, invoice_id = ? WHERE client_user_id = ? AND status = ?');
        $stmtUpdate->execute(['invoiced', $invoiceId, $clientUserId, 'pending']);

        // Atualizar saldo da conta (incrementar com o valor da nova fatura)
        $stmtBalance = $pdo->prepare('UPDATE billing_accounts SET balance = balance + ? WHERE client_user_id = ?');
        $stmtBalance->execute([$total, $clientUserId]);

        $details = "Fatura gerada: $invoiceNumber - Total: R$ " . number_format($total, 2, ',', '.');
        billing_log_history($pdo, $clientUserId, $actionUserId, 'invoice_generated', $details);

        $pdo->commit();
        return $invoiceId;
    } catch (Exception $e) {
        $pdo->rollBack();
        return null;
    }
}

function billing_generate_invoice_html(array $invoice, array $items, array $client): string
{
    $total = 0;
    foreach ($items as $item) {
        $total += (float)$item['amount'];
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Fatura #<?= (int)$invoice['id'] ?></title>
        <style>
            body { font-family: sans-serif; color: #333; line-height: 1.6; }
            .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; }
            .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
            .logo { font-size: 24px; font-weight: bold; color: #007bff; }
            .details { margin-bottom: 20px; }
            .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            .table th { background-color: #f8f9fa; }
            .total { margin-top: 20px; text-align: right; font-size: 18px; font-weight: bold; }
            .footer { margin-top: 50px; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <div class="logo">PORTAL CLOUD</div>
                <div>
                    <strong>Fatura #<?= (int)$invoice['id'] ?></strong><br>
                    Data: <?= date('d/m/Y', strtotime($invoice['created_at'])) ?><br>
                    Vencimento: <?= date('d/m/Y', strtotime($invoice['due_date'])) ?>
                </div>
            </div>

            <div class="details">
                <strong>Cliente:</strong><br>
                <?= h($client['name']) ?><br>
                <?= h($client['email']) ?>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th style="text-align: right;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= h($item['description']) ?></td>
                        <td><?= date('d/m/Y', strtotime($item['billing_date'])) ?></td>
                        <td style="text-align: right;">R$ <?= number_format((float)$item['amount'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total">
                Total: R$ <?= number_format($total, 2, ',', '.') ?>
            </div>

            <div class="footer">
                Esta fatura é um documento gerado automaticamente pelo sistema de Billing.<br>
                Em caso de dúvidas, entre em contato com nosso suporte financeiro.
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function billing_contest_item(PDO $pdo, int $itemId, int $clientUserId, string $reason): bool
{
    $stmt = $pdo->prepare('SELECT * FROM billing_items WHERE id = ? AND client_user_id = ?');
    $stmt->execute([$itemId, $clientUserId]);
    $item = $stmt->fetch();
    
    if (!$item) return false;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE billing_items SET status = ? WHERE id = ?');
        $stmt->execute(['contested', $itemId]);
        
        $details = "Item contestado pelo cliente. Motivo: $reason. Item: " . $item['description'];
        billing_log_history($pdo, $clientUserId, $clientUserId, 'item_contested', $details);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
