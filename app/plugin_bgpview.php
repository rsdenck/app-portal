<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

// Proxy functionality to avoid CORS and connection errors from frontend
if (isset($_GET['proxy_url'])) {
    header('Content-Type: application/json');
    $url = $_GET['proxy_url'];
    
    // Basic validation to only allow specific APIs
    if (!str_starts_with($url, 'https://www.peeringdb.com/api/')) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid proxy URL. Only PeeringDB is allowed.']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DFlow-Network-Manager/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'CURL Error: ' . $curlError]);
        exit;
    }

    if ($httpCode !== 200) {
        http_response_code($httpCode);
        if (empty($response)) {
            echo json_encode(['status' => 'error', 'message' => "HTTP Error $httpCode: No response from API"]);
            exit;
        }
    }
    echo $response;
    exit;
}

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}
$plugin = plugin_get_by_name($pdo, 'bgpview');

if (!$plugin || !$plugin['is_active']) {
    header('Location: /');
    exit;
}

$isEmbed = isset($_GET['embed']) && $_GET['embed'] == '1';
$myAsnRaw = $_GET['temp_asn'] ?? ($plugin['config']['my_asn'] ?? '');
$myAsn = preg_replace('/\D/', '', $myAsnRaw); // Sanitize ASN for PHP use as well

