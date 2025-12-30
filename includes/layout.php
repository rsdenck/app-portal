<?php
declare(strict_types=1);

function render_sidebar_icon(string $icon): void
{
    switch ($icon) {
        case 'settings':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'dashboard':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="9 22 9 12 15 12 15 22" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'ticket':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'users':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M23 21v-2a4 4 0 0 0-3-3.87" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'user-check':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="16 11 18 13 22 9" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'user':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'briefcase':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'box':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8" y1="21" x2="16" y2="21" fill="none" stroke="currentColor" stroke-width="2"/><line x1="12" y1="17" x2="12" y2="21" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'monitor':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'folder':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'dollar-sign':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'pie-chart':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83" fill="none" stroke="currentColor" stroke-width="2"/><path d="M22 12A10 10 0 0 0 12 2v10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'book':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'log-out':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="16 17 21 12 16 7" fill="none" stroke="currentColor" stroke-width="2"/><line x1="21" y1="12" x2="9" y2="12" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'activity':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'shield':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'cloud':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'mail':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="22,6 12,13 2,6" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'server':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="6" x2="6" y2="6" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="18" x2="6" y2="18" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'share-2':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="6" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="19" r="3" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49" fill="none" stroke="currentColor" stroke-width="2"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'globe':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="12" x2="22" y2="12" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'bar-chart':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="18" y1="20" x2="18" y2="4" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="20" x2="6" y2="16" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'vm':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 21h8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 17v4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 8l5 3 5-3-5-3-5 3z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 12l5 3 5-3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'cpu':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="9" y="9" width="6" height="6" fill="none" stroke="currentColor" stroke-width="2"/><line x1="9" y1="1" x2="9" y2="4" fill="none" stroke="currentColor" stroke-width="2"/><line x1="15" y1="1" x2="15" y2="4" fill="none" stroke="currentColor" stroke-width="2"/><line x1="9" y1="20" x2="9" y2="23" fill="none" stroke="currentColor" stroke-width="2"/><line x1="15" y1="20" x2="15" y2="23" fill="none" stroke="currentColor" stroke-width="2"/><line x1="20" y1="9" x2="23" y2="9" fill="none" stroke="currentColor" stroke-width="2"/><line x1="1" y1="9" x2="4" y2="9" fill="none" stroke="currentColor" stroke-width="2"/><line x1="1" y1="15" x2="4" y2="15" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'search':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/><line x1="21" y1="21" x2="16.65" y2="16.65" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'shield-off':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M19.69 14a6.9 6.9 0 0 0 .31-2V5l-8-3-3.11 1.17" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4.73 4.73L4 5v7c0 6 8 10 8 10a20.29 20.29 0 0 0 5.62-4.38" fill="none" stroke="currentColor" stroke-width="2"/><line x1="1" y1="1" x2="23" y2="23" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'zap':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        case 'bank':
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14M9 21V11m6 10V11M12 3L2 7h20L12 3z" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
        default:
            echo '<svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            break;
    }
}

