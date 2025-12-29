<?php
require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_permission('billing.manage');

// Processar atualização de preços
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_prices') {
    foreach ($_POST['prices'] as $id => $price) {
        $stmt = $pdo->prepare("UPDATE billing_prices SET price_per_unit = ? WHERE id = ?");
        $stmt->execute([(float)$price, (int)$id]);
    }
    header("Location: /app/atendente_billing_prices.php?success=1");
    exit;
}

$stmt = $pdo->query("SELECT * FROM billing_prices ORDER BY category, resource_label");
$prices = $stmt->fetchAll();

render_header('Atendente · Tabela de Preços', $user);
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2>Tabela de Preços de Recursos</h2>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert success" style="margin-bottom:20px;padding:15px;background:#d4edda;color:#155724;border-radius:4px;border:1px solid #c3e6cb">
            Preços atualizados com sucesso!
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="update_prices">
        <table class="table">
            <thead>
                <tr>
                    <th>Recurso</th>
                    <th>Unidade</th>
                    <th>Categoria</th>
                    <th style="text-align:right">Preço por Unidade (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prices as $p): ?>
                    <tr>
                        <td><strong><?= h($p['resource_label']) ?></strong><br><small class="muted"><?= h($p['resource_key']) ?></small></td>
                        <td><?= h($p['unit_label']) ?></td>
                        <td><span class="tag"><?= h($p['category']) ?></span></td>
                        <td style="text-align:right">
                            <input type="number" name="prices[<?= $p['id'] ?>]" value="<?= $p['price_per_unit'] ?>" step="0.01" class="input" style="width:120px;text-align:right">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:20px;display:flex;justify-content:flex-end">
            <button type="submit" class="btn primary">Salvar Alterações</button>
        </div>
    </form>
</div>

<style>
.tag { padding: 2px 8px; border-radius: 4px; font-size: 11px; background: var(--bg-hover); border: 1px solid var(--border); }
</style>

<?php render_footer(); ?>