if (!$isEmbed) {
    render_header('Network · Gestão de ASN & IX', $user);
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><link rel="stylesheet" href="/assets/style.css"></head><body class="embed-mode" style="background:transparent; padding:0; color:var(--text);">';
}
?>
<script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<style>
    #networkGraph {
        width: 100%;
        height: 600px;
        background-color: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 20px;
    }
    .table { color: var(--text); }
    .table th { color: var(--text); border-bottom: 1px solid var(--border); }
    .table td { color: var(--text); border-bottom: 1px solid var(--border); }
    .muted { color: var(--muted) !important; }
    
    /* New UI Elements */
    .info-box {
        margin-bottom: 15px;
        padding: 12px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        border-radius: 6px;
    }
    .info-box label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--muted);
        margin-bottom: 4px;
        font-weight: 600;
    }
    .info-box .value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text);
    }
    .primary-link { color: var(--primary); text-decoration: none; }
    .primary-link:hover { text-decoration: underline; }
    .badge.policy-open { background: rgba(39, 196, 168, 0.1); color: #27c4a8; }
    .badge.policy-selective { background: rgba(241, 196, 15, 0.1); color: #f1c40f; }
    .badge.policy-restrictive { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
</style>

<div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="display:flex;align-items:center;gap:12px">
            <div class="plugin-icon-wrapper" style="background:rgba(39, 196, 168, 0.1);color:var(--primary);width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:8px">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </div>
            <div>
                <div style="font-weight:700;font-size:18px">Network Manager</div>
                <div class="muted" style="font-size:12px">Análise de BGP, Prefixos e Internet Exchange</div>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <?php if ($myAsn): ?>
                <div class="badge" style="background:rgba(39, 196, 168, 0.2);color:var(--primary);border:1px solid var(--primary)">ASN Visualizado: AS<?= h($myAsn) ?></div>
            <?php endif; ?>
            <a href="/app/atendente_plugin_config.php?name=bgpview" class="btn btn-sm">Configurar</a>
        </div>
    </div>

    <div class="tabs" style="margin-top:20px">
        <button class="tab-btn active" onclick="switchTab(event, 'dashboard')">Dashboard</button>
        <button class="tab-btn" onclick="switchTab(event, 'peeringdb')">Rede & Política</button>
        <button class="tab-btn" onclick="switchTab(event, 'ix')">Pontos de Troca (IXP)</button>
        <button class="tab-btn" onclick="switchTab(event, 'peers')">Interconexões (Peers)</button>
        <button class="tab-btn" onclick="switchTab(event, 'map')">Mapa de Presença</button>
        <button class="tab-btn" onclick="switchTab(event, 'search')">Busca PeeringDB</button>
    </div>
</div>

<!-- DASHBOARD TAB -->
<div id="dashboard" class="tab-content active">
    <?php if (!$myAsn): ?>
        <div class="card" style="text-align:center;padding:40px">
            <div class="muted" style="margin-bottom:15px">Você ainda não definiu o seu ASN principal.</div>
            <a href="/app/atendente_plugin_config.php?name=bgpview" class="btn primary">Configurar Meu ASN</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <div class="card" id="asnInfo">
                    <div style="font-weight:600;margin-bottom:15px">Informações AS<?= h($myAsn) ?></div>
                    <div id="asnDetailsLoading">Carregando dados do PeeringDB...</div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div style="font-weight:600;margin-bottom:15px">Métricas de Peering (PeeringDB)</div>
                    <div class="config-grid">
                        <div class="config-tile no-hover">
                            <div class="config-tile-main">
                                <div class="config-tile-title" id="countIXP" style="font-size:24px;color:var(--primary)">-</div>
                                <div class="config-tile-desc">Presença em IXPs</div>
                            </div>
                        </div>
                        <div class="config-tile no-hover">
                            <div class="config-tile-main">
                                <div class="config-tile-title" id="countFac" style="font-size:24px;color:#27c4a8">-</div>
                                <div class="config-tile-desc">Data Centers (Fac)</div>
                            </div>
                        </div>
                        <div class="config-tile no-hover">
                            <div class="config-tile-main">
                                <div class="config-tile-title" id="trafficLevel" style="font-size:18px;color:var(--warning)">-</div>
                                <div class="config-tile-desc">Nível de Tráfego</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SEARCH TAB -->
<div id="search" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Busca no PeeringDB</div>
        <p class="muted">Pesquise por Nome da Rede ou ASN.</p>
        <form onsubmit="globalSearch(event)" style="display:flex;gap:10px;margin-bottom:20px">
            <input type="text" id="globalSearchQuery" class="input" placeholder="Ex: Google, 15169" style="flex:1">
            <button type="submit" class="btn primary">Pesquisar</button>
        </form>
        <div style="overflow-x:auto">
            <table class="table" id="searchTable" style="display:none">
                <thead>
                    <tr>
                        <th>Rede</th>
                        <th>ASN</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="searchList"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- IX TAB -->
<div id="ix" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Presença em Internet Exchange (IXP)</div>
        <p class="muted">Conexões públicas deste ASN via PeeringDB.</p>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>IXP</th>
                        <th>Localidade</th>
                        <th>Velocidade</th>
                        <th>IPv4</th>
                        <th>IPv6</th>
                    </tr>
                </thead>
                <tbody id="ixList">
                    <tr><td colspan="5" style="text-align:center">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PEERS TAB -->
<div id="peers" class="tab-content">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
            <div>
                <div style="font-weight:600">Interconexões (Peers Potenciais)</div>
                <div class="muted" style="font-size:12px">Outras redes presentes nos mesmos IXPs</div>
            </div>
            <div style="display:flex;gap:10px">
                <a href="https://lg.armazem.cloud/" target="_blank" class="btn btn-sm secondary">Looking Glass Armazem</a>
                <button onclick="loadPeers()" class="btn btn-sm primary">Atualizar Lista</button>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Rede</th>
                        <th>ASN</th>
                        <th>IXP Compartilhado</th>
                        <th>Política</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="peersList">
                    <tr><td colspan="5" style="text-align:center">Selecione um IXP ou carregue para ver peers...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MAP TAB -->
<div id="map" class="tab-content">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
            <div>
                <div style="font-weight:600">Mapa de Presença (PeeringDB)</div>
                <div class="muted" style="font-size:12px">Visualização das conexões em IXPs e Data Centers</div>
            </div>
            <button onclick="loadPeerGraph()" class="btn btn-sm primary">Atualizar Mapa</button>
        </div>
        <div id="networkGraph"></div>
    </div>
</div>

<!-- PEERINGDB TAB -->
<div id="peeringdb" class="tab-content">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
            <div>
                <div style="font-weight:600">Rede & Política de Peering</div>
                <div class="muted" style="font-size:12px">Informações detalhadas via PeeringDB API</div>
            </div>
            <a href="https://www.peeringdb.com/asn/<?= h($myAsn) ?>" target="_blank" class="btn btn-sm">Ver no PeeringDB.com</a>
        </div>
        <div id="peeringdbContent" style="color:var(--text)">
            <div style="text-align:center;padding:20px">Carregando dados...</div>
        </div>
    </div>
</div>

<script>
const rawAsn = '<?= $myAsn ?>';
const myAsn = rawAsn.replace(/\D/g, ''); // Garante que seja apenas números

async function fetchWithProxy(url) {
    const proxyBase = 'plugin_bgpview.php?proxy_url=';
    console.log('Fetching via proxy:', url);
    try {
        const response = await fetch(proxyBase + encodeURIComponent(url));
        const text = await response.text();
        
        try {
            return JSON.parse(text);
        } catch (parseError) {
            console.error('Failed to parse JSON:', text);
            throw new Error(`Invalid JSON response: ${text.substring(0, 100)}...`);
        }
    } catch (e) {
        console.error('Fetch error:', e);
        throw e;
    }
}

function switchTab(evt, tabName) {
    const contents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < contents.length; i++) {
        contents[i].classList.remove("active");
    }
    const buttons = document.getElementsByClassName("tab-btn");
    for (let i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove("active");
    }
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");

    if (myAsn) {
        if (tabName === 'dashboard') loadDashboard();
        if (tabName === 'ix') loadIX();
        if (tabName === 'peers') loadPeers();
        if (tabName === 'map') loadPeerGraph();
        if (tabName === 'peeringdb') loadPeeringDB();
    }
}

async function loadPeers() {
    const list = document.getElementById('peersList');
    if (!myAsn) return;
    list.innerHTML = '<tr><td colspan="5" style="text-align:center"><div class="spinner-border spinner-border-sm text-primary"></div> Buscando interconexões...</td></tr>';

    try {
        const netData = await fetchWithProxy(`https://www.peeringdb.com/api/net?asn=${myAsn}`);
        if (!netData.data || netData.data.length === 0) {
            list.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--danger)">ASN não encontrado no PeeringDB.</td></tr>';
            return;
        }
        const netId = netData.data[0].id;

        const ixData = await fetchWithProxy(`https://www.peeringdb.com/api/netixlan?net_id=${netId}`);
        if (!ixData.data || ixData.data.length === 0) {
            list.innerHTML = '<tr><td colspan="5" style="text-align:center">Nenhuma conexão IXP pública encontrada para listar peers.</td></tr>';
            return;
        }

        const maxIx = Math.min(ixData.data.length, 5);
        let peersHtml = '';
        let foundAsns = new Set();
        foundAsns.add(parseInt(myAsn));

        for (let i = 0; i < maxIx; i++) {
            const ix = ixData.data[i];
            const otherNets = await fetchWithProxy(`https://www.peeringdb.com/api/netixlan?ix_id=${ix.ix_id}&limit=30`);
            
            if (otherNets.data) {
                otherNets.data.forEach(peer => {
                    if (!foundAsns.has(peer.asn)) {
                        foundAsns.add(peer.asn);
                        peersHtml += `<tr>
                            <td><strong>${peer.name}</strong></td>
                            <td>AS${peer.asn}</td>
                            <td><span class="badge" style="background:rgba(52, 152, 219, 0.1);color:#3498db">${ix.name}</span></td>
                            <td><span class="badge" style="background:rgba(255,255,255,0.05)">${peer.operational_status || 'Ativo'}</span></td>
                            <td>
                                <div style="display:flex;gap:5px">
                                    <button onclick="setAsnAndReload(${peer.asn})" class="btn btn-sm">Analisar</button>
                                    <a href="https://bgp.he.net/AS${peer.asn}" target="_blank" class="btn btn-sm" title="Hurricane Electric">HE</a>
                                </div>
                            </td>
                        </tr>`;
                    }
                });
            }
        }

        list.innerHTML = peersHtml || '<tr><td colspan="5" style="text-align:center">Nenhum peer encontrado nos IXPs principais.</td></tr>';

    } catch (e) {
        list.innerHTML = `<tr><td colspan="5" style="color:var(--danger);text-align:center">Erro: ${e.message}</td></tr>`;
    }
}

async function loadPeeringDB() {
    const container = document.getElementById('peeringdbContent');
    if (!myAsn) return;
    
    container.innerHTML = '<div style="text-align:center;padding:20px"><div class="spinner-border text-primary" role="status"></div><div style="margin-top:10px">Buscando detalhes do ASN ' + myAsn + ' no PeeringDB...</div></div>';

    try {
        // Try exact ASN match
        const data = await fetchWithProxy(`https://www.peeringdb.com/api/net?asn=${myAsn}`);
        console.log('PeeringDB API Response:', data);
        
        if (data.data && data.data.length > 0) {
            const net = data.data[0];
            let html = `
                <div class="row" style="margin-top:20px">
                    <div class="col-md-6">
                        <div class="info-box">
                            <label>Nome da Rede</label>
                            <div class="value">${net.name}</div>
                        </div>
                        <div class="info-box">
                            <label>Aka / Nome Fantasia</label>
                            <div class="value">${net.aka || '-'}</div>
                        </div>
                        <div class="info-box">
                            <label>Website</label>
                            <div class="value"><a href="${net.website}" target="_blank" class="primary-link">${net.website}</a></div>
                        </div>
                        <div class="info-box">
                            <label>Política de Peering</label>
                            <div class="value"><span class="badge policy-${(net.policy_general || 'unknown').toLowerCase()}">${net.policy_general || 'Não informada'}</span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <label>Nível de Tráfego</label>
                            <div class="value">${net.traffic_level || '-'} (${net.traffic_range || 'N/A'})</div>
                        </div>
                        <div class="info-box">
                            <label>Prefixos (v4/v6)</label>
                            <div class="value">
                                <span style="color:var(--primary)">IPv4: ${net.prefixes_ipv4 || '0'}</span> | 
                                <span style="color:#27c4a8">IPv6: ${net.prefixes_ipv6 || '0'}</span>
                            </div>
                        </div>
                        <div class="info-box">
                            <label>Ratio de Tráfego</label>
                            <div class="value">${net.traffic_ratio || '-'}</div>
                        </div>
                        <div class="info-box">
                            <label>ID PeeringDB</label>
                            <div class="value">#${net.id}</div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:25px;padding:20px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid var(--border)">
                    <div style="font-weight:600;margin-bottom:15px;display:flex;align-items:center;gap:8px">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Ferramentas de Análise Externa para AS${myAsn}
                    </div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <a href="https://lg.armazem.cloud/" target="_blank" class="btn btn-sm secondary" style="background:#27c4a8;color:white;border:none">Looking Glass Armazem Cloud</a>
                        <a href="https://bgp.he.net/AS${myAsn}" target="_blank" class="btn btn-sm">Hurricane Electric</a>
                        <a href="https://radar.cloudflare.com/as${myAsn}" target="_blank" class="btn btn-sm">Cloudflare Radar</a>
                        <a href="https://www.peeringdb.com/asn/${myAsn}" target="_blank" class="btn btn-sm">Perfil no PeeringDB</a>
                        <a href="https://peeringdb.com/net/${net.id}" target="_blank" class="btn btn-sm">PeeringDB Net Object</a>
                    </div>
                </div>
            `;
            container.innerHTML = html;
        } else {
            // Fallback: search by name or just show error
            container.innerHTML = `
                <div style="text-align:center;padding:40px">
                    <div style="font-size:50px;margin-bottom:20px">🔍</div>
                    <div style="font-weight:700;font-size:18px;margin-bottom:10px">AS${myAsn} não localizado como "Network"</div>
                    <p class="muted" style="max-width:500px;margin:0 auto 25px auto">
                        O PeeringDB não retornou um objeto de rede direto para este ASN. Isso pode acontecer se o ASN for muito recente, estiver registrado sob outra entidade ou não for uma rede (ex: IXP).
                    </p>
                    <div style="display:flex;gap:10px;justify-content:center">
                        <button onclick="switchTab(event, 'search')" class="btn primary">Buscar Manualmente</button>
                        <a href="https://www.peeringdb.com/search?q=${myAsn}" target="_blank" class="btn">Ver no Site PeeringDB</a>
                        <a href="https://lg.armazem.cloud/" target="_blank" class="btn secondary">Ir para Looking Glass</a>
                    </div>
                    <div style="margin-top:30px;font-size:12px;color:var(--muted)">
                        Tente pesquisar pelo nome da empresa na aba "Busca PeeringDB".
                    </div>
                </div>`;
        }
    } catch (e) {
        console.error('PeeringDB Load Error:', e);
        container.innerHTML = `
            <div class="alert alert-danger" style="background:rgba(231, 76, 60, 0.1);border:1px solid #e74c3c;padding:20px;border-radius:8px;text-align:center">
                <div style="font-weight:700;color:#e74c3c;margin-bottom:10px">Erro de Comunicação com PeeringDB</div>
                <div style="font-size:13px;margin-bottom:15px">${e.message}</div>
                <button onclick="loadPeeringDB()" class="btn btn-sm" style="background:#e74c3c;color:white;border:none">Tentar Novamente</button>
            </div>`;
    }
}

async function globalSearch(event) {
    event.preventDefault();
    const query = document.getElementById('globalSearchQuery').value.trim();
    if (!query) return;
    const table = document.getElementById('searchTable');
    const list = document.getElementById('searchList');
    table.style.display = 'table';
    list.innerHTML = '<tr><td colspan="3" style="text-align:center">Pesquisando...</td></tr>';
    try {
        const data = await fetchWithProxy(`https://www.peeringdb.com/api/net?name__contains=${encodeURIComponent(query)}`);
        if (data.data) {
            let html = '';
            data.data.forEach(net => {
                html += `<tr>
                    <td><strong>${net.name}</strong></td>
                    <td>AS${net.asn}</td>
                    <td><button onclick="setAsnAndReload(${net.asn})" class="btn btn-sm">Selecionar</button></td>
                </tr>`;
            });
            list.innerHTML = html || '<tr><td colspan="3">Nenhum resultado encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="3">Erro na pesquisa.</td></tr>'; }
}

function setAsnAndReload(asn) {
    window.location.href = `?temp_asn=${asn}`;
}

let networkInstance = null;

async function loadPeerGraph() {
    const container = document.getElementById('networkGraph');
    if (!myAsn) {
        container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted)">Por favor, configure um ASN para visualizar o mapa.</div>';
        return;
    }
    
    container.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--muted)"><div class="spinner-border text-primary" style="margin-bottom:15px"></div>Gerando mapa de conexões para AS' + myAsn + '...</div>';

    try {
        const netData = await fetchWithProxy(`https://www.peeringdb.com/api/net?asn=${myAsn}`);
        if (!netData.data || netData.data.length === 0) {
            container.innerHTML = `
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:20px;text-align:center">
                    <div style="font-size:40px;margin-bottom:15px">🗺️</div>
                    <div style="font-weight:600;color:var(--danger)">ASN ${myAsn} não localizado no PeeringDB</div>
                    <p class="muted" style="font-size:12px;margin-top:10px">Não foi possível gerar o mapa pois a rede não está cadastrada.</p>
                    <a href="https://www.peeringdb.com/search?q=${myAsn}" target="_blank" class="btn btn-sm" style="margin-top:15px">Verificar no PeeringDB</a>
                </div>`;
            return;
        }
        const netId = netData.data[0].id;

        const ixData = await fetchWithProxy(`https://www.peeringdb.com/api/netixlan?net_id=${netId}`);
        const facData = await fetchWithProxy(`https://www.peeringdb.com/api/netfac?net_id=${netId}`);

        const nodes = new vis.DataSet([
            { id: 'me', label: `MEU AS\n(AS${myAsn})`, color: '#27c4a8', size: 40, font: { color: '#ffffff', weight: 'bold' } }
        ]);
        const edges = new vis.DataSet();

        let count = 0;
        if (ixData.data) {
            ixData.data.forEach(ix => {
                const id = `ix_${ix.ix_id}`;
                if (!nodes.get(id)) {
                    nodes.add({ id, label: `IX: ${ix.name}`, color: '#3498db', shape: 'box', font: { color: '#ffffff' } });
                }
                edges.add({ from: 'me', to: id, label: `${ix.speed}M`, color: '#3498db', font: { size: 10, align: 'middle' } });
                count++;
            });
        }

        if (facData.data) {
            facData.data.forEach(fac => {
                const id = `fac_${fac.fac_id}`;
                if (!nodes.get(id)) {
                    nodes.add({ id, label: `DC: ${fac.name}`, color: '#e67e22', shape: 'diamond', font: { color: '#ffffff' } });
                }
                edges.add({ from: 'me', to: id, color: '#e67e22', dashes: true });
                count++;
            });
        }

        if (count === 0) {
            container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted)">Nenhuma conexão (IXP/Fac) reportada no PeeringDB para este ASN.</div>';
            return;
        }

        const options = {
            nodes: { 
                font: { size: 12, face: 'Inter, sans-serif' },
                shadow: true
            },
            edges: {
                width: 2,
                shadow: true
            },
            physics: { 
                enabled: true,
                barnesHut: { gravitationalConstant: -2000, centralGravity: 0.3, springLength: 95 },
                stabilization: { iterations: 100 }
            }
        };

        if (networkInstance) networkInstance.destroy();
        networkInstance = new vis.Network(container, { nodes, edges }, options);

    } catch (e) {
        container.innerHTML = `<div style="color:#e74c3c;text-align:center;padding:20px">Erro ao gerar mapa: ${e.message}</div>`;
    }
}

