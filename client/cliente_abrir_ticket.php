<?php

require __DIR__ . '/../includes/bootstrap.php';

$user = require_login('cliente');
$categories = ticket_categories($pdo);
$selectedCategoryId = safe_int($_GET['category_id'] ?? null);
if ($selectedCategoryId === null && isset($_POST['category_id'])) {
    $selectedCategoryId = safe_int($_POST['category_id']);
}

$category = $selectedCategoryId ? ticket_category($pdo, $selectedCategoryId) : null;
$schema = [];
if ($category && isset($category['schema_json'])) {
    $decoded = json_decode((string)$category['schema_json'], true);
    if (is_array($decoded)) {
        $schema = $decoded;
    }
}

$error = '';
$subject = (string)($_POST['subject'] ?? ($_GET['subject'] ?? ''));
$description = (string)($_POST['description'] ?? ($_GET['description'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_validate();
    $categoryId = safe_int($_POST['category_id'] ?? null);
    $subject = trim($subject);
    $description = trim($description);
    $priority = (string)($_POST['priority'] ?? 'medium');

    if (!$categoryId) {
        $error = 'Selecione uma categoria.';
    } else {
        $category = ticket_category($pdo, $categoryId);
        if (!$category) {
            $error = 'Categoria inválida.';
        } else {
            $decoded = json_decode((string)$category['schema_json'], true);
            $schema = is_array($decoded) ? $decoded : [];

            if ($subject === '' || $description === '') {
                $error = 'Assunto e descrição são obrigatórios.';
            } else {
                $extra = [];
                $inputExtra = $_POST['extra'] ?? [];
                if (!is_array($inputExtra)) {
                    $inputExtra = [];
                }

                foreach ($schema as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $name = (string)($field['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $value = trim((string)($inputExtra[$name] ?? ''));
                    $required = (bool)($field['required'] ?? false);
                    if ($required && $value === '') {
                        $error = 'Preencha: ' . (string)($field['label'] ?? $name);
                        break;
                    }
                    if ($value !== '') {
                        $extra[$name] = $value;
                    }
                }

                if ($error === '') {
                    $ticketId = ticket_create($pdo, (int)$user['id'], $categoryId, $subject, $description, $extra, null, $priority);
                    redirect('/client/cliente_chamado.php');
                }
            }
        }
    }
}

render_header('Cliente · Abrir Chamado', current_user());
?>
<div class="card">
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="row">
      <div class="col">
        <label>Categoria</label>
        <select
          name="category_id"
          onchange="
            (function(sel){
              var s = document.querySelector('input[name=subject]');
              var d = document.querySelector('textarea[name=description]');
              var url = '?category_id=' + encodeURIComponent(sel.value);
              if (s && s.value !== '') {
                url += '&subject=' + encodeURIComponent(s.value);
              }
              if (d && d.value !== '') {
                url += '&description=' + encodeURIComponent(d.value);
              }
              var extraInputs = document.querySelectorAll('[name^=extra\\[]');
              extraInputs.forEach(function(inp){
                if (!inp.name) return;
                var m = inp.name.match(/^extra\[(.+)\]$/);
                if (!m) return;
                var key = m[1];
                if (inp.value !== '') {
                  url += '&extra[' + encodeURIComponent(key) + ']=' + encodeURIComponent(inp.value);
                }
              });
              window.location = url;
            })(this);
          "
          required
        >
          <option value="">Selecione...</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($selectedCategoryId === (int)$c['id']) ? 'selected' : '' ?>>
              <?= h((string)$c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label>Prioridade</label>
        <select name="priority">
          <option value="low">Baixa</option>
          <option value="medium" selected>Média</option>
          <option value="high">Alta</option>
          <option value="critical">Crítica</option>
        </select>
      </div>
    </div>
    
    <div class="row">
      <div class="col">
        <label>Assunto</label>
        <input name="subject" value="<?= h($subject) ?>" required>
      </div>
    </div>
    <label>Descrição</label>
    <textarea name="description" required><?= h($description) ?></textarea>

    <?php if ($schema): ?>
      <div style="margin-top:12px;font-weight:700">Campos da categoria</div>
      <div class="row">
        <?php foreach ($schema as $field): ?>
          <?php
            if (!is_array($field)) { continue; }
            $fname = (string)($field['name'] ?? '');
            $flabel = (string)($field['label'] ?? $fname);
            $ftype = (string)($field['type'] ?? 'text');
            $required = (bool)($field['required'] ?? false);
            $val = '';
            if (isset($_POST['extra']) && is_array($_POST['extra']) && isset($_POST['extra'][$fname])) {
                $val = (string)$_POST['extra'][$fname];
            } elseif (isset($_GET['extra']) && is_array($_GET['extra']) && isset($_GET['extra'][$fname])) {
                $val = (string)$_GET['extra'][$fname];
            }
          ?>
          <div class="col">
            <label><?= h($flabel) ?><?= $required ? ' *' : '' ?></label>
            <?php if ($ftype === 'textarea'): ?>
              <textarea name="extra[<?= h($fname) ?>]" <?= $required ? 'required' : '' ?>><?= h($val) ?></textarea>
            <?php elseif ($ftype === 'select'): ?>
              <?php $opts = (isset($field['options']) && is_array($field['options'])) ? $field['options'] : []; ?>
              <select name="extra[<?= h($fname) ?>]" <?= $required ? 'required' : '' ?>>
                <option value="">Selecione...</option>
                <?php foreach ($opts as $opt): ?>
                  <?php $optStr = (string)$opt; ?>
                  <option value="<?= h($optStr) ?>" <?= ($val === $optStr) ? 'selected' : '' ?>><?= h($optStr) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input name="extra[<?= h($fname) ?>]" value="<?= h($val) ?>" <?= $required ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top:14px">
      <button class="btn primary" type="submit">Abrir chamado</button>
    </div>
  </form>
</div>
<?php
render_footer();



