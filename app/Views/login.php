<?php // Variabel: $error (string|null), $oldUser (string) ?>
<section class="card narrow">
  <h1 class="title">MASUK APLIKASI</h1>
  <p class="muted center">TNI Satuan Siber — Tanda Terima Pengiriman</p>
  <?php if (!empty($error)): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/login" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" maxlength="30" autocomplete="username"
             value="<?= e($oldUser ?? '') ?>" required autofocus>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" maxlength="100" autocomplete="current-password" required>
    </div>
    <div class="actions">
      <button type="submit" class="btn primary" style="width:100%">Masuk</button>
    </div>
  </form>
</section>
