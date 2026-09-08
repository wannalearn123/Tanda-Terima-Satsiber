<?php // Variabel: $row ?>
<section class="card">
  <h1 class="title">DETAIL PENGIRIMAN #<?= (int) $row['id'] ?></h1>
  <dl class="detail">
    <div><dt>Nomor Referensi</dt><dd><?= e($row['nomor_referensi']) ?></dd></div>
    <div><dt>Pusat Penerima</dt><dd><?= e($row['preset_nama'] ?? '-') ?></dd></div>
    <div><dt>Nama Penerima</dt><dd><?= e($row['nama_penerima']) ?></dd></div>
    <div><dt>Pangkat / Golongan</dt><dd><?= e($row['pangkat_golongan'] !== '' ? $row['pangkat_golongan'] : '-') ?></dd></div>
    <div><dt>Jabatan</dt><dd><?= e($row['jabatan'] !== '' ? $row['jabatan'] : '-') ?></dd></div>
    <div><dt>Tanggal</dt><dd><?= e($row['tanggal']) ?></dd></div>
    <div><dt>Pukul</dt><dd><?= e(substr((string) $row['pukul'], 0, 5)) ?></dd></div>
    <div><dt>Telp / HP</dt><dd><?= e($row['telp_hp'] !== '' ? $row['telp_hp'] : '-') ?></dd></div>
    <div><dt>Tanda Tangan</dt><dd><?php if (!empty($row['tanda_tangan_path'])): ?><img class="sigview" src="/<?= e($row['tanda_tangan_path']) ?>" alt="Tanda tangan penerima"><?php else: ?>-<?php endif; ?></dd></div>
    <div><dt>Terakhir diubah</dt><dd><?= e($row['updated_at']) ?></dd></div>
  </dl>
  <div class="actions">
    <a class="btn primary" href="/pengiriman/<?= (int) $row['id'] ?>/pdf" target="_blank" rel="noopener">Cetak PDF</a>
    <a class="btn" href="/pengiriman/<?= (int) $row['id'] ?>/edit">Edit</a>
    <?php if (!empty($appAdmin)): ?>
      <form class="inline" method="post" action="/pengiriman/<?= (int) $row['id'] ?>/delete"
            onsubmit="return confirm('Hapus data ini? File tanda tangan ikut terhapus.')">
        <?= csrf_field() ?>
        <button class="btn danger" type="submit">Hapus</button>
      </form>
    <?php endif; ?>
    <a class="btn ghost" href="/dashboard">Kembali</a>
  </div>
</section>
