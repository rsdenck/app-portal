<?php

require __DIR__ . '/includes/bootstrap.php';

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

$myAsn = $_GET['temp_asn'] ?? ($plugin['config']['my_asn'] ?? '');

render_header('Network · Gestão de ASN & IX', $user);
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
                <?php if (isset($_GET['temp_asn'])): ?>
                    <a href="plugin_bgpview.php" class="btn btn-sm btn-outline">Voltar para Meu ASN</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="/atendente_plugin_config.php?name=bgpview" class="btn btn-sm">Configurar</a>
        </div>
    </div>

    <div class="tabs" style="margin-top:20px">
        <button class="tab-btn active" onclick="switchTab(event, 'dashboard')">Dashboard</button>
        <button class="tab-btn" onclick="switchTab(event, 'prefixes')">Prefixos</button>
        <button class="tab-btn" onclick="switchTab(event, 'peers')">Peers</button>
        <button class="tab-btn" onclick="switchTab(event, 'upstreams')">Upstreams</button>
        <button class="tab-btn" onclick="switchTab(event, 'downstreams')">Downstreams</button>
        <button class="tab-btn" onclick="switchTab(event, 'ix')">IXPs</button>
        <button class="tab-btn" onclick="switchTab(event, 'map')">Mapa de Conexões</button>
        <button class="tab-btn" onclick="switchTab(event, 'search')">Busca Global</button>
        <button class="tab-btn" onclick="switchTab(event, 'tools')">Ferramentas</button>
    </div>
</div>

<!-- DASHBOARD TAB -->
<div id="dashboard" class="tab-content active">
    <?php if (!$myAsn): ?>
        <div class="card" style="text-align:center;padding:40px">
            <div class="muted" style="margin-bottom:15px">Você ainda não definiu o seu ASN principal.</div>
            <a href="/atendente_plugin_config.php?name=bgpview" class="btn primary">Configurar Meu ASN</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <div class="card" id="asnInfo">
                    <div style="font-weight:600;margin-bottom:15px">Informações AS<?= h($myAsn) ?></div>
                    <div id="asnDetailsLoading">Carregando...</div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div style="font-weight:600;margin-bottom:15px">Visão Geral de Tráfego</div>
                    <div class="config-grid">
                        <div class="config-tile no-hover">
                            <div class="config-tile-main">
                                <div class="config-tile-title" id="countIPv4" style="font-size:24px;color:var(--primary)">-</div>
                                <div class="config-tile-desc">Prefixos IPv4</div>
                            </div>
                        </div>
                        <div class="config-tile no-hover">
                            <div class="config-tile-main">
                                <div class="config-tile-title" id="countIPv6" style="font-size:24px;color:#27c4a8">-</div>
                                <div class="config-tile-desc">Prefixos IPv6</div>
                            </div>
                        </div>
                        <div class="config-tile no-hover">
                            <div class="config-tile-main">
                                <div class="config-tile-title" id="countPeers" style="font-size:24px;color:var(--warning)">-</div>
                                <div class="config-tile-desc">Total de Peers</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- PREFIXES TAB -->
<div id="prefixes" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Prefixos Anunciados</div>
        <div style="overflow-x:auto">
            <table class="table" id="prefixesTable">
                <thead>
                    <tr>
                        <th>Prefixo</th>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th>ROA</th>
                    </tr>
                </thead>
                <tbody id="prefixesList">
                    <tr><td colspan="4" style="text-align:center">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PEERS TAB -->
<div id="peers" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Vizinhos BGP (Peers)</div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>ASN</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>País</th>
                    </tr>
                </thead>
                <tbody id="peersList">
                    <tr><td colspan="4" style="text-align:center">Carregando...</td></tr>
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
                <div style="font-weight:600">Mapa de Conexões (ASN Peers)</div>
                <div class="muted" style="font-size:12px">Visualização gráfica das adjacências BGP de AS<?= h($myAsn) ?></div>
            </div>
            <button onclick="loadPeerGraph()" class="btn btn-sm primary">Atualizar Mapa</button>
        </div>
        <div id="networkGraph"></div>
        <div id="graphLegend" style="margin-top:15px;display:flex;gap:20px;font-size:12px;justify-content:center">
            <div style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;background:#27c4a8;border-radius:50%"></span> Seu ASN</div>
            <div style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;background:#3498db;border-radius:50%"></span> IPv4 Peer</div>
            <div style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;background:#e74c3c;border-radius:50%"></span> IPv6 Peer</div>
        </div>
    </div>
