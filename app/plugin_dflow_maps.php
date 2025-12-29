<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');

// Get DFlow configuration
$dflowPlugin = plugin_get_by_name($pdo, 'dflow');
$config = $dflowPlugin['config'] ?? [];

render_header('Redes · Hub Central', $user);
?>

<div class="hub-container">
    <!-- Hub Header -->
    <div class="hub-header">
        <div class="hub-title-section">
            <h1 class="hub-title">Central de Redes & Observabilidade</h1>
            <p class="hub-subtitle">Análise profunda L2-L7, Correlação de Fluxos e Topologia em Tempo Real</p>
        </div>
        <div class="hub-context-selector">
            <div class="context-label">Contexto Global (VLAN):</div>
            <select id="global-vlan-selector" class="vlan-select">
                <option value="0">Todas as VLANs (Global)</option>
                <?php
                $vlans = $pdo->query("SELECT DISTINCT vlan FROM plugin_dflow_interfaces WHERE vlan > 0 ORDER BY vlan")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($vlans as $v):
                ?>
                    <option value="<?= $v ?>">VLAN <?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <div id="vlan-health-indicator" class="health-indicator" style="display:none;">
                <span class="health-dot"></span>
                <span class="health-text">Status: Normal</span>
            </div>
        </div>
        <div class="hub-status-badges">
            <div class="status-badge">
                <span class="status-dot active"></span>
                Engine: <?= h($config['capture_mode'] ?? 'libpcap') ?>
            </div>
            <?php if ($config['enable_dpi'] ?? true): ?>
                <div class="status-badge">DPI L7: ON</div>
            <?php endif; ?>
            <?php if ($config['correlate_snmp'] ?? false): ?>
                <div class="status-badge">SNMP Sync: ON</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hub Navigation Tabs -->
    <div class="hub-tabs">
        <button class="hub-tab active" data-tab="maps">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            Live Hosts (Force-Directed)
        </button>
        <button class="hub-tab" data-tab="topology">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg>
            L2 Topology
        </button>
        <button class="hub-tab" data-tab="interfaces">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/></svg>
            Interfaces
        </button>
        <button class="hub-tab" data-tab="hosts">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            Hosts
        </button>
        <button class="hub-tab" data-tab="bgp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            BGP Analyser
        </button>
        <button class="hub-tab" data-tab="snmp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="15" x2="4" y2="15"/></svg>
            SNMP Discovery
        </button>
        <button class="hub-tab" data-tab="flows">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            IP Flows
        </button>
        <button class="hub-tab" data-tab="geomap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            GeoMap (Threats)
        </button>
        <button class="hub-tab" data-tab="vlan">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            VLAN Analysis
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="hub-content">
        <!-- Topology Map Tab -->
        <div id="tab-maps" class="tab-pane active">
            <div class="card map-card" style="padding:0; position:relative;">
                <div id="3d-graph" style="width:100%; height:600px; background:#000d1a;"></div>
                <div class="map-controls">
                    <button class="btn small" onclick="resetCamera()">Reset Camera</button>
                    <button class="btn small" id="btn-freeze" onclick="toggleFreeze()">Freeze</button>
                    <button class="btn small" onclick="toggleAutoRotate()">Auto-Rotate</button>
                </div>
            </div>
        </div>

        <!-- L2 Topology Tab -->
        <div id="tab-topology" class="tab-pane">
            <div class="card map-card" style="padding:0; position:relative;">
                <div id="topo-graph" style="width:100%; height:600px; background:#000810;"></div>
                <div class="map-controls">
                    <button class="btn small" onclick="initTopologyGraph()">Reload Topology</button>
                </div>
            </div>
        </div>

        <!-- Interfaces Tab -->
        <div id="tab-interfaces" class="tab-pane">
            <iframe src="plugin_dflow_interfaces.php?embed=1" class="hub-iframe"></iframe>
        </div>

        <!-- Hosts Tab -->
        <div id="tab-hosts" class="tab-pane">
            <iframe src="plugin_dflow_hosts.php?embed=1" class="hub-iframe"></iframe>
        </div>

        <!-- BGP Tab (Iframe for now or partial) -->
        <div id="tab-bgp" class="tab-pane">
            <iframe src="plugin_bgpview.php?embed=1" class="hub-iframe"></iframe>
        </div>

        <!-- SNMP Tab -->
        <div id="tab-snmp" class="tab-pane">
            <iframe src="plugin_snmp.php?embed=1" class="hub-iframe"></iframe>
        </div>

        <!-- Flows Tab -->
        <div id="tab-flows" class="tab-pane">
            <iframe src="plugin_dflow_flows.php?embed=1" class="hub-iframe"></iframe>
        </div>

        <!-- GeoMap Tab -->
        <div id="tab-geomap" class="tab-pane">
             <div class="card" style="padding:0; height:600px; background:#111; position:relative;">
                <div id="world-map" style="width:100%; height:100%;"></div>
                <div class="threat-intel-overlay">
                    <div class="intel-box">
                        <div class="intel-title">Threat Intel Feeds</div>
                        <div class="intel-item"><span class="dot red"></span> AbuseIPDB: Active</div>
                        <div class="intel-item"><span class="dot orange"></span> Shodan: Connected</div>
                    </div>
                </div>
             </div>
        </div>
        <!-- VLAN Analysis Tab -->
        <div id="tab-vlan" class="tab-pane">
            <div class="vlan-container">
                <div class="vlan-sidebar">
                    <div class="card" style="margin-bottom:15px; padding:15px;">
                        <h3 style="margin:0 0 10px 0; font-size:14px;">VLANs Detectadas</h3>
                        <div id="vlan-list" class="vlan-list">
                            <div class="muted" style="font-size:12px; padding:10px;">Carregando VLANs...</div>
                        </div>
                    </div>
                </div>
                <div class="vlan-main">
                    <div class="card" style="padding:0; position:relative; height:600px; background:#000d1a;">
                        <div id="vlan-graph" style="width:100%; height:100%;"></div>
                        <div class="vlan-info-overlay" id="vlan-info" style="display:none;">
                            <div class="vlan-info-box">
                                <h4 id="vlan-info-title">VLAN Analysis</h4>
                                <div id="vlan-info-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/3d-force-graph"></script>
