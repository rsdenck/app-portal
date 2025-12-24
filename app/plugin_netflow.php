<?php

require_once __DIR__ . '/../includes/bootstrap.php';
// netflow_api.php is already included in bootstrap.php

$user = require_login();

// Get plugin config
$plugin = plugin_get_by_name($pdo, 'netflow');
if (!$plugin || !$plugin['is_active']) {
    render_header('Netflow API', $user);
    echo '<div class="alert alert-warning">Plugin Netflow não está ativo ou configurado.</div>';
    render_footer();
    exit;
}

$client = netflow_get_client($plugin['config']);
$test = $client->testConnection();

render_header('Netflow API Dashboard', $user);
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-dark text-white">
                <div class="card-header border-bottom border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bar-chart mr-2"></i>Status da Conexão Netflow</h5>
                    <span class="badge <?= isset($test['error']) ? 'badge-danger' : 'badge-success' ?>">
                        <?= isset($test['error']) ? 'Desconectado' : 'Conectado' ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>URL da API:</strong> <code><?= h($plugin['config']['url'] ?? 'Não definida') ?></code></p>
                            <p><strong>Autenticação:</strong> API Key (Bearer Token)</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Resultado do Teste:</strong></p>
                            <pre class="bg-black p-3 rounded text-success"><?= h(print_r($test, true)) ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!isset($test['error'])): ?>
    <div class="row">
        <!-- Aqui entrariam os dados reais do Netflow -->
        <div class="col-md-4">
            <div class="card bg-dark text-white mb-4">
                <div class="card-body text-center">
                    <h2 class="text-primary"><i class="fas fa-exchange-alt"></i></h2>
                    <h4>Fluxos Ativos</h4>
                    <p class="h2">--</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white mb-4">
                <div class="card-body text-center">
                    <h2 class="text-success"><i class="fas fa-arrow-down"></i></h2>
                    <h4>Download</h4>
                    <p class="h2">0.00 Mbps</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white mb-4">
                <div class="card-body text-center">
                    <h2 class="text-danger"><i class="fas fa-arrow-up"></i></h2>
                    <h4>Upload</h4>
                    <p class="h2">0.00 Mbps</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>



