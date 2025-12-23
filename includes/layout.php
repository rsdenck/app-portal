<?php

function render_header(string $title, ?array $user = null): void
{
    global $pdo;
    $sessionUser = $user ?? current_user();
    $role = $sessionUser['role'] ?? null;
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= h($title) ?></title>
      <link rel="icon" href="/assets/favicon_round.svg" type="image/svg+xml">
      <link rel="icon" href="/assets/favicon.png" type="image/png">
      <link rel="shortcut icon" href="/assets/favicon.png" type="image/png">
      <link rel="stylesheet" href="/assets/style.css">
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <script>
      document.addEventListener('DOMContentLoaded', function () {
        // Theme Management
        var body = document.body;
        var themeToggle = document.querySelector('[data-theme-toggle]');
        var savedTheme = localStorage.getItem('theme') || 'dark';
        
        if (savedTheme === 'light') {
          body.classList.add('theme-light');
        }

        if (themeToggle) {
          themeToggle.addEventListener('click', function() {
            body.classList.toggle('theme-light');
            var currentTheme = body.classList.contains('theme-light') ? 'light' : 'dark';
            localStorage.setItem('theme', currentTheme);
          });
        }

        var toggle = document.querySelector('[data-sidebar-toggle]');
        var shell = document.querySelector('.app-shell');
        if (toggle && shell) {
          toggle.addEventListener('click', function () {
            shell.classList.toggle('sidebar-open');
          });
        }
        if (shell) {
          var collapsed = localStorage.getItem('sidebarCollapsed') === '1';
          if (collapsed) {
            shell.classList.add('sidebar-collapsed');
          }
        }
        var collapseBtn = document.querySelector('[data-sidebar-collapse]');
        if (collapseBtn && shell) {
          var updateLabel = function () {
            if (shell.classList.contains('sidebar-collapsed')) {
              collapseBtn.textContent = 'Expandir menu';
            } else {
              collapseBtn.textContent = 'Encolher menu';
            }
          };
          updateLabel();
          collapseBtn.addEventListener('click', function () {
            shell.classList.toggle('sidebar-collapsed');
            var isCollapsed = shell.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
            updateLabel();
          });
        }
      });
      </script>
    </head>
    <body>
    <?php if ($sessionUser): ?>
      <div class="app-shell">
        <aside class="sidebar">
          <div class="sidebar-header">
            <div class="sidebar-logo">
              <img src="/assets/logo_armazem.png" alt="Armazém Cloud" class="sidebar-logo-img">
            </div>
          </div>
          <?php if ($role === 'cliente'): ?>
            <div class="sidebar-group">
              <a class="side-link<?= str_ends_with($script, '/cliente_chamado.php') ? ' side-link-active' : '' ?>" href="/cliente_chamado.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Chamados</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_hosts.php') ? ' side-link-active' : '' ?>" href="/cliente_hosts.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Monitoramento</span>
              </a>

              <?php
                $activePlugins = plugins_get_active($pdo);
                $pluginMenus = plugin_get_menus($pdo, $sessionUser, $activePlugins);
                foreach ($pluginMenus as $pm):
                  $isActive = str_starts_with($script, str_replace('.php', '', $pm['url']));
              ?>
                <a class="side-link<?= $isActive ? ' side-link-active' : '' ?>" href="<?= h($pm['url']) ?>">
                  <?php if ($pm['icon'] === 'box'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'shield'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'cloud'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'mail'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="22,6 12,13 2,6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'server'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="6" x2="6" y2="6" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="18" x2="6" y2="18" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'share-2'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="6" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="19" r="3" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49" fill="none" stroke="currentColor" stroke-width="2"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'globe'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="12" x2="22" y2="12" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'activity'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'bar-chart'): ?>
                     <svg class="sidebar-icon" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="18" y1="20" x2="18" y2="4" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="20" x2="6" y2="16" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                   <?php elseif ($pm['icon'] === 'monitor'): ?>
                     <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" fill="none" stroke="currentColor" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                   <?php elseif ($pm['icon'] === 'terminal'): ?>
                     <svg class="sidebar-icon" viewBox="0 0 24 24"><polyline points="4 17 10 11 4 5" fill="none" stroke="currentColor" stroke-width="2"/><line x1="12" y1="19" x2="20" y2="19" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                   <?php endif; ?>
                  <span class="side-link-text"><?= h($pm['label']) ?></span>
                </a>
                <?php if (!empty($pm['sub'])): ?>
                  <div class="sidebar-sub-group" style="padding-left: 20px; display: <?= $isActive ? 'block' : 'none' ?>;">
                    <?php foreach ($pm['sub'] as $sub): ?>
                      <a class="side-link<?= str_ends_with($script, $sub['url']) ? ' side-link-active' : '' ?>" href="<?= h($sub['url']) ?>" style="font-size: 0.85rem; padding: 6px 12px;">
                        <span class="side-link-text"><?= h($sub['label']) ?></span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($pm['sub'])): ?>
                  <div class="sidebar-sub-group" style="padding-left: 20px; display: <?= $isActive ? 'block' : 'none' ?>;">
                    <?php foreach ($pm['sub'] as $sub): ?>
                      <a class="side-link<?= str_ends_with($script, $sub['url']) ? ' side-link-active' : '' ?>" href="<?= h($sub['url']) ?>" style="font-size: 0.85rem; padding: 6px 12px;">
                        <span class="side-link-text"><?= h($sub['label']) ?></span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>

              <a class="side-link<?= str_ends_with($script, '/cliente_ativos.php') ? ' side-link-active' : '' ?>" href="/cliente_ativos.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" fill="none" stroke="currentColor" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Ativos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_boleto.php') ? ' side-link-active' : '' ?>" href="/cliente_boleto.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M4 10h16M4 14h16M4 18h16M4 6h16" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Boletos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_billing.php') ? ' side-link-active' : '' ?>" href="/cliente_billing.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Billing</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_relatorios.php') ? ' side-link-active' : '' ?>" href="/cliente_relatorios.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 12A10 10 0 0 0 12 2v10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Relatórios</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_config.php') ? ' side-link-active' : '' ?>" href="/cliente_config.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Configurações</span>
              </a>
            </div>
          <?php elseif ($role === 'atendente'): ?>
            <div class="sidebar-group">
              <a class="side-link<?= str_ends_with($script, '/atendente_gestao.php') ? ' side-link-active' : '' ?>" href="/atendente_gestao.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="9 22 9 12 15 12 15 22" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Dashboard</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_fila.php') ? ' side-link-active' : '' ?>" href="/atendente_fila.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Chamados</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_atendimentos.php') ? ' side-link-active' : '' ?>" href="/atendente_atendimentos.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M23 21v-2a4 4 0 0 0-3-3.87" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Atendimentos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_atendente.php') ? ' side-link-active' : '' ?>" href="/tk_atendente.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="16 11 18 13 22 9" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Usuários</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_cliente.php') ? ' side-link-active' : '' ?>" href="/tk_cliente.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Clientes</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_empresas.php') ? ' side-link-active' : '' ?>" href="/tk_empresas.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14M9 21V11m6 10V11M12 3L2 7h20L12 3z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Empresas</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_ativos.php') ? ' side-link-active' : '' ?>" href="/atendente_ativos.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" fill="none" stroke="currentColor" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Ativos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_monitoramento.php') ? ' side-link-active' : '' ?>" href="/atendente_monitoramento.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Monitoramento</span>
              </a>
              <a class="side-link<?= (str_ends_with($script, '/atendente_projetos.php') || str_ends_with($script, '/atendente_projeto_view.php')) ? ' side-link-active' : '' ?>" href="/atendente_projetos.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Projetos</span>
              </a>

              <?php
                $activePlugins = plugins_get_active($pdo);
                $pluginMenus = plugin_get_menus($pdo, $sessionUser, $activePlugins);
                foreach ($pluginMenus as $pm):
                  $isActive = str_starts_with($script, str_replace('.php', '', $pm['url']));
              ?>
                <a class="side-link<?= $isActive ? ' side-link-active' : '' ?>" href="<?= h($pm['url']) ?>">
                  <?php if ($pm['icon'] === 'box'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'shield'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'cloud'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'mail'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="22,6 12,13 2,6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'server'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="6" x2="6" y2="6" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="18" x2="6" y2="18" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'share-2'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="6" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="19" r="3" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49" fill="none" stroke="currentColor" stroke-width="2"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'globe'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="12" x2="22" y2="12" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'activity'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php elseif ($pm['icon'] === 'bar-chart'): ?>
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="18" y1="20" x2="18" y2="4" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="20" x2="6" y2="16" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                  <?php endif; ?>
                  <span class="side-link-text"><?= h($pm['label']) ?></span>
                </a>
              <?php endforeach; ?>

              <a class="side-link<?= str_ends_with($script, '/atendente_relatorios.php') ? ' side-link-active' : '' ?>" href="/atendente_relatorios.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 12A10 10 0 0 0 12 2v10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Relatórios</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_docs.php') ? ' side-link-active' : '' ?>" href="/tk_docs.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Documentação</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_config.php') ? ' side-link-active' : '' ?>" href="/atendente_config.php">
                <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span class="side-link-text">Configurações</span>
              </a>
            </div>
          <?php endif; ?>
          <div class="sidebar-footer">
            <button class="btn sidebar-collapse-toggle" type="button" data-sidebar-collapse>Encolher menu</button>
            <a class="side-link" href="/logout.php">
              <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="16 17 21 12 16 7" fill="none" stroke="currentColor" stroke-width="2"/><line x1="21" y1="12" x2="9" y2="12" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              <span class="side-link-text">Sair</span>
            </a>
          </div>
        </aside>
        <div class="main">
          <div class="container">
            <div class="topbar">
              <div></div>
              <div style="display:flex;gap:8px;align-items:center">
                <?php
                  $unreadCount = ticket_unread_count_global($pdo, (int)$sessionUser['id']);
                ?>
                <a href="<?= $role === 'cliente' ? '/cliente_chamado.php' : '/atendente_fila.php' ?>" class="btn" title="Notificações" style="padding:8px; position:relative; display: flex; align-items:center; justify-content:center">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                  </svg>
                  <?php if ($unreadCount > 0): ?>
                    <span style="position:absolute; top:-2px; right:-2px; background:var(--danger); color:white; font-size:10px; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; border: 2px solid var(--bg); box-shadow: 0 0 5px var(--danger)">
                      <?= $unreadCount > 9 ? '+' : $unreadCount ?>
                    </span>
                  <?php endif; ?>
                </a>
                <button class="btn" type="button" data-theme-toggle title="Mudar tema" style="padding:8px">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </button>
                <button class="btn sidebar-toggle" type="button" data-sidebar-toggle>Menu</button>
              </div>
            </div>
    <?php else: ?>
      <div class="container">
        <div class="topbar">
          <div></div>
          <button class="btn" type="button" data-theme-toggle title="Mudar tema" style="padding:8px">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          </button>
        </div>
    <?php endif;
}

function render_footer(): void
{
    $sessionUser = current_user();
    ?>
    <?php if ($sessionUser): ?>
          </div>
        </div>
      </div>
    <?php else: ?>
      </div>
      <div class="corner-action">
        <a class="btn" href="/atendente_login.php">Atendente</a>
      </div>
    <?php endif; ?>
    </body>
    </html>
    <?php
}