function render_header(string $title, ?array $user = null, bool $showLayout = true): void
{
    global $pdo;
    $sessionUser = $user ?? current_user();
    $role = $sessionUser['role'] ?? null;
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $isConfigActive = str_contains($script, 'atendente_config.php') || 
                      str_contains($script, 'atendente_conta.php') || 
                      str_contains($script, 'atendente_definicoes.php') ||
                      str_contains($script, 'atendente_plugins.php') ||
                      str_contains($script, 'atendente_plugin_config.php');
    $isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
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
      <?php if ($isEmbed): ?>
      <style>
        body { background: transparent !important; padding: 0 !important; margin: 0 !important; }
        .container { max-width: 100% !important; padding: 0 !important; }
        .card { margin: 0 !important; border-radius: 0 !important; border: none !important; box-shadow: none !important; }
      </style>
      <?php endif; ?>
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <script>
      document.addEventListener('DOMContentLoaded', function () {
        // Theme Management
        var body = document.body;
        var themeToggle = document.querySelector('[data-theme-toggle]');
        var serverTheme = <?= json_encode($sessionUser['theme'] ?? 'dark') ?>;
        var savedTheme = localStorage.getItem('theme') || serverTheme;
        
        var applyTheme = function(theme, saveToServer = false) {
          body.classList.remove('theme-light', 'theme-cyan', 'theme-navy');
          if (theme !== 'dark') {
            body.classList.add('theme-' + theme);
          }
          localStorage.setItem('theme', theme);

          if (saveToServer) {
            fetch('/api/theme.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ theme: theme })
            });
          }
        };

        applyTheme(savedTheme);

        if (themeToggle) {
          themeToggle.addEventListener('click', function() {
            var themes = ['dark', 'light', 'cyan', 'navy'];
            var currentTheme = localStorage.getItem('theme') || serverTheme;
            var nextIndex = (themes.indexOf(currentTheme) + 1) % themes.length;
            applyTheme(themes[nextIndex], true);
          });
        }

        // Notification Dropdown
        var notifToggle = document.querySelector('[data-notif-toggle]');
        var notifDropdown = document.querySelector('[data-notif-dropdown]');
        if (notifToggle && notifDropdown) {
          notifToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notifDropdown.classList.toggle('active');
          });
          document.addEventListener('click', function(e) {
            if (!notifDropdown.contains(e.target)) {
              notifDropdown.classList.remove('active');
            }
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
    <body<?= !$showLayout ? ' class="auth-body"' : '' ?>>
    <?php if ($sessionUser && $showLayout && !$isEmbed): ?>
      <div class="app-shell">
        <aside class="sidebar">
          <div class="sidebar-header">
            <div class="sidebar-logo">
              <img src="/assets/logo_armazem.png" alt="Armazém Cloud" class="sidebar-logo-img">
            </div>
          </div>
          <?php if ($role === 'cliente'): ?>
            <div class="sidebar-group">
              <a class="side-link<?= str_ends_with($script, '/cliente_chamado.php') ? ' side-link-active' : '' ?>" href="/client/cliente_chamado.php">
                <?php render_sidebar_icon('ticket'); ?>
                <span class="side-link-text">Chamados</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_hosts.php') ? ' side-link-active' : '' ?>" href="/client/cliente_hosts.php">
                <?php render_sidebar_icon('monitor'); ?>
                <span class="side-link-text">Monitoramento</span>
              </a>

              <?php
                $activePlugins = plugins_get_active($pdo);
                $groupedMenus = plugin_get_menus($pdo, $sessionUser, $activePlugins);
                foreach ($groupedMenus as $categoryName => $cat):
                  if ($categoryName === 'Monitoramento') continue;
                  
                  // Verificar se a categoria está ativa (pelo parâmetro na URL ou se um plugin dela está aberto)
                  $isCatActive = ($_GET['category'] ?? '') === $categoryName;
                  if (!$isCatActive) {
                    foreach ($cat['plugins'] as $p) {
                      if (str_starts_with($script, str_replace('.php', '', $p['url']))) {
                        $isCatActive = true;
                        break;
                      }
                    }
                  }
              ?>
                <?php
                  $targetUrl = "/app/atendente_plugins.php?category=" . urlencode($categoryName);
                  if ($role === 'atendente' && $categoryName === 'Redes') {
                    $targetUrl = "/app/plugin_dflow_maps.php";
                  }
                ?>
                <a class="side-link<?= $isCatActive ? ' side-link-active' : '' ?>" href="<?= $targetUrl ?>">
                  <?php render_sidebar_icon($cat['icon']); ?>
                  <span class="side-link-text"><?= h($categoryName) ?></span>
                </a>
              <?php endforeach; ?>

              <a class="side-link<?= str_ends_with($script, '/cliente_ativos.php') ? ' side-link-active' : '' ?>" href="/client/cliente_ativos.php">
                <?php render_sidebar_icon('box'); ?>
                <span class="side-link-text">Ativos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_boleto.php') ? ' side-link-active' : '' ?>" href="/client/cliente_boleto.php">
                <?php render_sidebar_icon('activity'); ?>
                <span class="side-link-text">Boletos</span>
              </a>
              <?php if (user_has_permission($sessionUser, 'billing.view')): ?>
                <a class="side-link<?= str_ends_with($script, '/cliente_billing.php') ? ' side-link-active' : '' ?>" href="/client/cliente_billing.php">
                  <?php render_sidebar_icon('dollar-sign'); ?>
                  <span class="side-link-text">Billing</span>
                </a>
              <?php endif; ?>
              <a class="side-link<?= str_ends_with($script, '/cliente_relatorios.php') ? ' side-link-active' : '' ?>" href="/client/cliente_relatorios.php">
                <?php render_sidebar_icon('pie-chart'); ?>
                <span class="side-link-text">Relatórios</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/cliente_config.php') ? ' side-link-active' : '' ?>" href="/client/cliente_config.php">
                <?php render_sidebar_icon('settings'); ?>
                <span class="side-link-text">Configurações</span>
              </a>
            </div>
          <?php elseif ($role === 'atendente'): ?>
            <div class="sidebar-group">
              <a class="side-link<?= str_ends_with($script, '/atendente_gestao.php') ? ' side-link-active' : '' ?>" href="/app/atendente_gestao.php">
                <?php render_sidebar_icon('dashboard'); ?>
                <span class="side-link-text">Dashboard</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_fila.php') ? ' side-link-active' : '' ?>" href="/app/atendente_fila.php">
                <?php render_sidebar_icon('ticket'); ?>
                <span class="side-link-text">Chamados</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_atendimentos.php') ? ' side-link-active' : '' ?>" href="/app/atendente_atendimentos.php">
                <?php render_sidebar_icon('users'); ?>
                <span class="side-link-text">Atendimentos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_atendente.php') ? ' side-link-active' : '' ?>" href="/app/tk_atendente.php">
                <?php render_sidebar_icon('user-check'); ?>
                <span class="side-link-text">Usuários</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_cliente.php') ? ' side-link-active' : '' ?>" href="/app/tk_cliente.php">
                <?php render_sidebar_icon('user'); ?>
                <span class="side-link-text">Clientes</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_empresas.php') ? ' side-link-active' : '' ?>" href="/app/tk_empresas.php">
                <?php render_sidebar_icon('bank'); ?>
                <span class="side-link-text">Empresas</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_ativos.php') ? ' side-link-active' : '' ?>" href="/app/atendente_ativos.php">
                <?php render_sidebar_icon('box'); ?>
                <span class="side-link-text">Ativos</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_monitoramento.php') ? ' side-link-active' : '' ?>" href="/app/atendente_monitoramento.php">
                <?php render_sidebar_icon('monitor'); ?>
                <span class="side-link-text">Monitoramento</span>
              </a>
              <a class="side-link<?= (str_ends_with($script, '/atendente_projetos.php') || str_ends_with($script, '/atendente_projeto_view.php')) ? ' side-link-active' : '' ?>" href="/app/atendente_projetos.php">
                <?php render_sidebar_icon('folder'); ?>
                <span class="side-link-text">Projetos</span>
              </a>

              <?php if (user_has_permission($sessionUser, 'billing.manage')): ?>
                <a class="side-link<?= str_ends_with($script, '/atendente_billing.php') ? ' side-link-active' : '' ?>" href="/app/atendente_billing.php">
                  <?php render_sidebar_icon('dollar-sign'); ?>
                  <span class="side-link-text">Billing</span>
                </a>
              <?php endif; ?>

              <?php
                $activePlugins = plugins_get_active($pdo);
                $groupedMenus = plugin_get_menus($pdo, $sessionUser, $activePlugins);
                foreach ($groupedMenus as $categoryName => $cat):
                  if ($categoryName === 'Monitoramento' || $categoryName === 'Inteligência' || $categoryName === 'Segurança') continue;
                  
                  // Verificar se a categoria está ativa
                  $isCatActive = ($_GET['category'] ?? '') === $categoryName || str_contains($script, 'plugin_dflow_maps.php');
                  if (!$isCatActive) {
                    foreach ($cat['plugins'] as $p) {
                      if (str_starts_with($script, str_replace('.php', '', $p['url']))) {
                        $isCatActive = true;
                        break;
                      }
                    }
                  }

                  $targetUrl = "/app/atendente_plugins.php?category=" . urlencode($categoryName);
                  if ($categoryName === 'Redes') {
                    $targetUrl = "/app/plugin_dflow_maps.php";
                  }
              ?>
                <a class="side-link<?= $isCatActive ? ' side-link-active' : '' ?>" href="<?= $targetUrl ?>">
                  <?php render_sidebar_icon($cat['icon']); ?>
                  <span class="side-link-text"><?= h($categoryName) ?></span>
                </a>
              <?php endforeach; ?>

              <a class="side-link<?= str_ends_with($script, '/atendente_relatorios.php') ? ' side-link-active' : '' ?>" href="/app/atendente_relatorios.php">
                <?php render_sidebar_icon('pie-chart'); ?>
                <span class="side-link-text">Relatórios</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/tk_docs.php') ? ' side-link-active' : '' ?>" href="/app/tk_docs.php">
                <?php render_sidebar_icon('book'); ?>
                <span class="side-link-text">Documentação</span>
              </a>
              <a class="side-link<?= str_ends_with($script, '/atendente_config.php') || str_ends_with($script, '/atendente_conta.php') || str_ends_with($script, '/atendente_definicoes.php') || str_ends_with($script, '/atendente_plugins.php') || str_ends_with($script, '/atendente_plugin_config.php') ? ' side-link-active' : '' ?>" href="/app/atendente_config.php">
                <?php render_sidebar_icon('settings'); ?>
                <span class="side-link-text">Configurações</span>
              </a>
            </div>
          <?php endif; ?>
          <div class="sidebar-footer">
            <button class="btn sidebar-collapse-toggle" type="button" data-sidebar-collapse>Encolher menu</button>
            <a class="side-link" href="/logout.php">
              <?php render_sidebar_icon('log-out'); ?>
              <span class="side-link-text">Sair</span>
            </a>
          </div>
        </aside>
        <div class="main">
          <div class="container">
            <div class="topbar">
              <div style="display:flex;gap:8px;align-items:center">
                <!-- Back button removed as it was redundant -->
              </div>
              <div style="display:flex;gap:8px;align-items:center">
                <?php
                  $unreadCount = ticket_unread_count_global($pdo, (int)$sessionUser['id']);
                  $unreadList = ticket_unread_list_global($pdo, (int)$sessionUser['id']);
                ?>
                <div class="dropdown" data-notif-dropdown>
                  <button class="btn" type="button" data-notif-toggle title="Notificações" style="padding:8px; position:relative; display: flex; align-items:center; justify-content:center">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                      <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                      <span style="position:absolute; top:-2px; right:-2px; background:var(--danger); color:white; font-size:10px; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; border: 2px solid var(--bg); box-shadow: 0 0 5px var(--danger)">
                        <?= $unreadCount > 9 ? '+' : $unreadCount ?>
                      </span>
                    <?php endif; ?>
                  </button>
                  <div class="dropdown-content">
                    <div class="dropdown-header">
                      <span>Notificações</span>
                      <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?= $unreadCount ?> unread</span>
                      <?php endif; ?>
                    </div>
                    <?php if (empty($unreadList)): ?>
                      <div class="notification-empty">Nenhuma notificação nova</div>
                    <?php else: ?>
                      <?php foreach ($unreadList as $notif): ?>
                        <?php 
                          $url = ($role === 'cliente') 
                            ? "/client/cliente_ticket.php?id=" . (int)$notif['id']
                            : "/app/atendente_ticket.php?id=" . (int)$notif['id'];
                        ?>
                        <a href="<?= h($url) ?>" class="notification-item">
                          <span class="notification-category"><?= h((string)$notif['category_name']) ?></span>
                          <span class="notification-subject"><?= h((string)$notif['subject']) ?></span>
                          <span class="notification-time"><?= date('d/m/Y H:i', strtotime((string)$notif['created_at'])) ?></span>
                        </a>
                      <?php endforeach; ?>
                      <div style="padding:8px; text-align:center; border-top:1px solid var(--border)">
                        <a href="<?= $role === 'cliente' ? '/client/cliente_chamado.php' : '/app/atendente_fila.php' ?>" style="font-size:12px; font-weight:600">Ver todos os chamados</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <button class="btn" type="button" data-theme-toggle title="Mudar tema" style="padding:8px">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </button>
                <button class="btn sidebar-toggle" type="button" data-sidebar-toggle>Menu</button>
              </div>
            </div>
    <?php else: ?>
      <div class="topbar auth-topbar">
        <div></div>
        <button class="btn" type="button" data-theme-toggle title="Mudar tema" style="padding:8px">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        </button>
      </div>
    <?php endif;
}

function render_footer(bool $showLayout = true): void
{
    $sessionUser = current_user();
    ?>
    <?php if ($sessionUser && $showLayout): ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
    </body>
    </html>
    <?php
}