</div>

<!-- UPSTREAMS TAB -->
<div id="upstreams" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Upstreams (Fornecedores de Trânsito)</div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>ASN</th>
                        <th>Nome</th>
                        <th>País</th>
                    </tr>
                </thead>
                <tbody id="upstreamsList">
                    <tr><td colspan="3" style="text-align:center">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DOWNSTREAMS TAB -->
<div id="downstreams" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Downstreams (Clientes)</div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>ASN</th>
                        <th>Nome</th>
                        <th>País</th>
                    </tr>
                </thead>
                <tbody id="downstreamsList">
                    <tr><td colspan="3" style="text-align:center">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SEARCH TAB -->
<div id="search" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Busca Global BGPView</div>
        <p class="muted">Pesquise por Nome, ASN, IP ou Descrição.</p>
        <form onsubmit="globalSearch(event)" style="display:flex;gap:10px;margin-bottom:20px">
            <input type="text" id="globalSearchQuery" class="input" placeholder="Ex: Google, 15169, 8.8.8.8" style="flex:1">
            <button type="submit" class="btn primary">Pesquisar</button>
        </form>
        <div style="overflow-x:auto">
            <table class="table" id="searchTable" style="display:none">
                <thead>
                    <tr>
                        <th>Resultado</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
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
        <div style="font-weight:600;margin-bottom:15px">Internet Exchange Points (IXP)</div>
        <p class="muted">Pontos de troca de tráfego onde este ASN está presente.</p>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Localidade</th>
                        <th>IPv4</th>
                        <th>IPv6</th>
                    </tr>
                </thead>
                <tbody id="ixList">
                    <tr><td colspan="4" style="text-align:center">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TOOLS TAB -->
<div id="tools" class="tab-content">
    <div class="card">
        <div style="font-weight:600;margin-bottom:15px">Consultas Rápidas</div>
        <form onsubmit="quickSearch(event)" style="display:flex;gap:10px;margin-bottom:20px">
            <input type="text" id="quickQuery" class="input" placeholder="Digite ASN, IP ou Prefixo" style="flex:1">
            <button type="submit" class="btn primary">Consultar</button>
        </form>
        <div id="quickResult" style="display:none">
            <pre id="quickRaw" style="background:var(--bg);padding:15px;border-radius:8px;font-size:12px;overflow-x:auto;border:1px solid var(--border)"></pre>
        </div>
    </div>
</div>

<script>
const myAsn = '<?= $myAsn ?>';

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
        if (tabName === 'prefixes') loadPrefixes();
        if (tabName === 'peers') loadPeers();
        if (tabName === 'upstreams') loadUpstreams();
        if (tabName === 'downstreams') loadDownstreams();
        if (tabName === 'map') loadPeerGraph();
        if (tabName === 'ix') loadIX();
    }
}

async function loadUpstreams() {
    const list = document.getElementById('upstreamsList');
    list.innerHTML = '<tr><td colspan="3" style="text-align:center">Carregando...</td></tr>';
    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}/upstreams`);
        const data = await response.json();
        if (data.status === 'ok') {
            let html = '';
            data.data.ipv4_upstreams.forEach(p => {
                html += `<tr><td>AS${p.asn}</td><td>${p.name}</td><td>${p.country_code}</td></tr>`;
            });
            list.innerHTML = html || '<tr><td colspan="3">Nenhum upstream encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="3">Erro ao carregar.</td></tr>'; }
}

async function loadDownstreams() {
    const list = document.getElementById('downstreamsList');
    list.innerHTML = '<tr><td colspan="3" style="text-align:center">Carregando...</td></tr>';
    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}/downstreams`);
        const data = await response.json();
        if (data.status === 'ok') {
            let html = '';
            data.data.ipv4_downstreams.forEach(p => {
                html += `<tr><td>AS${p.asn}</td><td>${p.name}</td><td>${p.country_code}</td></tr>`;
            });
            list.innerHTML = html || '<tr><td colspan="3">Nenhum downstream encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="3">Erro ao carregar.</td></tr>'; }
}