<script src="https://unpkg.com/d3-force"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab Management
    const tabs = document.querySelectorAll('.hub-tab');
    const panes = document.querySelectorAll('.tab-pane');
    const vlanSelector = document.getElementById('global-vlan-selector');

    let currentVlan = 0;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            tabs.forEach(t => t.classList.remove('active'));
            panes.forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('tab-' + target).classList.add('active');
            
            refreshTabContent(target, currentVlan);
        });
    });

    vlanSelector.addEventListener('change', function() {
        currentVlan = parseInt(this.value);
        console.log('Contexto alterado para VLAN:', currentVlan);
        
        updateVlanHealth(currentVlan);

        // Refresh active tab
        const activeTab = document.querySelector('.hub-tab.active').dataset.tab;
        refreshTabContent(activeTab, currentVlan);
    });

    function updateVlanHealth(vlan) {
        const indicator = document.getElementById('vlan-health-indicator');
        if (vlan === 0) {
            indicator.style.display = 'none';
            return;
        }

        fetch(`plugin_dflow_vlan_intelligence.php?vlan=${vlan}`)
            .then(res => res.json())
            .then(data => {
                indicator.style.display = 'flex';
                const dot = indicator.querySelector('.health-dot');
                const text = indicator.querySelector('.health-text');
                
                if (data.status === 'Normal') {
                    dot.style.background = '#27c4a8';
                    text.innerText = 'Status: Normal';
                } else {
                    dot.style.background = '#ff4d4d';
                    text.innerText = 'Status: Anomalia Detectada';
                    text.title = data.anomalies.join('\n');
                }
            });
    }

    function refreshTabContent(target, vlan) {
        if (target === 'maps') init3DGraph(vlan);
        if (target === 'topology') initTopologyGraph();
        if (target === 'geomap') initGeoMap();
        if (target === 'vlan') initVlanAnalysis(vlan);
        
        // Update IFrames with vlan context
        const iframeTabs = ['interfaces', 'hosts', 'bgp', 'snmp', 'flows'];
        if (iframeTabs.includes(target)) {
            const iframe = document.querySelector(`#tab-${target} iframe`);
            if (iframe) {
                const baseUrl = iframe.src.split('?')[0];
                iframe.src = `${baseUrl}?embed=1&vlan=${vlan}`;
            }
        }
    }

    // Initial Load
    init3DGraph(currentVlan);
});

