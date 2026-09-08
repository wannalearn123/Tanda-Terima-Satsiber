<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Tanda Terima') ?> — TNI Satuan Siber</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <div class="brand">
      <div class="brand-sub">TENTARA NASIONAL INDONESIA</div>
      <div class="brand-main">SATUAN SIBER</div>
    </div>
    <nav class="nav">
      <?php if (!empty($appUser)): ?>
        <a href="/" class="<?= (($active ?? '') === 'form') ? 'on' : '' ?>">Form</a>
        <a href="/dashboard" class="<?= (($active ?? '') === 'dashboard') ? 'on' : '' ?>">Rekapitulasi</a>
        <?php if (!empty($appAdmin)): ?>
          <a href="/users" class="<?= (($active ?? '') === 'users') ? 'on' : '' ?>">Pengguna</a>
        <?php endif; ?>
        <form class="inline" method="post" action="/logout" title="<?= e($appUser['username'] ?? '') ?>">
          <?= csrf_field() ?>
          <button class="navlink" type="submit">Keluar (<?= e($appUser['username'] ?? '') ?>)</button>
        </form>
      <?php else: ?>
        <a href="/login" class="<?= (($active ?? '') === 'login') ? 'on' : '' ?>">Masuk</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
  <?php if ($err = flash('error')): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
  <?php if ($ok = flash('success')): ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
  <?= $content ?? '' ?>
</main>
<footer class="wrap foot"><small>TNI Satuan Siber — Tanda Terima Pengiriman · single server · SQLite</small></footer>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
