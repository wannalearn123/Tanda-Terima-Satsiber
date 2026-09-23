<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tanda Terima Pengiriman — TNI Satuan Siber</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <div class="brand">
      <div class="brand-sub">TENTARA NASIONAL INDONESIA</div>
      <div class="brand-main">SATUAN SIBER</div>
    </div>
    <?php if (!empty($appUser)): ?>
    <form class="inline" method="post" action="/logout" title="<?= e($appUser['username'] ?? '') ?>">
      <?= csrf_field() ?>
      <button class="btn sm danger" type="submit">Keluar</button>
    </form>
    <?php endif; ?>
  </div>
</header>

<nav class="nav sub-nav">
  <div class="nav-scroll">
    <?php if (!empty($appUser)): ?>
    <a href="/" class="<?= (($active ?? '') === 'form') ? 'on' : '' ?>">Form</a>
    <a href="/dashboard" class="<?= (($active ?? '') === 'dashboard') ? 'on' : '' ?>">Rekapitulasi</a>
    <a href="/agenda" class="<?= (($active ?? '') === 'agenda') ? 'on' : '' ?>">Agenda</a>
    <?php if (!empty($appAdmin)): ?>
    <a href="/users" class="<?= (($active ?? '') === 'users') ? 'on' : '' ?>">Pengguna</a>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</nav>

<main class="wrap<?= ($active ?? '') === 'login' ? ' login-page' : '' ?><?= ($active ?? '') === 'agenda' ? ' agenda-flex' : '' ?>">
  <?php if ($err = flash('error')): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
  <?php if ($ok = flash('success')): ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
  <?= $content ?? '' ?>
</main>
<footer class="wrap foot"><small>TNI Satuan Siber</small></footer>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