async function globalSearch(event) {
    event.preventDefault();
    const query = document.getElementById('globalSearchQuery').value.trim();
    if (!query) return;
    const table = document.getElementById('searchTable');
    const list = document.getElementById('searchList');
    table.style.display = 'table';
    list.innerHTML = '<tr><td colspan="4" style="text-align:center">Pesquisando...</td></tr>';
    try {
        const response = await fetch(`https://api.bgpview.io/search?query_term=${encodeURIComponent(query)}`);
        const data = await response.json();
        if (data.status === 'ok') {
            let html = '';
            // Process ASNs
            if (data.data.asns) {
                data.data.asns.forEach(asn => {
                    html += `<tr>
                        <td><strong>AS${asn.asn}</strong></td>
                        <td><span class="badge" style="background:#3498db;color:#fff">ASN</span></td>
                        <td>${asn.name} - ${asn.description_short}</td>
                        <td><button onclick="setAsnAndReload(${asn.asn})" class="btn btn-sm">Ver Detalhes</button></td>
                    </tr>`;
                });
            }
            // Process IPs/Prefixes
            if (data.data.prefixes) {
                data.data.prefixes.forEach(p => {
                    html += `<tr>
                        <td><strong>${p.prefix}</strong></td>
                        <td><span class="badge" style="background:#27c4a8;color:#fff">Prefixo</span></td>
                        <td>${p.name} (${p.description})</td>
                        <td><button onclick="quickSearchManual('${p.prefix}')" class="btn btn-sm">Ver Raw</button></td>
                    </tr>`;
                });
            }
            list.innerHTML = html || '<tr><td colspan="4">Nenhum resultado encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="4">Erro na pesquisa.</td></tr>'; }
}

function setAsnAndReload(asn) {
    // In a real app we might want to update the config, but for now let's just use it in the UI session
    window.location.href = `?temp_asn=${asn}`;
}

function quickSearchManual(query) {
    document.getElementById('quickQuery').value = query;
    switchTab({ currentTarget: document.querySelector('[onclick*="tools"]') }, 'tools');
    quickSearch(new Event('submit'));
}

let networkInstance = null;

async function loadPeerGraph() {
    const container = document.getElementById('networkGraph');
    if (!myAsn) return;
    
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted)">Gerando mapa de conexões...</div>';

    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}/peers`);
        const data = await response.json();
        
        if (data.status !== 'ok') throw new Error('API Error');

        const nodes = new vis.DataSet([
            { id: parseInt(myAsn), label: `Meu AS${myAsn}`, color: '#27c4a8', size: 30, font: { color: '#ffffff' } }
        ]);
        const edges = new vis.DataSet();
        const seenAsns = new Set([parseInt(myAsn)]);

        // Process IPv4 Peers
        data.data.ipv4_peers.forEach(peer => {
            if (!seenAsns.has(peer.asn)) {
                nodes.add({ 
                    id: peer.asn, 
                    label: `AS${peer.asn}\n${peer.name.substring(0, 15)}...`, 
                    color: '#3498db',
                    title: `${peer.name} (${peer.country_code})`
                });
                seenAsns.add(peer.asn);
            }
            edges.add({ from: parseInt(myAsn), to: peer.asn, label: 'IPv4', color: { color: '#3498db', opacity: 0.5 } });
        });

        // Process IPv6 Peers
        data.data.ipv6_peers.forEach(peer => {
            if (!seenAsns.has(peer.asn)) {
                nodes.add({ 
                    id: peer.asn, 
                    label: `AS${peer.asn}\n${peer.name.substring(0, 15)}...`, 
                    color: '#e74c3c',
                    title: `${peer.name} (${peer.country_code})`
                });
                seenAsns.add(peer.asn);
            }
            edges.add({ from: parseInt(myAsn), to: peer.asn, label: 'IPv6', color: { color: '#e74c3c', opacity: 0.5 } });
        });

        const options = {
            nodes: {
                shape: 'dot',
                size: 20,
                font: { size: 12, face: 'Inter, sans-serif' },
                borderWidth: 2
            },
            edges: {
                width: 2,
                arrows: { to: { enabled: true, scaleFactor: 0.5 } }
            },
            physics: {
                forceAtlas2Based: {
                    gravitationalConstant: -100,
                    centralGravity: 0.01,
                    springLength: 150,
                    springConstant: 0.08
                },
                maxVelocity: 50,
                solver: 'forceAtlas2Based',
                timestep: 0.35,
                stabilization: { iterations: 150 }
            }
        };

        if (networkInstance) networkInstance.destroy();
        networkInstance = new vis.Network(container, { nodes, edges }, options);

    } catch (e) {
        container.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#e74c3c">Erro ao carregar mapa: ${e.message}</div>`;
    }
}