let Graph;
const protoColors = {
    'HTTPS': '#00ffff',
    'HTTP': '#00ff00',
    'TLS': '#00ccff',
    'DNS': '#ffcc00',
    'SSH': '#ff00ff',
    'BitTorrent': '#ff4d4d',
    'QUIC': '#33cc33',
    'Unknown': '#888888'
};

function init3DGraph(vlan = 0) {
    const elem = document.getElementById('3d-graph');
    if (!elem) return;
    
    console.log(`Iniciando 3D Graph (VLAN ${vlan})...`);
    fetch(`plugin_dflow_maps_data.php?mode=hosts&vlan=${vlan}`)
        .then(res => res.json())
        .then(data => {
            if (!data.nodes || data.nodes.length === 0) {
                elem.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#666;">' +
                    '<i style="font-size:40px;margin-bottom:10px;">📡</i>' +
                    '<span>Nenhum tráfego real detectado pelo DFLOW para este contexto.</span>' +
                    '<small style="margin-top:5px;opacity:0.6;">Aguardando captura de fluxos em tempo real...</small>' +
                    '</div>';
                return;
            }
            
            // Se o gráfico já existe, limpa para forçar reload com novo contexto
            elem.innerHTML = '';

            Graph = ForceGraph3D()(elem)
                .graphData(data)
                .nodeLabel(n => `<div class="node-tip"><b>Host: ${n.label}</b><br>IP: ${n.id}</div>`)
                .nodeAutoColorBy('group')
                .nodeVal('val')
                // Force-Directed Graph Engine configuration
                .d3Force('charge', d3.forceManyBody().strength(-120))
                .d3Force('link', d3.forceLink().distance(link => {
                    const b = typeof link.bytes === 'number' ? link.bytes : 1;
                    return 150 / (Math.log(b + 1) || 1);
                }))
                .d3Force('center', d3.forceCenter())
                // Links visuais derivados de FLOW real
                .linkWidth(l => l.thickness)
                .linkColor(l => protoColors[l.l7_proto] || '#888888')
                .linkDirectionalParticles("value") // PPS
                .linkDirectionalParticleSpeed(d => d.value * 0.002)
                .linkOpacity(l => l.opacity) // RTT
                .linkLabel(l => `
                    <div class="node-tip">
                        <b>${l.source.id} → ${l.target.id}</b><br>
                        Protocolo: ${l.l7_proto}<br>
                        Volume: ${(l.bytes / 1024).toFixed(1)} KB<br>
                        Pacotes: ${l.packets}<br>
                        RTT: ${l.rtt > 0 ? l.rtt.toFixed(2) + ' ms' : 'N/A'}
                    </div>
                `)
                .onNodeClick(node => {
                    // Navegação encadeada: Node -> Host Detail
                    const vlan = document.getElementById('global-vlan-selector').value;
                    window.open(`plugin_dflow_hosts.php?search=${node.id}&vlan=${vlan}`, '_blank');
                })
                .backgroundColor('#000d1a')
                .showNavInfo(false);
        });
}

let TopoGraph;
function initTopologyGraph() {
    const elem = document.getElementById('topo-graph');
    if (!elem) return;
    if (elem.childElementCount > 0) {
        // Just refresh data
        fetch('plugin_dflow_maps_data.php?mode=topology')
            .then(res => res.json())
            .then(data => TopoGraph.graphData(data));
        return;
    }

    fetch('plugin_dflow_maps_data.php?mode=topology')
        .then(res => res.json())
        .then(data => {
            TopoGraph = ForceGraph3D()(elem)
                .graphData(data)
                .nodeLabel(n => `<div class="node-tip"><b>Device: ${n.label}</b></div>`)
                .nodeColor(n => n.group === 'switch' ? '#00ffff' : '#ffcc00')
                .linkLabel(l => l.label)
                .linkDirectionalArrowLength(3.5)
                .linkDirectionalArrowRelPos(1)
                .backgroundColor('#000810')
                .showNavInfo(false);
        });
}

