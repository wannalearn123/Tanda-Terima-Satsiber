<?php
// Variabel: $rows, $total, $page, $pages, $from, $to, $q
$qstr = fn(int $p): string => http_build_query(array_filter(
  ['from' => $from, 'to' => $to, 'q' => $q, 'page' => $p],
  fn($v) => $v !== '' && $v !== null
));
?>
<section class="card">
  <h1 class="title">REKAPITULASI RIWAYAT PENGIRIMAN</h1>

  <form method="get" action="/dashboard" class="filter">
    <div class="field"><label for="from">Dari</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to">Sampai</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field grow"><label for="q">Cari (No. Ref / Nama)</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Opsional"></div>
    <div class="field btnline"><button class="btn primary" type="submit">Cari</button></div>
  </form>

  <p class="muted">Total: <?= (int) $total ?> data · Halaman <?= (int) $page ?> / <?= (int) $pages ?></p>

  <div class="tablewrap">
    <table class="tbl">
      <thead><tr>
        <th>No</th><th>Tanggal</th><th>No. Referensi</th><th>Penerima</th><th>Pusat Penerima</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="center muted">Belum ada data.</td></tr>
      <?php else: $no = ($page - 1) * \App\Config\Config::perPage() + 1; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= e($r['tanggal']) ?> <?= e(substr((string) $r['pukul'], 0, 5)) ?></td>
          <td><?= e($r['nomor_referensi']) ?></td>
          <td><?= e($r['nama_penerima']) ?><?= $r['pangkat_golongan'] !== '' ? '<br><small>' . e($r['pangkat_golongan']) . '</small>' : '' ?></td>
          <td><?= e($r['pusat_nama'] ?? '-') ?></td>
          <td class="actions-cell">
            <a class="btn sm" href="/pengiriman/<?= (int) $r['id'] ?>">Lihat</a>
            <a class="btn sm" href="/pengiriman/<?= (int) $r['id'] ?>/edit">Edit</a>
            <a class="btn sm" href="/pengiriman/<?= (int) $r['id'] ?>/pdf" target="_blank" rel="noopener">PDF</a>
            <?php if (!empty($appAdmin)): ?>
              <form class="inline" method="post" action="/pengiriman/<?= (int) $r['id'] ?>/delete"
                    onsubmit="return confirm('Hapus data #<?= (int) $r['id'] ?>? File tanda tangan ikut terhapus.')">
                <?= csrf_field() ?>
                <button class="btn sm danger" type="submit">Hapus</button>
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
  <article class="mcard">
    <div class="mrow"><span>No</span><b><?= e($r['id']) ?></b></div>
    <div class="mrow"><span>Tanggal</span><b><?= e($r['tanggal']) ?> <?= e(substr((string) $r['pukul'], 0, 5)) ?></b></div>
    <div class="mrow"><span>No. Ref</span><b><?= e($r['nomor_referensi']) ?></b></div>
    <div class="mrow"><span>Penerima</span><b><?= e($r['nama_penerima']) ?></b></div>
    <div class="mrow"><span>Pusat</span><b><?= e($r['pusat_nama'] ?? '-') ?></b></div>
    <div class="mact">
      <a class="btn sm" href="/pengiriman/<?= (int) $r['id'] ?>">Lihat</a>
      <a class="btn sm" href="/pengiriman/<?= (int) $r['id'] ?>/edit">Edit</a>
      <a class="btn sm" href="/pengiriman/<?= (int) $r['id'] ?>/pdf" target="_blank" rel="noopener">PDF</a>
      <?php if (!empty($appAdmin)): ?>
        <form class="inline grow" method="post" action="/pengiriman/<?= (int) $r['id'] ?>/delete"
              onsubmit="return confirm('Hapus data #<?= (int) $r['id'] ?>?')">
          <?= csrf_field() ?>
          <button class="btn sm danger" style="width:100%" type="submit">Hapus</button>
        </form>
      <?php endif; ?>
    </div>
  </article>
  <?php endforeach; ?>

  <?php if ($pages > 1): ?>
  <nav class="pager">
    <?php if ($page > 1): ?><a class="btn sm" href="/dashboard?<?= e($qstr($page - 1)) ?>">‹ Prev</a><?php endif; ?>
    <span class="muted">Hal <?= (int) $page ?> / <?= (int) $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn sm" href="/dashboard?<?= e($qstr($page + 1)) ?>">Next ›</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
