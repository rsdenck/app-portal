<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
company_ensure_schema($pdo);
$success = '';
$error = '';

$companies = company_list($pdo);

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $companyId = safe_int($_POST['company_id'] ?? null);
        $companyName = '';
        $document = trim((string)($_POST['document'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $hostgroupid = trim((string)($_POST['hostgroupid'] ?? ''));

        if ($name === '' || $email === '') {
            $error = 'Preencha nome e email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido.';
        } elseif ($action === 'create' && strlen($password) < 8) {
            $error = 'Defina uma senha com no mínimo 8 caracteres.';
        } elseif (!$companyId) {
            $error = 'Selecione uma empresa.';
        } else {
            $companyRow = null;
            foreach ($companies as $c) {
                if ((int)$c['id'] === (int)$companyId) {
                    $companyRow = $c;
                    break;
                }
            }
            if (!$companyRow) {
                $error = 'Empresa inválida.';
            } else {
                $companyName = (string)$companyRow['name'];
                if ($action === 'create') {
                    if (user_find_by_email($pdo, $email)) {
                        $error = 'Email já cadastrado.';
                    }
                } else {
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                    $stmt->execute([$email, $editId]);
                    if ($stmt->fetch()) {
                        $error = 'Email já cadastrado em outro usuário.';
                    }
                }
            }
        }

        if ($error === '') {
            if ($action === 'create') {
                $clientId = user_create($pdo, 'cliente', $name, $email, $password);
                $pdo->prepare('UPDATE client_profiles SET company_id = ?, company_name = ?, document = ?, phone = ? WHERE user_id = ?')
                    ->execute([(int)$companyId, $companyName, $document, $phone, $clientId]);
            } else {
                $clientId = $editId;
                user_update_name($pdo, $clientId, $name);
                $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                $stmt->execute([$email, $clientId]);
                $stmt = $pdo->prepare('UPDATE client_profiles SET company_id = ?, company_name = ?, document = ?, phone = ? WHERE user_id = ?');
                $stmt->execute([(int)$companyId, $companyName, $document, $phone, $clientId]);
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        $error = 'Nova senha precisa ter no mínimo 8 caracteres.';
                    } else {
                        user_update_password($pdo, $clientId, $password);
                    }
                }
            }

            if ($error === '') {
                $pdo->prepare('DELETE FROM zabbix_hostgroups WHERE client_user_id = ?')->execute([$clientId]);
                if ($hostgroupid !== '') {
                    $stmt = $pdo->prepare('INSERT INTO zabbix_hostgroups (client_user_id, hostgroupid, name) VALUES (?,?,?)');
                    $stmt->execute([$clientId, $hostgroupid, $hostgroupid]);
                }
                $success = $action === 'create' ? 'Cliente criado com sucesso.' : 'Cliente atualizado com sucesso.';
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ?');
            $stmt->execute([$deleteId, 'cliente']);
            $success = 'Cliente excluído.';
        }
    }
}

$clientsStmt = $pdo->query(
    "SELECT u.id, u.name, u.email, cp.company_id, cp.company_name, cp.document, cp.phone
     FROM users u
     LEFT JOIN client_profiles cp ON cp.user_id = u.id
     WHERE u.role = 'cliente'
     ORDER BY u.name ASC"
);
$clients = $clientsStmt->fetchAll();

$hostgroupsByClient = [];
if ($clients) {
    $ids = array_map(fn($c) => (int)$c['id'], $clients);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT client_user_id, hostgroupid
         FROM zabbix_hostgroups
         WHERE client_user_id IN ($placeholders)
         ORDER BY id ASC"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $cid = (int)$row['client_user_id'];
        if (!isset($hostgroupsByClient[$cid])) {
            $hostgroupsByClient[$cid] = [];
        }
        $hostgroupsByClient[$cid][] = (string)$row['hostgroupid'];
    }
}

$editingClient = null;
if ($editId > 0) {
    foreach ($clients as $c) {
        if ((int)$c['id'] === $editId) {
            $editingClient = $c;
            break;
        }
    }
}