function resetCamera() { if(Graph) Graph.cameraPosition({ x: 0, y: 0, z: 1000 }, { x: 0, y: 0, z: 0 }, 2000); }

let isFrozen = false;
function toggleFreeze() {
    isFrozen = !isFrozen;
    const btn = document.getElementById('btn-freeze');
    if (Graph) {
        if (isFrozen) {
            Graph.pauseAnimation();
            btn.innerText = 'Resume';
            btn.style.background = 'rgba(255, 165, 0, 0.3)';
        } else {
            Graph.resumeAnimation();
            btn.innerText = 'Freeze';
            btn.style.background = '';
        }
    }
}

let rotate = false;
function toggleAutoRotate() { 
    rotate = !rotate;
    if(Graph) {
        let angle = 0;
        const r = setInterval(() => {
            if(!rotate) { clearInterval(r); return; }
            angle += Math.PI / 300;
            Graph.cameraPosition({
                x: 1000 * Math.sin(angle),
                z: 1000 * Math.cos(angle)
            });
        }, 10);
    }
}

let worldMap;
function initGeoMap() {
    const elem = document.getElementById('world-map');
    if (!elem || worldMap) return;

    worldMap = L.map('world-map', {
        center: [20, 0],
        zoom: 2,
        zoomControl: true,
        attributionControl: false
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19
    }).addTo(worldMap);

    fetch('plugin_dflow_geomap_data.php')
        .then(res => res.json())
        .then(data => {
            data.forEach(t => {
                const marker = L.circleMarker([t.lat, t.lng], {
                    radius: t.score > 0 ? Math.sqrt(t.score) * 2 + 5 : 6,
                    fillColor: t.color,
                    color: t.color,
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.6
                }).addTo(worldMap);

                let popupContent = `
                    <div style="min-width:220px; font-family: 'Inter', sans-serif;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <b style="font-size:14px; color:${t.color}">${t.ip}</b>
                            <span class="badge" style="background:${t.color}22; color:${t.color}; font-size:10px; border:1px solid ${t.color}44;">${t.threat}</span>
                        </div>
                        <div style="font-size:12px; line-height:1.6;">
                            <b>Localização:</b> ${t.city || 'Desconhecido'}, ${t.country || 'Desconhecido'}<br>
                            ${t.score > 0 ? `<b>Score de Risco:</b> <span style="color:${t.color}">${t.score}%</span><br>` : ''}
                            ${t.ports && t.ports.length > 0 ? `<b>Portas Abertas:</b> <span style="color:#ffec3d">${t.ports.join(', ')}</span><br>` : ''}
                            ${t.traffic && t.traffic.length > 0 ? `<b>Tráfego Recente:</b> <span style="color:#27c4a8">${t.traffic.join(', ')}</span><br>` : ''}
                            <hr style="margin:8px 0; border:0; border-top:1px solid #333">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <small style="color:#888">Visto em: ${t.last_seen}</small>
                                <a href="plugin_dflow_flows.php?search=${t.ip}" style="color:var(--primary); text-decoration:none; font-size:11px; font-weight:600;">Ver Fluxos →</a>
                            </div>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent);
            });
        });
}

let VlanGraph;
function initVlanAnalysis() {
    const listElem = document.getElementById('vlan-list');
    if (!listElem) return;

    fetch('plugin_dflow_vlan_data.php')
        .then(res => res.json())
        .then(data => {
            listElem.innerHTML = '';
            data.forEach(v => {
                const item = document.createElement('div');
                item.className = 'vlan-item';
                item.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color:#00ffff;">VLAN ${v.vlan}</span>
                        <span class="badge" style="font-size:10px;">${v.unique_hosts} Hosts</span>
                    </div>
                    <div style="font-size:11px; color:var(--muted); margin-top:4px;">
                        ${(v.total_bytes / 1024 / 1024).toFixed(2)} MB | ${v.total_flows} Fluxos
                    </div>
                    <div style="font-size:10px; color:#27c4a8; margin-top:4px;">
                        RTT Médio: ${v.avg_rtt ? v.avg_rtt.toFixed(2) : 'N/A'} ms
                    </div>
                    <div style="margin-top:6px;">
                        ${v.l7_metrics.map(m => `<span class="tag-proto" style="font-size:9px; margin-right:4px; background:rgba(0,255,255,0.1); color:#00ffff;">${m.l7_proto}</span>`).join('')}
                    </div>
                `;
                item.onclick = () => {
                    document.querySelectorAll('.vlan-item').forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                    loadVlanGraph(v.vlan, v);
                };
                listElem.appendChild(item);
            });
            
            if (data.length > 0) {
                const firstItem = listElem.firstChild;
                firstItem.classList.add('active');
                loadVlanGraph(data[0].vlan, data[0]);
            }
        });
}

