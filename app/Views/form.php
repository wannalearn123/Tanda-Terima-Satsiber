<?php
// Variabel: $preset (list), $old, $errors, $isEdit, $editId
$isEdit = $isEdit ?? false;
$old = $old ?? [];
$errors = $errors ?? [];
$val = fn(string $k, string $d = ''): string => (string) ($old[$k] ?? $d);
$ferr = fn(string $k): string => isset($errors[$k]) ? '<div class="ferr">' . e($errors[$k]) . '</div>' : '';
if (isset($errors['_csrf'])): ?><div class="alert err"><?= e($errors['_csrf']) ?></div><?php endif; ?>

<section class="card">
  <h1 class="title">TANDA TERIMA PENGIRIMAN</h1>

  <form method="post" action="<?= $isEdit ? '/pengiriman/' . (int) $editId . '/update' : '/pengiriman' ?>" class="form" novalidate>
    <?= csrf_field() ?>

    <div class="grid2">
      <div class="field">
        <label for="preset">Penerima *</label>
        <input id="preset" name="preset_penerima_id" type="text" maxlength="100" placeholder="Nama Satuan Kerja"
               value="<?= e($val('preset_penerima_id')) ?>" required>
        <?= $ferr('preset_penerima_id') ?>
      </div>

      <div class="field">
        <label for="noref">Nomor Referensi *</label>
        <input id="noref" name="nomor_referensi" type="text" maxlength="100"
               placeholder="Contoh: B.18/..." value="<?= e($val('nomor_referensi')) ?>" required>
        <?= $ferr('nomor_referensi') ?>
      </div>
    </div>

    <fieldset class="group"><legend>PENERIMA</legend>
      <div class="grid2">
        <div class="field">
          <label for="nama">Nama Penerima *</label>
          <input id="nama" name="nama_penerima" type="text" maxlength="100" value="<?= e($val('nama_penerima')) ?>" required>
          <?= $ferr('nama_penerima') ?>
        </div>
        <div class="field">
          <label for="pangkat">Pangkat / Golongan *</label>
          <input id="pangkat" name="pangkat_golongan" type="text" maxlength="100" value="<?= e($val('pangkat_golongan')) ?>" required>
          <?= $ferr('pangkat_golongan') ?>
        </div>
        <div class="field">
          <label for="jabatan">Jabatan *</label>
          <input id="jabatan" name="jabatan" type="text" maxlength="100" value="<?= e($val('jabatan')) ?>" required>
          <?= $ferr('jabatan') ?>
        </div>
        <div class="field">
          <label for="telp">Telp / HP *</label>
          <input id="telp" name="telp_hp" type="tel" maxlength="30" value="<?= e($val('telp_hp')) ?>" required>
          <?= $ferr('telp_hp') ?>
        </div>
        <div class="field">
          <label for="tanggal">Tanggal *</label>
          <input id="tanggal" name="tanggal" type="date" value="<?= e($val('tanggal')) ?>" required>
          <?= $ferr('tanggal') ?>
        </div>
        <div class="field">
          <label for="pukul">Pukul *</label>
          <input id="pukul" name="pukul" type="time" value="<?= e($val('pukul')) ?>" required>
          <?= $ferr('pukul') ?>
        </div>
      </div>
      <div class="field">
        <label for="sigpad">Tanda Tangan Penerima *</label>
        <?php if (!empty($existingTtd)): ?>
          <div class="sigexist">
            <img src="/<?= e($existingTtd) ?>" alt="Tanda tangan tersimpan">
            <small>Tersimpan. Gambar ulang di kanvas bawah untuk mengganti (wajib ada).</small>
          </div>
        <?php else: ?>
          <div class="sigexist"><small>Coret di kanvas bawah (wajib).</small></div>
        <?php endif; ?>
        <canvas id="sigpad" width="900" height="330"></canvas>
        <input type="hidden" name="tanda_tangan" id="tandatangan" value="">
        <div class="sigrow">
          <button type="button" class="btn sm ghost" id="sigclear">Hapus &amp; Ulangi</button>
        </div>
        <?= $ferr('tanda_tangan') ?>
      </div>
    </fieldset>

    <div class="actions">
      <button type="submit" class="btn primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan & Cetak' ?></button>
      <?php if (!$isEdit): ?>
        <button type="reset" class="btn ghost" id="btnReset">Reset</button>
      <?php else: ?>
        <button type="button" class="btn ghost" onclick="window.location.href='/pengiriman/<?= (int) $editId ?>'">Batal</button>
      <?php endif; ?>
    </div>
  </form>
</section>