async function loadDashboard() {
    if (!myAsn) return;
    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}`);
        const data = await response.json();
        if (data.status === 'ok') {
            const info = data.data;
            document.getElementById('asnDetailsLoading').innerHTML = `
                <div style="margin-bottom:8px"><strong>Nome:</strong> ${info.name}</div>
                <div style="margin-bottom:8px"><strong>Entidade:</strong> ${info.description_short}</div>
                <div style="margin-bottom:8px"><strong>País:</strong> ${info.country_code}</div>
                <div style="margin-bottom:8px"><strong>Website:</strong> <a href="${info.website}" target="_blank">${info.website}</a></div>
                <div style="font-size:11px;color:var(--muted);margin-top:15px">Registrado via ${info.rir_name}</div>
            `;
        }
        
        // Load counts from prefixes and peers
        const prefRes = await fetch(`https://api.bgpview.io/asn/${myAsn}/prefixes`);
        const prefData = await prefRes.json();
        if (prefData.status === 'ok') {
            document.getElementById('countIPv4').textContent = prefData.data.ipv4_prefixes.length;
            document.getElementById('countIPv6').textContent = prefData.data.ipv6_prefixes.length;
        }

        const peerRes = await fetch(`https://api.bgpview.io/asn/${myAsn}/peers`);
        const peerData = await peerRes.json();
        if (peerData.status === 'ok') {
            document.getElementById('countPeers').textContent = peerData.data.ipv4_peers.length + peerData.data.ipv6_peers.length;
        }
    } catch (e) {
        console.error(e);
    }
}

async function loadPrefixes() {
    const list = document.getElementById('prefixesList');
    list.innerHTML = '<tr><td colspan="4" style="text-align:center">Carregando...</td></tr>';
    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}/prefixes`);
        const data = await response.json();
        if (data.status === 'ok') {
            let html = '';
            data.data.ipv4_prefixes.forEach(p => {
                html += `<tr>
                    <td><strong>${p.prefix}</strong></td>
                    <td>${p.name}</td>
                    <td><small>${p.description}</small></td>
                    <td>${p.roa_status}</td>
                </tr>`;
            });
            list.innerHTML = html || '<tr><td colspan="4">Nenhum prefixo encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="4">Erro ao carregar.</td></tr>'; }
}

async function loadPeers() {
    const list = document.getElementById('peersList');
    list.innerHTML = '<tr><td colspan="4" style="text-align:center">Carregando...</td></tr>';
    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}/peers`);
        const data = await response.json();
        if (data.status === 'ok') {
            let html = '';
            data.data.ipv4_peers.forEach(p => {
                html += `<tr>
                    <td>AS${p.asn}</td>
                    <td>${p.name}</td>
                    <td>IPv4</td>
                    <td>${p.country_code}</td>
                </tr>`;
            });
            list.innerHTML = html || '<tr><td colspan="4">Nenhum peer encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="4">Erro ao carregar.</td></tr>'; }
}

async function loadIX() {
    const list = document.getElementById('ixList');
    list.innerHTML = '<tr><td colspan="4" style="text-align:center">Carregando...</td></tr>';
    try {
        const response = await fetch(`https://api.bgpview.io/asn/${myAsn}/ixs`);
        const data = await response.json();
        if (data.status === 'ok') {
            let html = '';
            data.data.forEach(ix => {
                html += `<tr>
                    <td><strong>${ix.name}</strong></td>
                    <td>${ix.country_code} - ${ix.city}</td>
                    <td>${ix.ipv4_address || '-'}</td>
                    <td>${ix.ipv6_address || '-'}</td>
                </tr>`;
            });
            list.innerHTML = html || '<tr><td colspan="4">Nenhum IXP encontrado.</td></tr>';
        }
    } catch (e) { list.innerHTML = '<tr><td colspan="4">Erro ao carregar.</td></tr>'; }
}

async function quickSearch(event) {
    event.preventDefault();
    const query = document.getElementById('quickQuery').value.trim();
    if (!query) return;
    const resDiv = document.getElementById('quickResult');
    const raw = document.getElementById('quickRaw');
    resDiv.style.display = 'block';
    raw.textContent = 'Consultando...';
    try {
        let url = query.match(/^\d+$/) ? `https://api.bgpview.io/asn/${query}` : `https://api.bgpview.io/ip/${query}`;
        const response = await fetch(url);
        const data = await response.json();
        raw.textContent = JSON.stringify(data, null, 2);
    } catch (e) { raw.textContent = 'Erro na consulta.'; }
}

// Initial load
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
render_footer();
