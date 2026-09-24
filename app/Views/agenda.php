<?php
// Variabel: $arah, $subList, $subMasuk, $subKeluar, $formSubList, $sub, $q, $rows, $total, $page, $pages, $counts, $form, $errors, $isEdit, $editId, $editNoAgenda, $editNoFmt, $riwayat, $dispErrors, $dispOld, $summaries, $kodeMap, $fmtNo
$val = fn(string $k, string $d = ''): string => (string) ($form[$k] ?? $d);
$ferr = fn(string $k): string => isset($errors[$k]) ? '<div class="ferr">' . e($errors[$k]) . '</div>' : '';
$fmtNoFn = $fmtNo ?? (fn(array $r): string => (string) ((int) ($r['no_agenda'] ?? 0)));
$editNoFmt = $editNoFmt ?? ($editNoAgenda !== null ? (string) $editNoAgenda : null);
$formArah = strtolower((string) ($form['arah'] ?? $arah));
if (!in_array($formArah, ['masuk', 'keluar'], true)) { $formArah = $arah; }
$formSubList = $formSubList ?? $subList ?? [];
$subMasuk = $subMasuk ?? [];
$subKeluar = $subKeluar ?? [];
$qstr = fn(int $p): string => http_build_query(array_filter(
    ['arah' => $arah, 'sub' => $sub, 'q' => $q, 'page' => $p],
    fn($v) => $v !== '' && $v !== null
));
if (isset($errors['_csrf'])): ?><div class="alert err"><?= e($errors['_csrf']) ?></div><?php endif; ?>
<?php if (isset($errors['form'])): ?><div class="alert err"><?= e($errors['form']) ?></div><?php endif; ?>

