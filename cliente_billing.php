<?php

require __DIR__ . '/includes/bootstrap.php';

$user = require_login('cliente');

render_header('Cliente · Billing', current_user());
?>
<div class="card">
  <div class="muted">Painel de billing (aqui entram planos, faturas e histórico).</div>
</div>
<?php
render_footer();

