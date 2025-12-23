<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

if ($user['role'] === 'cliente') {
    header('Location: /cliente_ativos.php');
    exit;
}
$bgpPlugin = plugin_get_by_name($pdo, 'bgpview');
$ipinfoPlugin = plugin_get_by_name($pdo, 'ipinfo');

if (!$bgpPlugin || !$bgpPlugin['is_active'] || !$ipinfoPlugin || !$ipinfoPlugin['is_active']) {
    header('Location: /');
    exit;
}

render_header('Cyber Threat Map · Threat Intelligence', $user);
?>

<link href="https://cesium.com/downloads/cesiumjs/releases/1.115/Build/Cesium/Widgets/widgets.css" rel="stylesheet">
<script src="https://cesium.com/downloads/cesiumjs/releases/1.115/Build/Cesium/Cesium.js"></script>

<style>
    :root {
        --active-green: #00ff00;
        --vuln-yellow: #ffff00;
        --threat-red: #ff0000;
        --trace-cyan: #00ffff;
        --flow-aqua: #00ffff;
        --bg-dark: #020205;
        --sidebar-bg: rgba(5, 5, 10, 0.95);
        --border-color: rgba(0, 255, 0, 0.2);
    }

    #map {
        width: 100%;
        height: 88vh;
        background-color: var(--bg-dark);
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        box-shadow: inset 0 0 100px rgba(0,0,0,1);
    }

    /* Hide Cesium UI elements to keep it clean */
    .cesium-viewer-bottom, 
    .cesium-viewer-toolbar,
    .cesium-viewer-animationContainer,
    .cesium-viewer-timelineContainer,
    .cesium-viewer-fullscreenContainer {
        display: none !important;
    }

    .cesium-widget-credits { display: none !important; }

    /* Kaspersky Style HUD */
    .map-hud {
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 10;
        background: rgba(2, 2, 5, 0.85);
        padding: 20px;
        border-radius: 2px;
        border: 1px solid var(--border-color);
        backdrop-filter: blur(10px);
        width: 300px;
        color: #fff;
        font-family: 'Courier New', Courier, monospace;
        box-shadow: 0 0 20px rgba(0,255,0,0.1);
    }

    .bottom-info-box {
        position: absolute;
        bottom: 30px; /* Reduzido de 40px */
        left: 50%;
        transform: translateX(-50%);
        z-index: 10;
        width: 50%; /* Reduzido de 60% */
        background: rgba(2, 2, 5, 0.85);
        border: 1px solid rgba(0, 255, 0, 0.2);
        padding: 10px 20px;
        color: var(--active-green);
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px; /* Reduzido de 12px */
        display: flex;
        flex-direction: column;
        gap: 5px;
        pointer-events: auto;
        transition: opacity 0.3s ease, transform 0.3s ease;
        backdrop-filter: blur(5px);
    }

    .close-info-btn {
        position: absolute;
        top: 5px;
        right: 10px;
        cursor: pointer;
        color: rgba(0, 255, 0, 0.5);
        font-size: 14px;
        font-weight: bold;
    }

    .close-info-btn:hover {
        color: var(--active-green);
    }

    .bottom-info-title {
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid rgba(0, 255, 0, 0.1);
        padding-bottom: 3px;
        font-size: 10px;
    }

    .bottom-info-content {
        color: #aaa;
        line-height: 1.4;
    }

    .hud-title {
        font-size: 14px;
        color: var(--active-green);
        text-transform: uppercase;
        letter-spacing: 3px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: bold;
    }

    .hud-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, var(--active-green), transparent);
    }

    .threat-feed {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 10;
        width: 300px;
        background: var(--sidebar-bg);
        border: 1px solid rgba(255, 0, 0, 0.2);
        border-radius: 4px;
        max-height: 400px;
        display: flex;
        flex-direction: column;
    }

    .feed-header {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 0, 0, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }

    .fullscreen-btn {
        background: transparent;
        border: 1px solid rgba(255, 0, 0, 0.3);
        color: var(--threat-red);
        font-size: 9px;
        padding: 2px 6px;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-radius: 2px;
        transition: all 0.2s;
    }

    .fullscreen-btn:hover {
        background: rgba(255, 0, 0, 0.1);
        border-color: var(--threat-red);
    }

    .feed-title {
        color: var(--threat-red);
        font-size: 11px;
        font-weight: bold;
        letter-spacing: 2px;
    }

    .feed-content {
        padding: 10px;
        overflow-y: auto;
        font-family: 'Courier New', Courier, monospace;
        font-size: 10px;
    }

    .feed-item {
        margin-bottom: 8px;
        padding: 6px;
        border-left: 2px solid var(--threat-red);
        background: rgba(255, 0, 0, 0.05);
        color: #ff9999;
    }

    .legend-hud {
        position: absolute;
        bottom: 20px;
        right: 20px;
        z-index: 10;
        background: var(--sidebar-bg);
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
        font-size: 11px;
        color: #ccc;
    }

    .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .dot.green { background: var(--active-green); box-shadow: 0 0 10px var(--active-green); }
    .dot.yellow { background: var(--vuln-yellow); box-shadow: 0 0 10px var(--vuln-yellow); }
    .dot.red { background: var(--threat-red); box-shadow: 0 0 10px var(--threat-red); }
    .dot.cyan { background: var(--trace-cyan); box-shadow: 0 0 10px var(--trace-cyan); }
    .dot.blue { background: #0000ff; box-shadow: 0 0 10px #0000ff; }
    .dot.purple { background: #8000ff; box-shadow: 0 0 10px #8000ff; }

    .stats-hud {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10;
        display: flex;
        gap: 20px;
        background: var(--sidebar-bg);
        padding: 10px 30px;
        border-radius: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(5px);
    }

    /* Advanced Flow Panels */
    .flow-panel {
        position: absolute;
        bottom: 80px;
        left: 20px;
        z-index: 10;
        width: 280px;
        background: rgba(2, 2, 5, 0.85);
        border: 1px solid var(--border-color);
        padding: 15px;
        color: #fff;
        font-family: 'Courier New', Courier, monospace;
        font-size: 10px;
        border-radius: 2px;
        backdrop-filter: blur(10px);
    }

    .flow-panel h4 {
        margin: 0 0 10px 0;
        color: var(--active-green);
        font-size: 11px;
        text-transform: uppercase;
        border-bottom: 1px solid rgba(0, 255, 0, 0.2);
        padding-bottom: 5px;
    }

    .flow-stat-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding-bottom: 2px;
    }

    .flow-stat-val { color: var(--trace-cyan); font-weight: bold; }

    .stat-item { text-align: center; min-width: 80px; }
    .stat-val { font-size: 22px; font-weight: bold; display: block; font-family: 'Courier New', Courier, monospace; }
    .stat-lbl { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 2px; }

    /* Scanline effect */
    #map::before {
        content: " ";
        display: block;
        position: absolute;
        top: 0; left: 0; bottom: 0; right: 0;
        background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
        z-index: 2;
        background-size: 100% 2px, 3px 100%;
        pointer-events: none;
    }

    @keyframes flicker {
        0% { opacity: 0.8; }
        50% { opacity: 1; }
        100% { opacity: 0.8; }
    }

    .loading-screen {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: var(--bg-dark);
        z-index: 100;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: var(--active-green);
    }

    .scanner {
        width: 150px;
        height: 2px;
        background: var(--active-green);
        box-shadow: 0 0 15px var(--active-green);
        animation: scan 2s linear infinite;
        margin-bottom: 20px;
    }

    @keyframes scan {
        0% { transform: translateY(-50px); opacity: 0; }
        50% { opacity: 1; }
        100% { transform: translateY(50px); opacity: 0; }
    }

    /* Custom Popup Styles */
    .custom-popup {
        position: absolute;
        background: rgba(10, 10, 15, 0.95);
        border: 1px solid var(--active-green);
        padding: 15px;
        border-radius: 4px;
        color: white;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        z-index: 1000;
        pointer-events: none;
        display: none;
        box-shadow: 0 0 20px rgba(0, 255, 0, 0.2);
        min-width: 220px;
    }
    .popup-header {
        border-bottom: 1px solid rgba(0, 255, 0, 0.3);
        margin-bottom: 10px;
        padding-bottom: 5px;
        font-weight: bold;
        color: var(--active-green);
        display: flex;
        justify-content: space-between;
    }
    .popup-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    .popup-label { color: #888; margin-right: 10px; }
    .popup-value { color: #eee; text-align: right; }

    /* Selected Node HUD */
    .selected-hud {
        position: absolute;
        top: 20px;
        left: 20px;
        width: 280px; /* Reduzido de 320px */
        background: rgba(5, 5, 12, 0.9);
        border: 1px solid var(--active-green);
        padding: 15px; /* Reduzido de 20px */
        border-radius: 4px;
        color: white;
        font-family: 'Courier New', Courier, monospace;
        display: none;
        z-index: 2000;
        box-shadow: 0 0 30px rgba(0, 255, 0, 0.15);
        pointer-events: auto;
        backdrop-filter: blur(5px);
    }
    .selected-hud .close-btn {
        position: absolute;
        top: 8px;
        right: 12px;
        cursor: pointer;
        color: #666;
        font-size: 12px;
    }
    .selected-hud .close-btn:hover { color: white; }
    .selected-hud h3 { 
        margin: 0 0 10px 0; 
        color: var(--active-green); 
        font-size: 14px; /* Reduzido de 16px */
        border-bottom: 1px solid rgba(0,255,0,0.2); 
        padding-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .hud-detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px; /* Reduzido de 8px */
        font-size: 11px; /* Reduzido de 12px */
    }
    .hud-detail-label { color: #888; }
    .hud-detail-value { color: #fff; font-weight: bold; }
</style>

<div id="map">
    <div id="popup" class="custom-popup"></div>
    <div id="selectedHud" class="selected-hud">
        <div class="close-btn" onclick="document.getElementById('selectedHud').style.display='none'">[ ESC ]</div>
        <div id="selectedContent"></div>
    </div>
    <div id="loading" class="loading-screen">
        <div class="scanner"></div>
        <div style="letter-spacing: 5px; font-size: 12px; font-weight: bold;">INITIALIZING THREAT SCAN</div>
    </div>

    <div class="map-hud">
        <div class="hud-title">Threat Intel HUD</div>
        
        <div style="margin-bottom: 15px;">
            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 8px;">Traffic Locality</div>
            <div id="localityHUD" style="display: flex; gap: 10px;">
                <div style="flex: 1; background: rgba(0,255,0,0.05); padding: 5px; text-align: center; border: 1px solid rgba(0,255,0,0.1);">
                    <div id="localInt" style="font-size: 14px; font-weight: bold; color: var(--active-green);">0</div>
                    <div style="font-size: 8px; color: #666;">INTERNAL</div>
                </div>
                <div style="flex: 1; background: rgba(0,255,255,0.05); padding: 5px; text-align: center; border: 1px solid rgba(0,255,255,0.1);">
                    <div id="localExt" style="font-size: 14px; font-weight: bold; color: var(--trace-cyan);">0</div>
                    <div style="font-size: 8px; color: #666;">EXTERNAL</div>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <button onclick="refreshData(true)" style="width: 100%; background: transparent; border: 1px solid var(--active-green); color: var(--active-green); padding: 8px; font-size: 10px; font-weight: bold; cursor: pointer; letter-spacing: 2px;">RE-SCAN NETWORK (2m)</button>
        </div>

        <div id="activeListHUD" style="max-height: 150px; overflow-y: auto;">
            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 8px;">Infrastructure Status</div>
            <div id="infraList" style="font-size: 10px; color: #aaa; margin-bottom: 10px;"></div>
            
            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 8px;">Top Talkers (Flow)</div>
            <div id="topTalkersList" style="font-size: 10px; color: #aaa;"></div>
        </div>
    </div>

    <div class="flow-panel">
        <h4>Advanced Network Analysis</h4>
        <div style="margin-bottom: 15px;">
            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 5px;">Top Services (Ports)</div>
            <div id="topServicesList"></div>
        </div>
        <div>
            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 5px;">AS Traffic Distribution</div>
            <div id="topASList"></div>
        </div>
    </div>

    <div class="threat-feed">
        <div class="feed-header">
            <span class="feed-title">THREAT LOG</span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button class="fullscreen-btn" onclick="toggleFullScreen()">Full Screen</button>
                <div style="width: 6px; height: 6px; background: var(--threat-red); border-radius: 50%; animation: flicker 1s infinite;"></div>
            </div>
        </div>
        <div id="feedContent" class="feed-content"></div>
    </div>

    <div class="stats-hud">
        <div class="stat-item">
            <span id="statActive" class="stat-val" style="color: var(--active-green)">0</span>
            <span class="stat-lbl">Active</span>
        </div>
        <div class="stat-item">
            <span id="statVuln" class="stat-val" style="color: var(--vuln-yellow)">0</span>
            <span class="stat-lbl">Vulns</span>
        </div>
        <div class="stat-item">
            <span id="statThreats" class="stat-val" style="color: var(--threat-red)">0</span>
            <span class="stat-lbl">Threats</span>
        </div>
        <div class="stat-item">
            <span id="statAttacks" class="stat-val" style="color: #ff6600">0</span>
            <span class="stat-lbl">Attacks</span>
        </div>
        <div class="stat-item">
            <span id="statBGP" class="stat-val" style="color: #8000ff">0</span>
            <span class="stat-lbl">Peers</span>
        </div>
    </div>

    <div id="bottomInfoBox" class="bottom-info-box">
        <div class="close-info-btn" onclick="document.getElementById('bottomInfoBox').style.display='none'">[x]</div>
        <div class="bottom-info-title">Global Intelligence Operations</div>
        <div class="bottom-info-content">
            Monitoring ASN <?php echo h($bgpPlugin['config']['my_asn'] ?? "262978"); ?> and critical IP blocks: <?php echo h($bgpPlugin['config']['ip_blocks'] ?? "132.255.220.0/22, 186.250.184.0/22, 143.0.120.0/22"); ?>. 
            Real-time correlation between global attackers and internal assets enabled. 
            Data source: Shodan (Vulnerabilities), AbuseIPDB (Reputation), IPinfo (Geo).
        </div>
    </div>

    <div class="legend-hud">
        <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px;">Threat Intelligence Legend</div>
        <div class="legend-item"><div class="dot green" style="background: #00ff00;"></div> <span>Low Risk Target</span></div>
        <div class="legend-item"><div class="dot yellow" style="background: #ffff00;"></div> <span>Med Risk Target</span></div>
        <div class="legend-item"><div class="dot red" style="background: #ff0000;"></div> <span>High Risk Target</span></div>
        <div class="legend-item"><div class="dot tor" style="background: #d400ff; box-shadow: 0 0 10px #d400ff;"></div> <span>Tor Exit Node</span></div>
        <div class="legend-item"><div class="dot bgp" style="background: #8000ff; box-shadow: 0 0 10px #8000ff;"></div> <span>BGP Peer (Upstream/Neighbor)</span></div>
        <div class="legend-item"><div class="dot infra" style="background: #ffffff; border: 2px solid var(--active-green); border-radius: 0;"></div> <span>Infrastructure (Exporter)</span></div>
        <div class="legend-item"><div class="dot cyan" style="background: #00ffff;"></div> <span>Trace Flow</span></div>
        <div class="legend-item"><div class="dot aqua" style="background: #00ffff; box-shadow: 0 0 10px #00ffff; border: 1px solid white;"></div> <span>Real-time Flow</span></div>
    </div>
</div>

<script>
    // Cesium initialization with modern async pattern
    let viewer;
    
    async function initCesium() {
          try {
              // Desativar token do Ion para evitar conflitos de recursos internos
              Cesium.Ion.defaultAccessToken = null;

              viewer = new Cesium.Viewer('map', {
                  animation: false,
                  baseLayerPicker: false,
                  fullscreenButton: false,
                  vrButton: false,
                  geocoder: false,
                  homeButton: false,
                  infoBox: true,
                  sceneModePicker: false,
                  selectionIndicator: true,
                  timeline: false,
                  navigationHelpButton: false,
                  scene3DOnly: false,
                  // Iniciamos sem provedor para adicionar manualmente de forma assíncrona e estável
                  imageryProvider: false 
              });

              // Função auxiliar para criar provedores de forma compatível (estática vs construtor)
              async function createProvider(className, url, options = {}) {
                  const Cls = Cesium[className];
                  try {
                      if (typeof Cls.fromUrl === 'function') {
                          return await Cls.fromUrl(url, options);
                      }
                  } catch (e) {
                      console.warn(`Erro ao usar fromUrl para ${className}, tentando construtor...`);
                  }
                  return new Cls({ url, ...options });
              }

              // Adicionar camada base 'Dark Matter' (Preto e Cinza) de forma ultra-robusta
              try {
                  const darkMatterProvider = await createProvider('UrlTemplateImageryProvider', 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                      subdomains: 'abcd',
                      minimumLevel: 0,
                      maximumLevel: 20,
                      credit: '© OpenStreetMap contributors, © CARTO'
                  });
                  viewer.imageryLayers.addImageryProvider(darkMatterProvider);
              } catch (error) {
                  console.error("Falha ao carregar mapa Dark Matter:", error);
                  // Fallback para OSM se o Carto falhar
                  const osmProvider = await createProvider('UrlTemplateImageryProvider', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
                  viewer.imageryLayers.addImageryProvider(osmProvider);
              }

              viewer.scene.globe.enableLighting = false; // Desativar iluminação para manter o estilo "flat dark" constante

              setupScene();
              initInteractivity();
              refreshData(true);
              
              // Auto-refresh for real-time flow visibility
              setInterval(() => refreshData(false), 10000);
          } catch (error) {
            console.error("Cesium initialization failed:", error);
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                const statusEl = loadingEl.querySelector('div:last-child');
                if (statusEl) statusEl.innerHTML = `<span style="color: #ff0000">RENDER ERROR: ${error.message}</span>`;
            }
        }
    }

    function setupScene() {
        // Permitir 3D para o "fundo com mapa global" solicitado
        viewer.scene.mode = Cesium.SceneMode.SCENE3D;

        // Ajustes para o estilo "Preto e Cinza" solicitado
        viewer.scene.globe.showGroundAtmosphere = false; // Remover brilho azul da atmosfera
        viewer.scene.globe.baseColor = Cesium.Color.BLACK;
        viewer.scene.backgroundColor = Cesium.Color.BLACK;
        viewer.scene.skyAtmosphere.show = false; // Remover névoa atmosférica no horizonte
        
        // Melhorar qualidade visual
        viewer.scene.postProcessStages.fxaa.enabled = true;
        viewer.scene.highDynamicRange = true;

        // Bloom suave para as linhas de ataque brilharem mais no fundo escuro
        viewer.scene.postProcessStages.bloom.enabled = true;
        viewer.scene.postProcessStages.bloom.uniforms.contrast = 120;
        viewer.scene.postProcessStages.bloom.uniforms.brightness = -0.1;

        // Default view (Visão Global com foco no Brasil)
        viewer.camera.setView({
            destination: Cesium.Rectangle.fromDegrees(-120.0, -60.0, 40.0, 60.0)
        });
    }

    // Initialize everything
    initCesium();

    function initInteractivity() {
        // Mouse interactivity for custom popup
        const handler = new Cesium.ScreenSpaceEventHandler(viewer.scene.canvas);
        const popup = document.getElementById('popup');

        handler.setInputAction(function(movement) {
            const pickedObject = viewer.scene.pick(movement.endPosition);
            if (Cesium.defined(pickedObject) && pickedObject.id && pickedObject.id.properties) {
                const props = pickedObject.id.properties.getValue(Cesium.JulianDate.now());
                
                let headerText = `${props.type.toUpperCase()} DETECTED`;
                let headerColor = 'var(--active-green)';
                
                if (props.is_tor) {
                    headerText = `TOR EXIT NODE DETECTED`;
                    headerColor = '#d400ff';
                } else if (props.type === 'bgp_peer') {
                    headerText = `BGP PEER DETECTED`;
                    headerColor = '#8000ff';
                }

                let html = `
                    <div class="popup-header" style="color: ${headerColor}">
                        <span>${headerText}</span>
                        <span style="font-size: 10px; opacity: 0.7;">${new Date().toLocaleTimeString()}</span>
                    </div>
                `;

                if (props.type === 'bgp_peer') {
                    html += `
                        <div class="popup-row"><span class="popup-label">ASN:</span><span class="popup-value" style="color:${headerColor}">${props.asn}</span></div>
                        <div class="popup-row"><span class="popup-label">Relation:</span><span class="popup-value">${props.peer_type}</span></div>
                        <div class="popup-row"><span class="popup-label">Neighbor Of:</span><span class="popup-value">${props.neighbor_of}</span></div>
                        <div class="popup-row"><span class="popup-label">Location:</span><span class="popup-value">${props.city}, ${props.country}</span></div>
                        <div class="popup-row"><span class="popup-label">Link Power:</span><span class="popup-value">${props.power}</span></div>
                        <div class="popup-row" style="margin-top: 10px; border-top: 1px solid rgba(128,0,255,0.2); padding-top: 5px;">
                            <a href="https://bgp.he.net/${props.asn}" target="_blank" style="color: #8000ff; text-decoration: none; font-size: 10px;">[ VIEW ON BGP.HE.NET ]</a>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="popup-row"><span class="popup-label">IP Address:</span><span class="popup-value" style="color:${headerColor}">${props.ip || 'N/A'}</span></div>
                        <div class="popup-row"><span class="popup-label">Location:</span><span class="popup-value">${props.city || 'Unknown'}, ${props.country || 'Unknown'}</span></div>
                        <div class="popup-row"><span class="popup-label">ASN:</span><span class="popup-value">${props.asn || 'Unknown'}</span></div>
                    `;
                }

                if (props.is_tor) {
                    html += `
                        <div class="popup-row"><span class="popup-label">Tor Nickname:</span><span class="popup-value" style="color:#d400ff">${props.tor_nickname || 'Unnamed'}</span></div>
                    `;
                }

                if (props.type === 'attacker') {
                    html += `
                        <div class="popup-row"><span class="popup-label">Abuse Score:</span><span class="popup-value" style="color:#ff4444">${props.abuseScore}%</span></div>
                        <div class="popup-row"><span class="popup-label">Reports:</span><span class="popup-value">${props.reports}</span></div>
                    `;
                    if (props.is_wazuh) {
                        html += `<div class="popup-row"><span class="popup-label">Source:</span><span class="popup-value" style="color:var(--vuln-yellow)">WAZUH ALERT</span></div>`;
                    }
                } else if (props.type === 'infra') {
                    html += `
                        <div class="popup-row"><span class="popup-label">Device Type:</span><span class="popup-value" style="color:white; text-transform:uppercase;">${props.infra_type}</span></div>
                        <div class="popup-row"><span class="popup-label">Flow Export:</span><span class="popup-value" style="color:var(--active-green)">ACTIVE (sFlow)</span></div>
                        <div class="popup-row"><span class="popup-label">Status:</span><span class="popup-value">${props.status.toUpperCase()}</span></div>
                    `;
                    if (props.snmp) {
                        html += `
                            <div class="popup-row"><span class="popup-label">Hostname:</span><span class="popup-value">${props.snmp.hostname || 'N/A'}</span></div>
                            <div class="popup-row"><span class="popup-label">Uptime:</span><span class="popup-value">${props.snmp.uptime || 'N/A'}</span></div>
                        `;
                    }
                } else if (props.type === 'target') {
                    html += `
                        <div class="popup-row"><span class="popup-label">Risk Level:</span><span class="popup-value" style="color:${props.risk === 'high' ? 'red' : 'yellow'}">${props.risk.toUpperCase()}</span></div>
                        <div class="popup-row"><span class="popup-label">Open Ports:</span><span class="popup-value">${(props.ports || []).join(', ')}</span></div>
                    `;
                    if (props.nuclei && props.nuclei.length > 0) {
                        html += `
                            <div class="popup-row"><span class="popup-label">Nuclei Vulns:</span><span class="popup-value" style="color:red">${props.nuclei.length} Detected</span></div>
                        `;
                    }
                }

                safeSetHTML('popup', html);
                if (popup) {
                    popup.style.display = 'block';
                    popup.style.left = (movement.endPosition.x + 20) + 'px';
                    popup.style.top = (movement.endPosition.y + 20) + 'px';
                }
                
                viewer.container.style.cursor = 'pointer';
            } else {
                if (popup) popup.style.display = 'none';
                viewer.container.style.cursor = 'default';
            }
        }, Cesium.ScreenSpaceEventType.MOUSE_MOVE);

        // Left click to select and show location details
        handler.setInputAction(function(movement) {
            const pickedObject = viewer.scene.pick(movement.position);
            const selectedHud = document.getElementById('selectedHud');
            const selectedContent = document.getElementById('selectedContent');

            if (Cesium.defined(pickedObject) && pickedObject.id && pickedObject.id.properties) {
                const props = pickedObject.id.properties.getValue(Cesium.JulianDate.now());
                const entity = pickedObject.id;
                const position = entity.position.getValue(Cesium.JulianDate.now());
                const cartographic = Cesium.Cartographic.fromCartesian(position);
                const lon = Cesium.Math.toDegrees(cartographic.longitude);
                const lat = Cesium.Math.toDegrees(cartographic.latitude);

                showReticle([lon, lat]);

                let typeText = props.type.toUpperCase();
                let typeColor = props.type === 'attacker' ? 'red' : 'var(--active-green)';
                
                if (props.is_tor) {
                    typeText = 'TOR EXIT NODE';
                    typeColor = '#d400ff';
                }

                let html = `
                    <h3>NODE DETAILS: ${props.ip}</h3>
                    <div class="hud-detail-row"><span class="hud-detail-label">Type:</span><span class="hud-detail-value" style="color:${typeColor}">${typeText}</span></div>
                    <div class="hud-detail-row"><span class="hud-detail-label">Status:</span><span class="hud-detail-value">ACTIVE MONITORING</span></div>
                    <div class="hud-detail-row"><span class="hud-detail-label">Location:</span><span class="hud-detail-value">${props.city}, ${props.country}</span></div>
                    <div class="hud-detail-row"><span class="hud-detail-label">Coordinates:</span><span class="hud-detail-value">${lat.toFixed(4)}°, ${lon.toFixed(4)}°</span></div>
                    <div class="hud-detail-row"><span class="hud-detail-label">Network/ASN:</span><span class="hud-detail-value">${props.asn}</span></div>
                `;

                if (props.is_tor) {
                    html += `
                        <div style="margin-top:15px; padding:10px; background:rgba(212,0,255,0.1); border:1px solid rgba(212,0,255,0.3);">
                            <div class="hud-detail-row"><span class="hud-detail-label">Tor Nickname:</span><span class="hud-detail-value" style="color:#d400ff">${props.tor_nickname || 'Unnamed'}</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">Node Status:</span><span class="hud-detail-value">EXIT RELAY</span></div>
                        </div>
                    `;
                }

                if (props.type === 'attacker') {
                    html += `
                        <div style="margin-top:15px; padding:10px; background:rgba(255,0,0,0.1); border:1px solid rgba(255,0,0,0.3);">
                            <div class="hud-detail-row"><span class="hud-detail-label">Abuse Score:</span><span class="hud-detail-value" style="color:#ff4444">${props.abuseScore}%</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">Reports:</span><span class="hud-detail-value">${props.reports}</span></div>
                            ${props.is_wazuh ? `<div class="hud-detail-row"><span class="hud-detail-label">Source:</span><span class="hud-detail-value" style="color:var(--vuln-yellow)">WAZUH IDS ALERT</span></div>` : ''}
                        </div>
                    `;
                } else if (props.type === 'target') {
                    html += `
                        <div style="margin-top:15px; padding:10px; background:rgba(0,255,0,0.05); border:1px solid rgba(0,255,0,0.2);">
                            <div class="hud-detail-row"><span class="hud-detail-label">Risk Assessment:</span><span class="hud-detail-value" style="color:${props.risk === 'high' ? 'red' : 'yellow'}">${props.risk.toUpperCase()}</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">Exposed Ports:</span><span class="hud-detail-value">${(props.ports || []).join(', ') || 'None Detected'}</span></div>
                            ${props.nuclei && props.nuclei.length > 0 ? `
                                <div style="margin-top:10px; border-top:1px solid rgba(255,0,0,0.2); padding-top:5px;">
                                    <div class="hud-detail-label" style="margin-bottom:5px; color:red">NUCLEI VULNERABILITIES:</div>
                                    <div style="font-size:9px; color:#aaa; max-height:100px; overflow-y:auto;">
                                        ${props.nuclei.map(v => `<div style="margin-bottom:3px;">• ${v.info.name} [${v.info.severity}]</div>`).join('')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                } else if (props.type === 'infra') {
                    html += `
                        <div style="margin-top:15px; padding:10px; background:rgba(255,255,255,0.05); border:1px solid rgba(0,255,0,0.2);">
                            <div class="hud-detail-row"><span class="hud-detail-label">Device Type:</span><span class="hud-detail-value">${props.infra_type.toUpperCase()}</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">Status:</span><span class="hud-detail-value" style="color:var(--active-green)">${props.status.toUpperCase()}</span></div>
                            ${props.snmp ? `
                                <div class="hud-detail-row"><span class="hud-detail-label">Hostname:</span><span class="hud-detail-value">${props.snmp.hostname || 'N/A'}</span></div>
                                <div class="hud-detail-row"><span class="hud-detail-label">Uptime:</span><span class="hud-detail-value">${props.snmp.uptime || 'N/A'}</span></div>
                                <div class="hud-detail-row"><span class="hud-detail-label">CPU Load:</span><span class="hud-detail-value">${props.snmp.cpu_load || '0'}%</span></div>
                            ` : ''}
                        </div>
                    `;
                } else if (props.type === 'bgp_peer') {
                    html += `
                        <div style="margin-top:15px; padding:10px; background:rgba(128,0,255,0.1); border:1px solid rgba(128,0,255,0.3);">
                            <div class="hud-detail-row"><span class="hud-detail-label">BGP Relation:</span><span class="hud-detail-value" style="color:#8000ff">${props.peer_type.toUpperCase()}</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">Neighbor Of:</span><span class="hud-detail-value">${props.neighbor_of}</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">Peer Holder:</span><span class="hud-detail-value" style="font-size:9px;">${props.holder}</span></div>
                            <div class="hud-detail-row"><span class="hud-detail-label">IX Count:</span><span class="hud-detail-value">${props.ix_count}</span></div>
                            <div style="margin-top:10px; text-align:center;">
                                <a href="https://bgp.he.net/${props.asn}" target="_blank" style="color: #8000ff; text-decoration: none; font-size: 10px;">[ VIEW ON BGP.HE.NET ]</a>
                            </div>
                        </div>
                    `;
                }

                safeSetHTML('selectedContent', html);
                if (selectedHud) selectedHud.style.display = 'block';

                // Fly to node
                viewer.camera.flyTo({
                    destination: Cesium.Cartesian3.fromDegrees(lon, lat, 1000000), // 1000km altitude
                    duration: 1.5
                });

            }
        }, Cesium.ScreenSpaceEventType.LEFT_CLICK);

        // Pulse animation logic
        let lastTime = 0;
        viewer.scene.postRender.addEventListener(function(scene, time) {
            const now = Cesium.JulianDate.toDate(time).getTime();
            if (now - lastTime < 50) return;
            lastTime = now;

            const pulseSize = 50000 + (Math.sin(now / 500) * 20000);
            const glowPower = 0.3 + (Math.sin(now / 300) * 0.1);
            const strobe = Math.sin(now / 50) > 0; // Fast strobe for critical threats

            viewer.entities.values.forEach(entity => {
                const props = entity.properties ? entity.properties.getValue() : null;
                
                if (entity.ellipse) {
                    entity.ellipse.semiMinorAxis = pulseSize;
                    entity.ellipse.semiMajorAxis = pulseSize;
                    
                    // Critical origin pulse
                    if (props && (props.is_faz || props.is_shodan || props.is_abuse)) {
                        entity.ellipse.material = Cesium.Color.RED.withAlpha(strobe ? 0.6 : 0.1);
                        entity.ellipse.semiMinorAxis = pulseSize * 1.5;
                        entity.ellipse.semiMajorAxis = pulseSize * 1.5;
                    }
                }

                if (entity.polyline) {
                    if (entity.polyline.material instanceof Cesium.PolylineGlowMaterialProperty) {
                        entity.polyline.material.glowPower = glowPower;
                    }

                    // Shimmering effect for critical attack lines
                    if (props && (props.is_faz || props.is_shodan || props.is_abuse)) {
                        if (entity.polyline.material instanceof Cesium.PolylineArrowMaterialProperty || 
                            entity.polyline.material instanceof Cesium.PolylineGlowMaterialProperty) {
                            
                            // Flicker the alpha for a "shimmering" effect
                            const baseColor = Cesium.Color.RED;
                            const alpha = 0.4 + (Math.sin(now / 100) * 0.4); // Oscillate between 0 and 0.8
                            
                            if (entity.polyline.material.color) {
                                entity.polyline.material.color = baseColor.withAlpha(alpha);
                            }
                        }
                    }
                }
            });
        });
    }

    function toggleFullScreen() {
        const element = document.getElementById('map');
        if (!document.fullscreenElement) {
            if (element.requestFullscreen) element.requestFullscreen();
            else if (element.mozRequestFullScreen) element.mozRequestFullScreen();
            else if (element.webkitRequestFullscreen) element.webkitRequestFullscreen();
            else if (element.msRequestFullscreen) element.msRequestFullscreen();
        } else {
            if (document.exitFullscreen) document.exitFullscreen();
        }
    }

    // Close HUD with ESC key
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const selectedHud = document.getElementById('selectedHud');
            if (selectedHud) selectedHud.style.display = 'none';
            // Clear reticle
            if (viewer) {
                ['tl', 'tr', 'bl', 'br'].forEach(id => viewer.entities.removeById('reticle-' + id));
            }
        }
    });

    // Helper to add a target reticle (brackets) at a position
    function showReticle(coords) {
        if (!viewer) return;
        
        const size = 0.8; // size in degrees
        const gap = 0.3;  // gap between brackets
        const lon = coords[0];
        const lat = coords[1];
        
        // Clear previous reticle
        ['tl', 'tr', 'bl', 'br'].forEach(id => viewer.entities.removeById('reticle-' + id));

        const material = new Cesium.PolylineGlowMaterialProperty({
            glowPower: 0.2,
            color: Cesium.Color.LIME
        });

        // Top Left Bracket
        viewer.entities.add({
            id: 'reticle-tl',
            polyline: {
                positions: Cesium.Cartesian3.fromDegreesArray([
                    lon - size, lat + gap,
                    lon - size, lat + size,
                    lon - gap, lat + size
                ]),
                width: 3,
                material: material
            }
        });

        // Top Right Bracket
        viewer.entities.add({
            id: 'reticle-tr',
            polyline: {
                positions: Cesium.Cartesian3.fromDegreesArray([
                    lon + gap, lat + size,
                    lon + size, lat + size,
                    lon + size, lat + gap
                ]),
                width: 3,
                material: material
            }
        });

        // Bottom Left Bracket
        viewer.entities.add({
            id: 'reticle-bl',
            polyline: {
                positions: Cesium.Cartesian3.fromDegreesArray([
                    lon - size, lat - gap,
                    lon - size, lat - size,
                    lon - gap, lat - size
                ]),
                width: 3,
                material: material
            }
        });

        // Bottom Right Bracket
        viewer.entities.add({
            id: 'reticle-br',
            polyline: {
                positions: Cesium.Cartesian3.fromDegreesArray([
                    lon + gap, lat - size,
                    lon + size, lat - size,
                    lon + size, lat - gap
                ]),
                width: 3,
                material: material
            }
        });
    }

    let currentData = null;

    async function refreshData(isInitial = false) {
        const loadingEl = document.getElementById('loading');
        if (isInitial && loadingEl) {
            loadingEl.style.display = 'flex';
            const statusEl = loadingEl.querySelector('div:last-child');
            if (statusEl) statusEl.innerText = 'INITIALIZING THREAT SCAN...';
        }
        
        try {
            const res = await fetch('plugin_maps_data.php');
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();
            currentData = data;
            
            if (!data.features || data.features.length === 0) {
                if (isInitial && loadingEl) {
                    const statusEl = loadingEl.querySelector('div:last-child');
                    if (statusEl) statusEl.innerHTML = `<span style="color: #ffff00">NO DATA FOUND - STARTING COLLECTOR...</span>`;
                    setTimeout(() => {
                        loadingEl.style.display = 'none';
                    }, 3000);
                }
                return;
            }

            updateMap(data);
            updateHUD(data);
            
            if (isInitial && loadingEl) {
                setTimeout(() => {
                    loadingEl.style.display = 'none';
                }, 1000);
            }
        } catch (e) {
            console.error("Failed to load map data", e);
            if (loadingEl) {
                const statusEl = loadingEl.querySelector('div:last-child');
                if (statusEl) statusEl.innerHTML = `<span style="color: #ff0000">SCAN FAILED: ${e.message}</span>`;
                setTimeout(() => {
                    loadingEl.style.display = 'none';
                }, 3000);
            }
        }
    }

    function getArcPoints(startCoords, endCoords) {
        const start = Cesium.Cartesian3.fromDegrees(startCoords[0], startCoords[1]);
        const end = Cesium.Cartesian3.fromDegrees(endCoords[0], endCoords[1]);
        
        const distance = Cesium.Cartesian3.distance(start, end);
        const height = distance * 0.2; // 20% of distance as peak height
        
        const points = [];
        const iterations = 40;
        
        const startCartographic = Cesium.Cartographic.fromDegrees(startCoords[0], startCoords[1]);
        const endCartographic = Cesium.Cartographic.fromDegrees(endCoords[0], endCoords[1]);

        for (let i = 0; i <= iterations; i++) {
            const pct = i / iterations;
            const lon = Cesium.Math.lerp(startCartographic.longitude, endCartographic.longitude, pct);
            const lat = Cesium.Math.lerp(startCartographic.latitude, endCartographic.latitude, pct);
            
            // Parabolic arc height
            const h = Math.sin(Math.PI * pct) * height;
            points.push(Cesium.Cartesian3.fromRadians(lon, lat, h));
        }
        return points;
    }

    function updateMap(data) {
        viewer.entities.removeAll();

        data.features.forEach(feature => {
            const props = feature.properties;
            const type = props.type;
            const coords = feature.geometry.coordinates;

            if (feature.geometry.type === 'Point') {
                let color, size, outlineColor;

                switch(type) {
                    case 'target':
                        color = props.risk === 'high' ? Cesium.Color.RED : (props.risk === 'medium' ? Cesium.Color.YELLOW : Cesium.Color.GREEN);
                        size = 12;
                        outlineColor = Cesium.Color.WHITE;
                        break;
                    case 'attacker':
                        // Scaling size based on abuse score for a "hotspot" effect
                        const score = props.abuseScore || 0;
                        size = 6 + (score / 10); // Size varies between 6 and 16
                        
                        if (props.is_tor) {
                            color = Cesium.Color.fromCssColorString('#d400ff'); // Purple for TOR
                        } else if (props.is_real_flow) {
                            color = Cesium.Color.AQUA;
                        } else {
                            color = Cesium.Color.LIME; // Default bright green
                        }
                        
                        outlineColor = Cesium.Color.fromAlpha(color, 0.5);
                        break;
                    case 'infra':
                        color = Cesium.Color.WHITE;
                        outlineColor = Cesium.Color.LIME;
                        size = 12;
                        break;
                    case 'trace':
                        color = Cesium.Color.CYAN;
                        size = 4;
                        outlineColor = Cesium.Color.WHITE;
                        break;
                    case 'bgp_peer':
                        color = Cesium.Color.fromCssColorString('#8000ff'); // Purple
                        size = 10;
                        outlineColor = Cesium.Color.WHITE;
                        break;
                }

                viewer.entities.add({
                    position: Cesium.Cartesian3.fromDegrees(coords[0], coords[1]),
                    point: {
                        pixelSize: size,
                        color: color,
                        outlineColor: outlineColor,
                        outlineWidth: 2
                    },
                    label: {
                        text: props.asn || props.ip,
                        font: '10px monospace',
                        style: Cesium.LabelStyle.FILL_AND_OUTLINE,
                        outlineWidth: 2,
                        verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
                        pixelOffset: new Cesium.Cartesian2(0, -10),
                        fillColor: Cesium.Color.WHITE,
                        outlineColor: Cesium.Color.BLACK,
                        showBackground: true,
                        backgroundColor: new Cesium.Color(0.1, 0.1, 0.1, 0.7),
                        distanceDisplayCondition: new Cesium.DistanceDisplayCondition(0, 5000000)
                    },
                    properties: props,
                    name: props.name
                });

                // Add pulse/glow for attackers, targets, and BGP peers
                if (type === 'attacker' || type === 'target' || type === 'bgp_peer') {
                    viewer.entities.add({
                        position: Cesium.Cartesian3.fromDegrees(coords[0], coords[1]),
                        ellipse: {
                            semiMinorAxis: 40000.0 + ((props.abuseScore || (props.risk === 'high' ? 80 : 20) || (type === 'bgp_peer' ? 50 : 0)) * 500),
                            semiMajorAxis: 40000.0 + ((props.abuseScore || (props.risk === 'high' ? 80 : 20) || (type === 'bgp_peer' ? 50 : 0)) * 500),
                            material: new Cesium.ColorMaterialProperty(color.withAlpha(0.2)),
                            height: 0,
                            outline: false
                        }
                    });
                }

            } else if (feature.geometry.type === 'LineString') {
                const startCoords = coords[0];
                const endCoords = coords[1];
                
                // Arced polyline for better visual correlation of routes
                const arcPoints = getArcPoints(startCoords, endCoords);
                
                // Directional material
                let color;
                let width = props.is_real_flow ? 3 : 5;
                let dash = false;

                if (type === 'bgp_link') {
                    color = Cesium.Color.fromCssColorString('#8000ff').withAlpha(0.6); // Purple for BGP
                    width = 2;
                    dash = true;
                } else if (props.is_tor) {
                    color = Cesium.Color.fromCssColorString('#d400ff'); // Purple for TOR
                } else if (props.is_faz || props.is_shodan || props.is_abuse) {
                    color = Cesium.Color.RED;
                    width = 6; // Extra thick for critical threats
                } else {
                    color = props.is_real_flow ? Cesium.Color.AQUA : (props.severity === 'high' ? Cesium.Color.RED : Cesium.Color.ORANGE);
                }
                
                // Base directional arrow line
                viewer.entities.add({
                    polyline: {
                        positions: arcPoints,
                        width: width,
                        material: dash ? new Cesium.PolylineDashMaterialProperty({
                            color: color,
                            dashLength: 16
                        }) : new Cesium.PolylineArrowMaterialProperty(color)
                    },
                    properties: props // Store properties for animation loop
                });

                // Add a glowing line overlay for extra cyber feel (Continuous Risk Effect)
                if (type !== 'bgp_link') {
                    viewer.entities.add({
                        polyline: {
                            positions: arcPoints,
                            width: width * 1.5,
                            material: new Cesium.PolylineGlowMaterialProperty({
                                glowPower: 0.3,
                                color: color.withAlpha(0.5)
                            })
                        },
                        properties: props
                    });

                    // Add a thin bright core
                    viewer.entities.add({
                        polyline: {
                            positions: arcPoints,
                            width: 2,
                            material: Cesium.Color.WHITE.withAlpha(0.8)
                        },
                        properties: props
                    });
                }
            }
        });

        // Fly to Brazil area if no focus
        if (!currentData || currentData.features.length === 0) {
            viewer.camera.flyTo({
                destination: Cesium.Rectangle.fromDegrees(-75.0, -35.0, -30.0, 5.0),
                duration: 3
            });
        }
    }

    function safeSetText(id, text) {
        const el = document.getElementById(id);
        if (el) el.innerText = text;
    }

    function safeSetHTML(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function updateHUD(data) {
        safeSetText('statActive', data.stats.active);
        safeSetText('statVuln', data.stats.vulnerable);
        safeSetText('statThreats', data.stats.malicious);
        safeSetText('statAttacks', data.stats.attacks || 0);
        
        // Count BGP Peers from features
        const bgpCount = data.features.filter(f => f.properties.type === 'bgp_peer').length;
        safeSetText('statBGP', bgpCount);

        // Locality Stats
        if (data.top_stats && data.top_stats.locality) {
            safeSetText('localInt', data.top_stats.locality.internal);
            safeSetText('localExt', data.top_stats.locality.external);
        }

        let feedHtml = '';
        // Show actual attacks first
        data.features.filter(f => f.properties.type === 'attack').slice(0, 15).forEach(f => {
            const p = f.properties;
            let severity = p.severity === 'high' ? 'CRITICAL' : (p.is_real_flow ? 'FLOW' : 'WARNING');
            let color = p.severity === 'high' ? 'var(--threat-red)' : (p.is_real_flow ? 'var(--trace-cyan)' : '#ff6600');
            
            if (p.is_tor) {
                severity = 'TOR-' + severity;
                color = '#d400ff'; // Override with Tor Purple
            }

            if (p.is_faz) {
                severity = 'FAZ-IPS';
                color = '#ff0000'; // Pure red for FAZ detections
            }

            const details = p.is_real_flow ? ` [Port ${p.port || '?'}]` : '';
            const abuseTag = p.abuse_score > 0 ? ` <span style="font-size:9px; opacity:0.8; color:#fff; background:rgba(0,0,0,0.3); padding:0 3px; border-radius:2px">ABUSE:${p.abuse_score}%</span>` : '';
            const attackName = p.name ? `<div style="font-size:9px; margin-top:2px; opacity:0.9">${p.name}</div>` : '';
            
            feedHtml += `
                <div class="feed-item" style="border-color: ${color}; background: rgba(0,255,255,0.05); color: ${color};">
                    <div style="display:flex; justify-content:space-between; align-items:center">
                        <strong>${severity}:</strong>
                        ${abuseTag}
                    </div>
                    <div style="margin: 2px 0">${p.attacker_ip} -> ${p.target_ip}${details}</div>
                    ${attackName}
                </div>`;
        });
        safeSetHTML('feedContent', feedHtml);

        // Top Talkers
        let talkersHtml = '';
        if (data.top_stats && data.top_stats.talkers) {
            Object.entries(data.top_stats.talkers).forEach(([ip, bytes]) => {
                const mb = (bytes / (1024 * 1024)).toFixed(2);
                talkersHtml += `<div class="flow-stat-row"><span>${ip}</span><span class="flow-stat-val">${mb} MB</span></div>`;
            });
        }
        safeSetHTML('topTalkersList', talkersHtml);

        // Top Services
        let servicesHtml = '';
        if (data.top_stats && data.top_stats.services) {
            Object.entries(data.top_stats.services).forEach(([svc, bytes]) => {
                servicesHtml += `<div class="flow-stat-row"><span>${svc}</span><span class="flow-stat-val">${(bytes/1024).toFixed(1)} KB</span></div>`;
            });
        }
        safeSetHTML('topServicesList', servicesHtml);

        // Top AS
        let asHtml = '';
        if (data.top_stats && data.top_stats.as_traffic) {
            Object.entries(data.top_stats.as_traffic).forEach(([as, bytes]) => {
                asHtml += `<div class="flow-stat-row"><span>${as}</span><span class="flow-stat-val">${(bytes/1024).toFixed(1)} KB</span></div>`;
            });
        }
        safeSetHTML('topASList', asHtml);

        // Infrastructure List
        let infraHtml = '';
        data.features.filter(f => f.properties.type === 'infra').forEach(f => {
            infraHtml += `<div style="margin-bottom:2px; color: #fff;">> ${f.properties.name} <span style="color:var(--active-green)">[ONLINE]</span></div>`;
        });
        safeSetHTML('infraList', infraHtml);
    }
</script>

<?php render_footer(); ?>