<section class="card">
  <h1 class="title">AGENDA SURAT</h1>
  <div class="agenda-grid">
    <div class="agenda-form">
      <h2 class="sub"><?= $isEdit ? 'Ubah Surat ' . e((string) $editNoFmt) : 'Input Surat Baru' ?></h2>
      <form method="post" action="<?= $isEdit ? '/agenda/' . (int) $editId . '/update' : '/agenda' ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
        <div class="field">
          <label>Arah Surat</label>
          <input type="text" value="<?= $formArah === 'keluar' ? 'Surat Keluar' : 'Surat Masuk' ?>" disabled>
          <input type="hidden" name="arah" value="<?= e($formArah) ?>">
        </div>
        <div class="field">
          <label>Jenis Surat</label>
          <input type="text" value="<?= e((string) ($form['sub_jenis'] ?? '')) ?>" disabled>
          <input type="hidden" name="sub_jenis" value="<?= e((string) ($form['sub_jenis'] ?? '')) ?>">
        </div>
        <?php else: ?>
        <div class="field">
          <label for="arah">Arah Surat *</label>
          <select id="arah" name="arah" required>
            <option value="masuk" <?= $formArah === 'masuk' ? 'selected' : '' ?>>Surat Masuk</option>
            <option value="keluar" <?= $formArah === 'keluar' ? 'selected' : '' ?>>Surat Keluar</option>
          </select>
          <?= $ferr('arah') ?>
        </div>
        <div class="field">
          <label for="sub_jenis">Jenis Surat *</label>
          <select id="sub_jenis" name="sub_jenis" required>
            <?php $selSub = (string) ($form['sub_jenis'] ?? ''); ?>
            <?php if ($selSub !== '' && !in_array($selSub, $formSubList, true)): ?>
              <option value="<?= e($selSub) ?>" selected><?= e($selSub) ?> (arsip)</option>
            <?php endif; ?>
            <?php foreach ($formSubList as $s): ?>
              <option value="<?= e($s) ?>" <?= $selSub === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $ferr('sub_jenis') ?>
        </div>
        <?php endif; ?>
        <?= $ferr('sub_jenis') ?>
        <div class="field">
          <label for="tanggal">Tanggal *</label>
          <input id="tanggal" name="tanggal" type="date" value="<?= e($val('tanggal')) ?>" required>
          <?= $ferr('tanggal') ?>
        </div>
        <div class="field">
          <label for="no_surat">No. Surat *</label>
          <input id="no_surat" name="no_surat" type="text" maxlength="100"
                 placeholder="Contoh: B/334/VIII/2026" value="<?= e($val('no_surat')) ?>" required>
          <?= $ferr('no_surat') ?>
        </div>
        <div class="field">
          <label for="kepada">Kepada *</label>
          <input id="kepada" name="kepada" type="text" maxlength="150"
                 placeholder="Instansi / Perorangan" value="<?= e($val('kepada')) ?>" required>
          <?= $ferr('kepada') ?>
        </div>
        <div class="field">
          <label for="perihal">Perihal / Ringkasan Surat *</label>
          <textarea id="perihal" name="perihal" rows="3" maxlength="500" placeholder="Isi ringkas surat..." required><?= e($val('perihal')) ?></textarea>
          <?= $ferr('perihal') ?>
        </div>
        <?php if ($isEdit): ?>
        <div class="actions">
          <button type="submit" class="btn primary">Simpan Perubahan</button>
          <button type="button" class="btn ghost" onclick="window.location.href='/agenda?arah=<?= e($arah) ?>'">Batal</button>
        </div>
      </form>
      <fieldset class="group"><legend>RIWAYAT DISPOSISI</legend>
        <?php $riwayat = $riwayat ?? []; ?>
        <?php if ($riwayat === []): ?>
          <p class="muted">Belum ada disposisi.</p>
        <?php else: ?>
          <?php foreach ($riwayat as $i => $w): ?>
          <div class="mrow">
            <span>#<?= $i + 1 ?> · <?= e(substr((string) $w['created_at'], 0, 10)) ?></span>
            <b class="leader"><span class="ll"><?= e($w['aktor']) ?></span><span class="fill" aria-hidden="true"></span><span class="rl"><?= e($w['kegiatan']) ?></span></b>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </fieldset>
      <form method="post" action="/agenda/<?= (int) $editId ?>/disposisi" class="form" novalidate>
        <fieldset class="group" id="tambah-disposisi"><legend>TAMBAH DISPOSISI</legend>
          <?php $dval = fn(string $k): string => (string) (($dispOld[$k] ?? '') ); ?>
          <?php $derr = fn(string $k): string => isset($dispErrors[$k]) ? '<div class="ferr">' . e($dispErrors[$k]) . '</div>' : ''; ?>
          <?= csrf_field() ?>
          <div class="field">
            <label for="d_aktor">Aktor *</label>
            <input id="d_aktor" name="disposisi_aktor" type="text" maxlength="100"
                   placeholder="Contoh: Perwira 2" value="<?= e($dval('disposisi_aktor')) ?>" required>
            <?= $derr('disposisi_aktor') ?>
          </div>
          <div class="field">
            <label for="d_kegiatan">Kegiatan *</label>
            <input id="d_kegiatan" name="disposisi_kegiatan" type="text" maxlength="200"
                   placeholder="Contoh: Untuk Ditelaah" value="<?= e($dval('disposisi_kegiatan')) ?>" required>
            <?= $derr('disposisi_kegiatan') ?>
          </div>
          <div class="actions">
            <button type="submit" class="btn primary">Tambah Disposisi</button>
          </div>
        </fieldset>
      </form>
        <?php else: ?>
        <p class="muted">Disposisi ditambahkan setelah surat tersimpan, lewat tombol + di tabel.</p>
        <div class="actions">
          <button type="submit" class="btn primary">Simpan Surat</button>
          <button type="reset" class="btn ghost">Reset</button>
        </div>
      </form>
        <?php endif; ?>
    </div>

    <div class="agenda-list">
      <div class="tabs">
        <button class="btn sm <?= $arah === 'masuk' ? 'primary' : '' ?>" onclick="window.location.href='/agenda?arah=masuk'">Surat Masuk (<?= (int) ($counts['masuk'] ?? 0) ?>)</button>
        <button class="btn sm <?= $arah === 'keluar' ? 'primary' : '' ?>" onclick="window.location.href='/agenda?arah=keluar'">Surat Keluar (<?= (int) ($counts['keluar'] ?? 0) ?>)</button>
      </div>
      <form method="get" action="/agenda" class="filter agenda-filter">
        <input type="hidden" name="arah" value="<?= e($arah) ?>">
        <div class="field grow"><label for="aq">Cari (No. Agenda / No. Surat / Kepada / Perihal)</label>
          <input id="aq" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari SMB-1 / No. Surat / Kepada / Perihal..."></div>
        <div class="field"><label for="asub">Sub-Jenis</label>
          <select id="asub" name="sub">
            <option value="">Semua Sub-Jenis</option>
            <?php foreach ($subList as $s): ?>
              <option value="<?= e($s) ?>" <?= $sub === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field btnline"><button class="btn primary" type="submit">Cari</button></div>
      </form>
      <p class="muted">Total: <?= (int) $total ?> data · Halaman <?= (int) $page ?> / <?= (int) $pages ?></p>
      <div class="tablewrap">
        <table class="tbl">
          <thead><tr>
            <th>No.</th><th>Jenis</th><th>No. Surat</th><th>Kepada</th><th>Perihal</th>
            <th>Aktor</th><th>Kegiatan</th><th>Aksi</th>
          </tr></thead>
          <tbody>
          <?php if ($rows === []): ?>
            <tr><td colspan="8" class="center muted">Data surat <?= e($arah) ?> tidak ditemukan.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <?php $sum = $summaries[(int) $r['id']] ?? null; $last = $sum['latest'] ?? null; $cnt = (int) ($sum['count'] ?? 0); ?>
            <tr>
              <td><?= e($fmtNoFn($r)) ?></td>
              <td><?= e($r['sub_jenis']) ?></td>
              <td><?= e($r['no_surat']) ?></td>
              <td><?= e($r['kepada']) ?></td>
              <td class="perihal" title="<?= e($r['perihal']) ?>"><?= e($r['perihal']) ?></td>
              <td><?= $last !== null ? e($last['aktor']) : '<span class="muted">—</span>' ?></td>
              <td><?= $last !== null ? e($last['kegiatan']) : '<span class="muted">—</span>' ?><?php if ($cnt > 1): ?><br><small class="muted"><?= (int) $cnt ?> riwayat</small><?php endif; ?></td>
              <td class="actions-cell">
                <a class="btn sm icon" href="/agenda?arah=<?= e($arah) ?>&edit=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                </a>
                <?php if (!empty($appAdmin)): ?>
                  <form class="inline" method="post" action="/agenda/<?= (int) $r['id'] ?>/delete"
                        onsubmit="return confirm('Hapus agenda <?= e($fmtNoFn($r)) ?> (<?= e($arah) ?>)?')">
                    <?= csrf_field() ?>
                    <button class="btn sm icon danger" type="submit" title="Hapus" aria-label="Hapus">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php foreach ($rows as $r): ?>
      <?php $msum = $summaries[(int) $r['id']] ?? null; $mlast = $msum['latest'] ?? null; $mcnt = (int) ($msum['count'] ?? 0); ?>
      <article class="mcard">
        <div class="mrow"><span>No.</span><b><?= e($fmtNoFn($r)) ?></b></div>
        <div class="mrow"><span>Jenis</span><b><?= e($r['sub_jenis']) ?></b></div>
        <div class="mrow"><span>No. Surat</span><b><?= e($r['no_surat']) ?></b></div>
        <div class="mrow"><span>Kepada</span><b><?= e($r['kepada']) ?></b></div>
        <div class="mrow"><span>Perihal</span><b><?= e($r['perihal']) ?></b></div>
        <div class="mrow"><span>Disposisi</span><b<?= $mlast !== null ? ' class="leader"' : '' ?>><?= $mlast !== null ? '<span class="ll">' . e($mlast['aktor']) . '</span><span class="fill" aria-hidden="true"></span><span class="rl">' . e($mlast['kegiatan']) . ($mcnt > 1 ? ' · ' . $mcnt . ' riwayat' : '') . '</span>' : '—' ?></b></div>
        <div class="mact">
          <button class="btn sm" onclick="window.location.href='/agenda?arah=<?= e($arah) ?>&edit=<?= (int) $r['id'] ?>'">Edit</button>
          <?php if (!empty($appAdmin)): ?>
            <form class="inline grow" method="post" action="/agenda/<?= (int) $r['id'] ?>/delete"
                  onsubmit="return confirm('Hapus agenda <?= e($fmtNoFn($r)) ?>?')">
              <?= csrf_field() ?>
              <button class="btn sm danger" type="submit">Hapus</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
      <?php if ($pages > 1): ?>
      <nav class="pager">
        <?php if ($page > 1): ?><button class="btn sm" onclick="window.location.href='/agenda?<?= e($qstr($page - 1)) ?>'">‹ Prev</button><?php endif; ?>
        <span class="muted">Hal <?= (int) $page ?> / <?= (int) $pages ?></span>
        <?php if ($page < $pages): ?><button class="btn sm" onclick="window.location.href='/agenda?<?= e($qstr($page + 1)) ?>'">Next ›</button><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </div>
</section>
<script>
(function () {
  var masuk = <?= json_encode($subMasuk, JSON_UNESCAPED_UNICODE) ?>;
  var keluar = <?= json_encode($subKeluar, JSON_UNESCAPED_UNICODE) ?>;
  var arah = document.getElementById('arah');
  var sub = document.getElementById('sub_jenis');
  if (!arah || !sub) return;
  arah.addEventListener('change', function () {
    var list = arah.value === 'keluar' ? keluar : masuk;
    var cur = sub.value;
    sub.innerHTML = '';
    list.forEach(function (s) {
      var o = document.createElement('option');
      o.value = s; o.textContent = s;
      if (s === cur) o.selected = true;
      sub.appendChild(o);
    });
  });
})();
</script>
