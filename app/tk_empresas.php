<?php

require __DIR__ . '/../includes/bootstrap.php';
/** @var PDO $pdo */

$user = require_login('atendente');
company_ensure_schema($pdo);
$success = '';
$error = '';

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$editId = safe_int($_GET['id'] ?? ($_POST['id'] ?? null));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $tradeName = trim((string)($_POST['trade_name'] ?? ''));
        $document = trim((string)($_POST['document'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $addressStreet = trim((string)($_POST['address_street'] ?? ''));
        $addressNumber = trim((string)($_POST['address_number'] ?? ''));
        $addressComplement = trim((string)($_POST['address_complement'] ?? ''));
        $addressDistrict = trim((string)($_POST['address_district'] ?? ''));
        $addressCity = trim((string)($_POST['address_city'] ?? ''));
        $addressState = trim((string)($_POST['address_state'] ?? ''));
        $addressZip = trim((string)($_POST['address_zip'] ?? ''));
        $contactName = trim((string)($_POST['contact_name'] ?? ''));
        $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
        $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
        $slaResponseCritical = safe_int($_POST['sla_response_critical'] ?? null);
        $slaResolutionCritical = safe_int($_POST['sla_resolution_critical'] ?? null);
        $slaResponseHigh = safe_int($_POST['sla_response_high'] ?? null);
        $slaResolutionHigh = safe_int($_POST['sla_resolution_high'] ?? null);
        $slaResponseMedium = safe_int($_POST['sla_response_medium'] ?? null);
        $slaResolutionMedium = safe_int($_POST['sla_resolution_medium'] ?? null);
        $slaResponseLow = safe_int($_POST['sla_response_low'] ?? null);
        $slaResolutionLow = safe_int($_POST['sla_resolution_low'] ?? null);
        $slaAvailabilityTargetRaw = trim((string)($_POST['sla_availability_target'] ?? ''));
        $slaAvailabilityTargetRaw = str_replace(',', '.', $slaAvailabilityTargetRaw);
        $slaAvailabilityTarget = $slaAvailabilityTargetRaw === '' ? '' : $slaAvailabilityTargetRaw;
        $slaNotes = trim((string)($_POST['sla_notes'] ?? ''));

        if ($name === '') {
            $error = 'Nome da empresa é obrigatório.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email da empresa inválido.';
        } elseif ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email de contato inválido.';
        }

        if ($error === '') {
            $slaResponseCritical = $slaResponseCritical ?? 0;
            $slaResolutionCritical = $slaResolutionCritical ?? 0;
            $slaResponseHigh = $slaResponseHigh ?? 0;
            $slaResolutionHigh = $slaResolutionHigh ?? 0;
            $slaResponseMedium = $slaResponseMedium ?? 0;
            $slaResolutionMedium = $slaResolutionMedium ?? 0;
            $slaResponseLow = $slaResponseLow ?? 0;
            $slaResolutionLow = $slaResolutionLow ?? 0;
            $slaAvailabilityValue = $slaAvailabilityTarget === '' ? 0.00 : (float)$slaAvailabilityTarget;

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO companies (name, trade_name, document, email, phone, address_street, address_number, address_complement, address_district, address_city, address_state, address_zip, contact_name, contact_phone, contact_email, sla_response_critical_minutes, sla_resolution_critical_minutes, sla_response_high_minutes, sla_resolution_high_minutes, sla_response_medium_minutes, sla_resolution_medium_minutes, sla_response_low_minutes, sla_resolution_low_minutes, sla_availability_target_percent, sla_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $name,
                    $tradeName,
                    $document,
                    $email,
                    $phone,
                    $addressStreet,
                    $addressNumber,
                    $addressComplement,
                    $addressDistrict,
                    $addressCity,
                    $addressState,
                    $addressZip,
                    $contactName,
                    $contactPhone,
                    $contactEmail,
                    $slaResponseCritical,
                    $slaResolutionCritical,
                    $slaResponseHigh,
                    $slaResolutionHigh,
                    $slaResponseMedium,
                    $slaResolutionMedium,
                    $slaResponseLow,
                    $slaResolutionLow,
                    $slaAvailabilityValue,
                    $slaNotes,
                ]);
                $success = 'Empresa criada com sucesso.';
                $action = '';
                $editId = null;
            } else {
                $companyId = $editId;
                if (!$companyId) {
                    $error = 'Empresa inválida.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE companies SET name = ?, trade_name = ?, document = ?, email = ?, phone = ?, address_street = ?, address_number = ?, address_complement = ?, address_district = ?, address_city = ?, address_state = ?, address_zip = ?, contact_name = ?, contact_phone = ?, contact_email = ?, sla_response_critical_minutes = ?, sla_resolution_critical_minutes = ?, sla_response_high_minutes = ?, sla_resolution_high_minutes = ?, sla_response_medium_minutes = ?, sla_resolution_medium_minutes = ?, sla_response_low_minutes = ?, sla_resolution_low_minutes = ?, sla_availability_target_percent = ?, sla_notes = ? WHERE id = ?'
                    );
                    $stmt->execute([
                        $name,
                        $tradeName,
                        $document,
                        $email,
                        $phone,
                        $addressStreet,
                        $addressNumber,
                        $addressComplement,
                        $addressDistrict,
                        $addressCity,
                        $addressState,
                        $addressZip,
                        $contactName,
                        $contactPhone,
                        $contactEmail,
                        $slaResponseCritical,
                        $slaResolutionCritical,
                        $slaResponseHigh,
                        $slaResolutionHigh,
                        $slaResponseMedium,
                        $slaResolutionMedium,
                        $slaResponseLow,
                        $slaResolutionLow,
                        $slaAvailabilityValue,
                        $slaNotes,
                        (int)$companyId,
                    ]);
                    $success = 'Empresa atualizada com sucesso.';
                    $action = '';
                    $editId = null;
                }
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = safe_int($_POST['id'] ?? null);
        if (!$deleteId) {
            $error = 'Empresa inválida.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM client_profiles WHERE company_id = ?');
            $stmt->execute([(int)$deleteId]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                $error = 'Não é possível excluir empresa com clientes vinculados.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM companies WHERE id = ?');
                $stmt->execute([(int)$deleteId]);
                $success = 'Empresa excluída.';
            }
        }
    }
}