async function loadDashboard() {
    if (!myAsn) return;
    const detailsDiv = document.getElementById('asnDetailsLoading');
    try {
        const data = await fetchWithProxy(`https://www.peeringdb.com/api/net?asn=${myAsn}`);
        if (data.data && data.data.length > 0) {
            const info = data.data[0];
            detailsDiv.innerHTML = `
                <div style="margin-bottom:12px;padding:10px;background:rgba(39, 196, 168, 0.05);border-radius:6px;border-left:3px solid var(--primary)">
                    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600">Entidade Registrada</div>
                    <div style="font-weight:700;font-size:15px;color:var(--text)">${info.name}</div>
                </div>
                <div style="margin-bottom:8px"><strong>Website:</strong> <a href="${info.website}" target="_blank" class="primary-link">${info.website}</a></div>
                <div style="margin-bottom:8px"><strong>Política:</strong> <span class="badge policy-${(info.policy_general || 'unknown').toLowerCase()}">${info.policy_general}</span></div>
                
                <div style="margin-top:20px;display:flex;flex-direction:column;gap:8px">
                    <a href="https://lg.armazem.cloud/" target="_blank" class="btn btn-sm secondary" style="width:100%;text-align:center;background:#27c4a8;color:white;border:none">Looking Glass Armazem</a>
                    <a href="https://bg.he.net/AS${myAsn}" target="_blank" class="btn btn-sm" style="width:100%;text-align:center">Hurricane Electric</a>
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:15px;text-align:center">PeeringDB ID: ${info.id}</div>
            `;
            document.getElementById('trafficLevel').textContent = info.traffic_level || 'N/A';

            // Counts
            const ixData = await fetchWithProxy(`https://www.peeringdb.com/api/netixlan?net_id=${info.id}`);
            const facData = await fetchWithProxy(`https://www.peeringdb.com/api/netfac?net_id=${info.id}`);
            
            document.getElementById('countIXP').textContent = ixData.data ? ixData.data.length : 0;
            document.getElementById('countFac').textContent = facData.data ? facData.data.length : 0;

        } else {
            detailsDiv.innerHTML = `
                <div style="text-align:center;padding:20px">
                    <div style="color:var(--danger);font-weight:600;margin-bottom:10px">ASN não encontrado</div>
                    <p class="muted" style="font-size:11px">Este ASN não possui registro de rede no PeeringDB.</p>
                    <button onclick="switchTab(event, 'search')" class="btn btn-sm primary" style="margin-top:10px">Buscar por Nome</button>
                </div>`;
        }
    } catch (e) {
        detailsDiv.innerHTML = `<div style="color:var(--danger);padding:20px;text-align:center">Erro: ${e.message}</div>`;
    }
}