render_header('Atendente · Clientes', current_user());
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div>
      <div style="font-weight:700;margin-bottom:4px">Gerenciamento de Clientes</div>
      <div class="muted">Gerencie os usuários clientes que acessam o portal.</div>
    </div>
    <a class="btn primary" href="/app/tk_cliente.php?action=create">Novo cliente</a>
  </div>
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($action === 'create' || ($action === 'update' && $editingClient)): ?>
    <?php
      $formClient = $editingClient ?: ['id' => 0, 'name' => '', 'email' => '', 'company_id' => null, 'company_name' => '', 'document' => '', 'phone' => ''];
      $clientId = (int)$formClient['id'];
      $formHostgroup = '';
      if ($clientId > 0 && !empty($hostgroupsByClient[$clientId])) {
          $formHostgroup = (string)$hostgroupsByClient[$clientId][0];
      }
      $selectedCompanyId = (int)($formClient['company_id'] ?? 0);
    ?>
    <form method="post" style="margin-bottom:18px">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= h($editingClient ? 'update' : 'create') ?>">
      <?php if ($editingClient): ?>
        <input type="hidden" name="id" value="<?= (int)$editingClient['id'] ?>">
      <?php endif; ?>
      <div class="row">
        <div class="col">
          <label>Nome completo</label>
          <input name="name" value="<?= h((string)$formClient['name']) ?>" required>
        </div>
        <div class="col">
          <label>Email</label>
          <input name="email" type="email" value="<?= h((string)$formClient['email']) ?>" required>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <label>Empresa</label>
          <select name="company_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($companies as $company): ?>
              <option value="<?= (int)$company['id'] ?>" <?= $selectedCompanyId === (int)$company['id'] ? 'selected' : '' ?>>
                <?= h((string)$company['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label>Documento</label>
          <input name="document" value="<?= h((string)($formClient['document'] ?? '')) ?>">
        </div>
        <div class="col">
          <label>Telefone</label>
          <input name="phone" value="<?= h((string)($formClient['phone'] ?? '')) ?>">
        </div>
      </div>
      <div class="row">
        <div class="col">
          <label>ID do monitoramento (HostGroup)</label>
          <input name="hostgroupid" value="<?= h($formHostgroup) ?>" placeholder="Ex: 12345">
        </div>
        <div class="col">
          <label><?= $editingClient ? 'Nova senha' : 'Senha inicial' ?></label>
          <input name="password" type="password" autocomplete="new-password" <?= $editingClient ? '' : 'required' ?> placeholder="<?= $editingClient ? 'Preencha para redefinir' : 'Mínimo 8 caracteres' ?>">
        </div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn primary" type="submit"><?= $editingClient ? 'Salvar alterações' : 'Criar cliente' ?></button>
        <a class="btn" href="/app/tk_cliente.php">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>Email</th>
        <th>Empresa</th>
        <th>Documento</th>
        <th>Telefone</th>
        <th>HostGroup</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$clients): ?>
        <tr><td colspan="8" class="muted">Nenhum cliente cadastrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($clients as $c): ?>
        <?php
          $cid = (int)$c['id'];
          $hostgroupLabel = '';
          if (!empty($hostgroupsByClient[$cid])) {
              $hostgroupLabel = implode(', ', $hostgroupsByClient[$cid]);
          }
        ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><?= h((string)$c['name']) ?></td>
          <td><?= h((string)$c['email']) ?></td>
          <td><?= h((string)($c['company_name'] ?? '')) ?></td>
          <td><?= h((string)($c['document'] ?? '')) ?></td>
          <td><?= h((string)($c['phone'] ?? '')) ?></td>
          <td><?= h($hostgroupLabel) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="/app/tk_cliente.php?action=update&id=<?= (int)$c['id'] ?>">Editar</a>
            <form method="post" onsubmit="return confirm('Deseja realmente excluir este cliente?');">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn danger" type="submit">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();