$companies = company_list($pdo);

$editingCompany = null;
if ($action === 'update' && $editId) {
    $editingCompany = company_find($pdo, (int)$editId);
    if (!$editingCompany) {
        $action = '';
    }
}

render_header('Atendente · Empresas', current_user());
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div>
      <div style="font-weight:700;margin-bottom:4px">Gerenciamento de Empresas</div>
      <div class="muted">Cadastre empresas e vincule clientes de forma centralizada.</div>
    </div>
    <a class="btn primary" href="/app/tk_empresas.php?action=create">Nova empresa</a>
  </div>
  <?php if ($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($action === 'create' || ($action === 'update' && $editingCompany)): ?>
    <?php
      $formCompany = $editingCompany ?: [
          'id' => 0,
          'name' => '',
          'trade_name' => '',
          'document' => '',
          'email' => '',
          'phone' => '',
          'address_street' => '',
          'address_number' => '',
          'address_complement' => '',
          'address_district' => '',
          'address_city' => '',
          'address_state' => '',
          'address_zip' => '',
          'contact_name' => '',
          'contact_phone' => '',
          'contact_email' => '',
          'sla_response_critical_minutes' => 10,
          'sla_resolution_critical_minutes' => 60,
          'sla_response_high_minutes' => 30,
          'sla_resolution_high_minutes' => 120,
          'sla_response_medium_minutes' => 60,
          'sla_resolution_medium_minutes' => 240,
          'sla_response_low_minutes' => 120,
          'sla_resolution_low_minutes' => 480,
          'sla_availability_target_percent' => 99.50,
          'sla_notes' => '',
      ];
    ?>
    <form method="post" style="margin-bottom:18px">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= h($editingCompany ? 'update' : 'create') ?>">
      <?php if ($editingCompany): ?>
        <input type="hidden" name="id" value="<?= (int)$editingCompany['id'] ?>">
      <?php endif; ?>
      <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <button class="btn" type="button" data-company-tab-button="geral">Geral</button>
        <button class="btn" type="button" data-company-tab-button="endereco">Endereço</button>
        <button class="btn" type="button" data-company-tab-button="comercial">Comercial</button>
        <button class="btn" type="button" data-company-tab-button="sla">SLA</button>
      </div>
      <div data-company-tab="geral">
        <div class="row">
          <div class="col">
            <label>Razão social</label>
            <input name="name" value="<?= h((string)($formCompany['name'] ?? '')) ?>" required>
          </div>
          <div class="col">
            <label>Nome fantasia</label>
            <input name="trade_name" value="<?= h((string)($formCompany['trade_name'] ?? '')) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>CNPJ/CPF</label>
            <input name="document" value="<?= h((string)($formCompany['document'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Email principal</label>
            <input name="email" type="email" value="<?= h((string)($formCompany['email'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Telefone principal</label>
            <input name="phone" value="<?= h((string)($formCompany['phone'] ?? '')) ?>">
          </div>
        </div>
      </div>
      <div data-company-tab="endereco" style="display:none">
        <div class="row">
          <div class="col">
            <label>Logradouro</label>
            <input name="address_street" value="<?= h((string)($formCompany['address_street'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Número</label>
            <input name="address_number" value="<?= h((string)($formCompany['address_number'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Complemento</label>
            <input name="address_complement" value="<?= h((string)($formCompany['address_complement'] ?? '')) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Bairro</label>
            <input name="address_district" value="<?= h((string)($formCompany['address_district'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Cidade</label>
            <input name="address_city" value="<?= h((string)($formCompany['address_city'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>UF</label>
            <input name="address_state" value="<?= h((string)($formCompany['address_state'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>CEP</label>
            <input name="address_zip" value="<?= h((string)($formCompany['address_zip'] ?? '')) ?>">
          </div>
        </div>
      </div>
      <div data-company-tab="comercial" style="display:none">
        <div class="row">
          <div class="col">
            <label>Contato comercial</label>
            <input name="contact_name" value="<?= h((string)($formCompany['contact_name'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Email do contato</label>
            <input name="contact_email" type="email" value="<?= h((string)($formCompany['contact_email'] ?? '')) ?>">
          </div>
          <div class="col">
            <label>Telefone do contato</label>
            <input name="contact_phone" value="<?= h((string)($formCompany['contact_phone'] ?? '')) ?>">
          </div>
        </div>
      </div>
      <div data-company-tab="sla" style="display:none">
        <div class="row">
          <div class="col">
            <label>Nível Crítico - Tempo de resposta (min)</label>
            <input name="sla_response_critical" type="number" min="0" value="<?= h((string)($formCompany['sla_response_critical_minutes'] ?? 0)) ?>">
          </div>
          <div class="col">
            <label>Nível Crítico - Tempo de resolução (min)</label>
            <input name="sla_resolution_critical" type="number" min="0" value="<?= h((string)($formCompany['sla_resolution_critical_minutes'] ?? 0)) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Nível Alto - Tempo de resposta (min)</label>
            <input name="sla_response_high" type="number" min="0" value="<?= h((string)($formCompany['sla_response_high_minutes'] ?? 0)) ?>">
          </div>
          <div class="col">
            <label>Nível Alto - Tempo de resolução (min)</label>
            <input name="sla_resolution_high" type="number" min="0" value="<?= h((string)($formCompany['sla_resolution_high_minutes'] ?? 0)) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Nível Médio - Tempo de resposta (min)</label>
            <input name="sla_response_medium" type="number" min="0" value="<?= h((string)($formCompany['sla_response_medium_minutes'] ?? 0)) ?>">
          </div>
          <div class="col">
            <label>Nível Médio - Tempo de resolução (min)</label>
            <input name="sla_resolution_medium" type="number" min="0" value="<?= h((string)($formCompany['sla_resolution_medium_minutes'] ?? 0)) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Nível Baixo - Tempo de resposta (min)</label>
            <input name="sla_response_low" type="number" min="0" value="<?= h((string)($formCompany['sla_response_low_minutes'] ?? 0)) ?>">
          </div>
          <div class="col">
            <label>Nível Baixo - Tempo de resolução (min)</label>
            <input name="sla_resolution_low" type="number" min="0" value="<?= h((string)($formCompany['sla_resolution_low_minutes'] ?? 0)) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Objetivo de disponibilidade (SLO) %</label>
            <input name="sla_availability_target" type="text" inputmode="decimal" value="<?= h((string)($formCompany['sla_availability_target_percent'] ?? '')) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Observações / definição de SLI</label>
            <textarea name="sla_notes" rows="4"><?= h((string)($formCompany['sla_notes'] ?? '')) ?></textarea>
          </div>
        </div>
      </div>
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn primary" type="submit"><?= $editingCompany ? 'Salvar alterações' : 'Criar empresa' ?></button>
        <a class="btn" href="/app/tk_empresas.php">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>Documento</th>
        <th>Cidade/UF</th>
        <th>Telefone</th>
        <th>Email</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$companies): ?>
        <tr><td colspan="7" class="muted">Nenhuma empresa cadastrada.</td></tr>
      <?php endif; ?>
      <?php foreach ($companies as $c): ?>
        <?php
          $cityState = '';
          $city = (string)($c['address_city'] ?? '');
          $state = (string)($c['address_state'] ?? '');
          if ($city !== '' && $state !== '') {
              $cityState = $city . ' / ' . $state;
          } elseif ($city !== '') {
              $cityState = $city;
          } elseif ($state !== '') {
              $cityState = $state;
          }
        ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><?= h((string)$c['name']) ?></td>
          <td><?= h((string)($c['document'] ?? '')) ?></td>
          <td><?= h($cityState) ?></td>
          <td><?= h((string)($c['phone'] ?? '')) ?></td>
          <td><?= h((string)($c['email'] ?? '')) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="/app/tk_empresas.php?action=update&id=<?= (int)$c['id'] ?>">Editar</a>
            <form method="post" onsubmit="return confirm('Deseja realmente excluir esta empresa?');">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
  var buttons = document.querySelectorAll('[data-company-tab-button]');
  var panels = document.querySelectorAll('[data-company-tab]');
  if (!buttons.length || !panels.length) {
    return;
  }
  function activateTab(key) {
    panels.forEach(function (panel) {
      if (panel.getAttribute('data-company-tab') === key) {
        panel.style.display = '';
      } else {
        panel.style.display = 'none';
      }
    });
    buttons.forEach(function (btn) {
      var active = btn.getAttribute('data-company-tab-button') === key;
      if (active) {
        btn.classList.add('primary');
      } else {
        btn.classList.remove('primary');
      }
    });
  }
  buttons.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var key = btn.getAttribute('data-company-tab-button');
      if (key) {
        activateTab(key);
      }
    });
  });
  activateTab('geral');
});
</script>
<?php
render_footer();



