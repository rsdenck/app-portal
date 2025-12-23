<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
$plugins = plugins_get_all($pdo);

// Agrupar plugins por categoria
$groupedPlugins = [];
foreach ($plugins as $p) {
    $cat = $p['category'] ?: 'Outros';
    if (!isset($groupedPlugins[$cat])) {
        $groupedPlugins[$cat] = [];
    }
    $groupedPlugins[$cat][] = $p;
}

// Ordenar categorias (Prioridade manual)
$priority = [
    'Redes' => 1,
    'Segurança' => 2,
    'Virtualização' => 3,
    'Monitoramento' => 4,
    'Backup' => 5,
    'Acesso Remoto' => 6,
    'Hospedagem' => 7,
    'Email' => 8,
    'Inteligência' => 9
];

uksort($groupedPlugins, function($a, $b) use ($priority) {
    $pA = $priority[$a] ?? 99;
    $pB = $priority[$b] ?? 99;
    if ($pA !== $pB) return $pA - $pB;
    return strcmp($a, $b);
});

render_header('Atendente · Plugins', $user);
?>

<div style="margin-bottom: 20px;">
  <a href="index.php" class="btn" style="display: inline-flex; align-items: center; gap: 8px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
    Voltar
  </a>
</div>

<div class="card" style="margin-bottom:18px">
  <div style="font-weight:700;margin-bottom:6px">Plugins</div>
  <div class="muted" style="margin-bottom:12px">Configure integrações com ferramentas de monitoramento, backup e outros serviços via API.</div>
  
  <div class="category-list">
    <?php foreach ($groupedPlugins as $category => $items): ?>
      <details class="category-group" <?= count($groupedPlugins) === 1 ? 'open' : '' ?>>
        <summary class="category-header">
          <div style="display:flex; align-items:center; gap:12px">
            <div class="category-icon">
              <?php if (stripos($category, 'Rede') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12" y2="20"/></svg>
              <?php elseif (stripos($category, 'Segurança') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              <?php elseif (stripos($category, 'Virtualização') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6" y2="6"/><line x1="6" y1="18" x2="6" y2="18"/></svg>
              <?php elseif (stripos($category, 'Monitoramento') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              <?php elseif (stripos($category, 'Backup') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              <?php elseif (stripos($category, 'Acesso') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <?php elseif (stripos($category, 'Email') !== false): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="8"/></svg>
              <?php endif; ?>
            </div>
            <div style="font-weight:600; font-size:15px"><?= h($category) ?></div>
            <div class="category-count"><?= count($items) ?></div>
          </div>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </summary>

        <div class="plugin-list">
          <?php foreach ($items as $p): ?>
            <div class="plugin-item">
              <div class="plugin-info">
                <div class="plugin-icon-wrapper">
                  <?php if ($p['icon'] === 'activity'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                  <?php elseif ($p['icon'] === 'box' || $p['icon'] === 'vm'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                  <?php elseif ($p['icon'] === 'shield'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  <?php elseif ($p['icon'] === 'cloud'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                  <?php elseif ($p['icon'] === 'mail'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  <?php elseif ($p['icon'] === 'server'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6" y2="6"/><line x1="6" y1="18" x2="6" y2="18"/></svg>
                  <?php elseif ($p['icon'] === 'bar-chart'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                  <?php elseif ($p['icon'] === 'monitor'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                  <?php elseif ($p['icon'] === 'share-2'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                  <?php elseif ($p['icon'] === 'search'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                  <?php elseif ($p['icon'] === 'zap'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                  <?php elseif ($p['icon'] === 'globe'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="8"/></svg>
                  <?php endif; ?>
                </div>
                <div class="plugin-meta">
                  <div class="plugin-title">
                    <?= h($p['label']) ?>
                  </div>
                  <div class="plugin-desc"><?= h($p['description']) ?></div>
                </div>
              </div>
              <div class="plugin-actions">
                <div class="plugin-status-indicator">
                  <span class="status-dot <?= $p['is_active'] ? 'status-active' : '' ?>"></span>
                  <?= $p['is_active'] ? 'Ativo' : 'Inativo' ?>
                </div>
                <a href="/atendente_plugin_config.php?name=<?= urlencode($p['name']) ?>" class="btn">Configurar</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>

<style>
.category-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.category-group {
  border: 1px solid var(--border);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.02);
  overflow: hidden;
}
.category-header {
  padding: 15px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;
  background: var(--panel);
  transition: background 0.2s;
}
.category-header:hover {
  background: rgba(39, 196, 168, 0.05);
}
.category-header::-webkit-details-marker {
  display: none;
}
.category-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(39, 196, 168, 0.1);
  border-radius: 8px;
  color: var(--primary);
}
.category-count {
  font-size: 11px;
  background: var(--border);
  padding: 2px 8px;
  border-radius: 10px;
  color: var(--muted);
  font-weight: 600;
}
.chevron {
  transition: transform 0.2s;
  color: var(--muted);
}
.category-group[open] .chevron {
  transform: rotate(180deg);
}
.plugin-list {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: var(--border);
  padding: 0;
}
.plugin-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: var(--panel);
  transition: all 0.2s ease;
}
.plugin-item:hover {
  background: rgba(39, 196, 168, 0.02);
}
.plugin-info {
  display: flex;
  gap: 16px;
  align-items: center;
}
.plugin-icon-wrapper {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  color: var(--muted);
}
.plugin-icon-wrapper svg {
  width: 20px;
  height: 20px;
}
.plugin-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.plugin-title {
  font-weight: 600;
  font-size: 14px;
  color: var(--text);
}
.plugin-desc {
  font-size: 12px;
  color: var(--muted);
}
.plugin-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}
.plugin-status-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--muted);
}
.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #444;
}
.status-dot.status-active {
  background: var(--primary);
  box-shadow: 0 0 8px var(--primary);
}
</style>

<?php
render_footer();