async function loadIX() {
    const list = document.getElementById('ixList');
    list.innerHTML = '<tr><td colspan="5" style="text-align:center">Carregando...</td></tr>';
    try {
        const netData = await fetchWithProxy(`https://www.peeringdb.com/api/net?asn=${myAsn}`);
        if (netData.data && netData.data.length > 0) {
            const ixData = await fetchWithProxy(`https://www.peeringdb.com/api/netixlan?net_id=${netData.data[0].id}`);
            if (ixData.data) {
                let html = '';
                ixData.data.forEach(ix => {
                    html += `<tr>
                        <td><strong>${ix.name}</strong></td>
                        <td>${ix.city || '-'}</td>
                        <td>${ix.speed} Mbps</td>
                        <td>${ix.ipaddr4 || '-'}</td>
                        <td>${ix.ipaddr6 || '-'}</td>
                    </tr>`;
                });
                list.innerHTML = html || '<tr><td colspan="5" style="text-align:center">Nenhuma conexão IXP listada.</td></tr>';
            }
        }
    } catch (e) {
        list.innerHTML = `<tr><td colspan="5" style="color:var(--danger);text-align:center">Erro ao carregar.</td></tr>`;
    }
}

if (myAsn) loadDashboard();
</script>

<style>
.tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    color: var(--muted);
    cursor: pointer;
    font-weight: 600;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.tab-btn:hover { color: var(--text); }
.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.tab-content { display: none; margin-top: 20px; }
.tab-content.active { display: block; }
.no-hover { cursor: default !important; }
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.col-md-4 { width: 33.333%; }
.col-md-8 { width: 66.666%; }
.row { display: flex; gap: 18px; }
@media (max-width: 768px) {
    .row { flex-direction: column; }
    .col-md-4, .col-md-8 { width: 100%; }
}
</style>

<?php
if (!$isEmbed) {
    render_footer();
} else {
    echo '</body></html>';
}
?>



