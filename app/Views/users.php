<?php
// Variabel: $users (list), $me (id user login), $errors (array), $old (array)
?>
<section class="card">
  <h1 class="title">PENGGUNA</h1>

  <h2 class="sub">Tambah Pengguna</h2>
  <?php if (!empty($errors)): ?>
    <div class="alert err"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>
  <form method="post" action="/users" class="form">
    <?= csrf_field() ?>
    <div class="grid2">
      <div class="field"><label for="u-username">Username</label>
        <input id="u-username" name="username" type="text" maxlength="30" placeholder="huruf kecil, angka, titik, _"
               value="<?= e($old['username'] ?? '') ?>" required></div>
      <div class="field"><label for="u-nama">Nama</label>
        <input id="u-nama" name="nama" type="text" maxlength="100" value="<?= e($old['nama'] ?? '') ?>" required></div>
      <div class="field"><label for="u-pass">Password (min 8)</label>
        <input id="u-pass" name="password" type="password" maxlength="100" required></div>
      <div class="field"><label for="u-role">Role</label>
        <select id="u-role" name="role">
          <option value="operator" <?= (($old['role'] ?? '') === 'operator') ? 'selected' : '' ?>>operator</option>
          <option value="admin" <?= (($old['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option>
        </select></div>
    </div>
    <div class="actions"><button class="btn primary" type="submit">Tambah</button></div>
  </form>

  <h2 class="sub">Daftar Pengguna</h2>
  <div class="tablewrap">
    <table class="tbl">
      <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['nama']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td><?= ((int) $u['aktif'] === 1) ? 'aktif' : 'nonaktif' ?></td>
          <td class="actions-cell">
            <?php if ((int) $u['id'] !== (int) $me): ?>
              <form class="inline" method="post" action="/users/<?= (int) $u['id'] ?>/toggle">
                <?= csrf_field() ?>
                <button class="btn sm" type="submit"><?= ((int) $u['aktif'] === 1) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
              <form class="inline" method="post" action="/users/<?= (int) $u['id'] ?>/delete"
                    onsubmit="return confirm('Hapus pengguna <?= e($u['username']) ?>?')">
                <?= csrf_field() ?>
                <button class="btn sm danger" type="submit">Hapus</button>
              </form>
              <form class="inline" method="post" action="/users/<?= (int) $u['id'] ?>/password">
                <?= csrf_field() ?>
                <input class="pwmini" name="password" type="password" minlength="8" maxlength="100" placeholder="Password baru" required>
                <button class="btn sm" type="submit">Reset PW</button>
              </form>
            <?php else: ?>
              <small class="muted">akun sendiri</small>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