function loadVlanGraph(vlanId, vlanMeta) {
    const elem = document.getElementById('vlan-graph');
    if (!elem) return;
    
    const infoBox = document.getElementById('vlan-info');
    const infoTitle = document.getElementById('vlan-info-title');
    const infoContent = document.getElementById('vlan-info-content');

    // Fetch deep intelligence for this VLAN
    fetch(`plugin_dflow_vlan_intelligence.php?vlan=${vlanId}`)
        .then(res => res.json())
        .then(intel => {
            infoTitle.innerText = `VLAN ${vlanId} Intelligence`;
            let content = `
                <div style="margin-bottom:10px; border-bottom:1px solid #333; padding-bottom:5px;">
                    <b>Status:</b> <span style="color:${intel.status === 'Normal' ? '#27c4a8' : '#ff4d4d'}">${intel.status}</span><br>
                    <b>Traffic (Last Hour):</b> ${(intel.current.bytes / 1024 / 1024).toFixed(2)} MB<br>
                    ${intel.baseline ? `<b>Baseline:</b> ${(intel.baseline.avg_bytes / 1024 / 1024).toFixed(2)} MB` : '<i>Baseline ainda não calculado</i>'}
                </div>
            `;

            if (intel.anomalies.length > 0) {
                content += `<div style="color:#ff4d4d; font-size:11px; margin-bottom:10px;"><b>Anomalias:</b><br>${intel.anomalies.join('<br>')}</div>`;
            }

            content += `
                <div style="font-size:11px; margin-bottom:10px;">
                    <b>Top Protocols (L7):</b><br>
                    ${vlanMeta.l7_metrics.map(m => `• ${m.l7_proto}: ${(m.traffic / 1024).toFixed(1)} KB`).join('<br>')}
                </div>
                <div style="font-size:11px;">
                    <b>Top ASNs:</b><br>
                    ${intel.top_asns.map(a => `• AS${a.asn}: ${(a.traffic / 1024 / 1024).toFixed(2)} MB`).join('<br>')}
                </div>
            `;
            infoContent.innerHTML = content;
            infoBox.style.display = 'block';
        });

    // Clear previous graph
    elem.innerHTML = '';
    
    fetch(`plugin_dflow_maps_data.php?vlan=${vlanId}`)
        .then(res => res.json())
        .then(data => {
            VlanGraph = ForceGraph3D()(elem)
                .graphData(data)
                .nodeLabel(n => `<div class="node-tip"><b>Host: ${n.label}</b><br>IP: ${n.id}</div>`)
                .nodeAutoColorBy('group')
                .nodeVal('val')
                .linkWidth(l => l.thickness)
                .linkColor(l => protoColors[l.l7_proto] || '#888888')
                .linkDirectionalParticles("value")
                .linkDirectionalParticleSpeed(d => d.value * 0.002)
                .linkOpacity(l => l.opacity)
                .onNodeClick(node => {
                    window.open(`plugin_dflow_hosts.php?search=${node.id}&vlan=${vlanId}`, '_blank');
                })
                .backgroundColor('#000d1a')
                .showNavInfo(false);
        });
}
</script>

