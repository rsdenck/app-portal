<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('atendente');
$plugins = plugins_get_all($pdo);

render_header('Atendente · Plugins', $user);
?>
<div class="card" style="margin-bottom:18px">
  <div style="font-weight:700;margin-bottom:6px">Plugins</div>
  <div class="muted" style="margin-bottom:12px">Configure integrações com ferramentas de monitoramento, backup e outros serviços via API.</div>
  
  <div class="plugin-list">
    <?php foreach ($plugins as $p): ?>
      <div class="plugin-item">
        <div class="plugin-info">
          <div class="plugin-icon-wrapper">
            <?php if ($p['icon'] === 'activity'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <?php elseif ($p['icon'] === 'box'): ?>
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
            <?php else: ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="8"/></svg>
            <?php endif; ?>
          </div>
          <div class="plugin-meta">
            <div class="plugin-title">
              <?= h($p['label']) ?>
              <span class="plugin-category"><?= h($p['category']) ?></span>
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
</div>

<style>
.plugin-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.plugin-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s ease;
}
.plugin-item:hover {
  border-color: var(--primary);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
.plugin-info {
  display: flex;
  gap: 16px;
  align-items: center;
}
.plugin-icon-wrapper {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(39, 196, 168, 0.1);
  border-radius: 10px;
  color: var(--primary);
}
.plugin-icon-wrapper svg {
  width: 24px;
  height: 24px;
}
.plugin-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.plugin-title {
  font-weight: 700;
  font-size: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.plugin-category {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: var(--border);
  padding: 2px 6px;
  border-radius: 4px;
  color: var(--muted);
  font-weight: 500;
}
.plugin-desc {
  font-size: 13px;
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
  font-size: 13px;
  color: var(--muted);
  min-width: 80px;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #333;
}
.status-dot.status-active {
  background: var(--primary);
  box-shadow: 0 0 8px var(--primary);
}
</style>

<?php
render_footer();