<style>
.hub-container { display: flex; flex-direction: column; gap: 20px; }
.hub-header { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.hub-title { font-size: 24px; font-weight: 800; margin: 0; }
.hub-subtitle { color: var(--muted); margin: 5px 0 0 0; font-size: 14px; }
.hub-status-badges { display: flex; gap: 10px; }
.status-badge { background: rgba(255,255,255,0.05); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.status-dot { width: 6px; height: 6px; border-radius: 50%; background: #444; }
.status-dot.active { background: var(--primary); box-shadow: 0 0 8px var(--primary); }

.hub-tabs { display: flex; gap: 5px; border-bottom: 1px solid var(--border); }
.hub-tab { background: transparent; border: none; padding: 12px 20px; color: var(--muted); font-size: 13px; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.hub-tab:hover { color: var(--text); background: rgba(255,255,255,0.02); }
.hub-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: rgba(39,196,168,0.05); }

.hub-content { min-height: 600px; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

.hub-context-selector { display: flex; align-items: center; gap: 15px; margin-right: auto; padding-left: 20px; border-left: 1px solid var(--border); }
.context-label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.vlan-select { background: rgba(0,255,255,0.05); border: 1px solid rgba(0,255,255,0.2); color: #00ffff; border-radius: 4px; padding: 4px 10px; font-size: 12px; font-weight: 600; outline: none; cursor: pointer; }
.vlan-select:hover { border-color: #00ffff; background: rgba(0,255,255,0.1); }
.vlan-select option { background: #000d1a; color: #fff; }

.health-indicator { display: flex; align-items: center; gap: 8px; padding: 4px 12px; background: rgba(255,255,255,0.03); border-radius: 4px; }
.health-dot { width: 8px; height: 8px; border-radius: 50%; background: #27c4a8; box-shadow: 0 0 10px currentColor; }
.health-text { font-size: 11px; font-weight: 700; color: var(--text); }

.hub-iframe { width: 100%; height: 700px; border: none; border-radius: 12px; background: var(--panel); }
.map-controls { position: absolute; bottom: 20px; left: 20px; display: flex; gap: 10px; z-index: 10; }
.node-tip { background: rgba(0,0,0,0.8); padding: 5px 10px; border-radius: 4px; font-size: 12px; color: white; border: 1px solid rgba(255,255,255,0.2); }

.threat-intel-overlay { position: absolute; top: 20px; right: 20px; z-index: 1000; }
.intel-box { background: rgba(0,0,0,0.85); border: 1px solid var(--border); border-radius: 8px; padding: 15px; min-width: 180px; backdrop-filter: blur(4px); }
.intel-title { font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; letter-spacing: 0.5px; }
.intel-item { font-size: 12px; color: white; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
.dot { width: 6px; height: 6px; border-radius: 50%; }
.dot.red { background: #ff4d4f; box-shadow: 0 0 6px #ff4d4f; }
.dot.orange { background: #faad14; box-shadow: 0 0 6px #faad14; }

/* VLAN Analysis Styles */
.vlan-container { display: grid; grid-template-columns: 250px 1fr; gap: 20px; }
.vlan-list { display: flex; flex-direction: column; gap: 8px; max-height: 500px; overflow-y: auto; }
.vlan-item { background: rgba(255,255,255,0.03); border: 1px solid var(--border); padding: 12px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.vlan-item:hover { background: rgba(255,255,255,0.08); border-color: var(--primary); }
.vlan-item.active { border-color: var(--primary); background: rgba(39,196,168,0.05); }

.vlan-info-overlay { position: absolute; top: 20px; left: 20px; z-index: 100; pointer-events: none; }
.vlan-info-box { background: rgba(0,0,0,0.85); border: 1px solid var(--border); border-radius: 8px; padding: 15px; min-width: 200px; backdrop-filter: blur(4px); }
.vlan-info-box h4 { margin: 0 0 5px 0; color: var(--primary); font-size: 14px; }
</style>

<?php render_footer(); ?>
