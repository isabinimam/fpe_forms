<?php
/**
 * FORM BUKTI DOKUMENTASI FPE
 * =====================================================================
 * RSKD Duren Sawit
 *
 * File ini didesain untuk di-include ke aplikasi yang sudah berjalan.
 *
 * VARIABEL YANG HARUS SUDAH ADA sebelum file ini di-include:
 *   $conn          -> resource sqlsrv_connect, koneksi SQL Server aktif
 *   $id_pasien     -> int, ID pasien yang sedang dibuka
 *   $nama_petugas  -> string, nama PPA yang login (opsional)
 *
 * Contoh pemanggilan dari halaman induk:
 *   $id_pasien    = 12;
 *   $nama_petugas = $_SESSION['nama'] ?? 'Petugas';
 *   include 'form_dokumentasi_fpe.php';
 *
 * Asumsi tampilan: Bootstrap 5 sudah dimuat oleh halaman induk.
 * =====================================================================
 */

if (!isset($conn) || !is_resource($conn)) {
    die('Koneksi database ($conn) sqlsrv belum tersedia. Sertakan koneksi sqlsrv sebelum meng-include file ini.');
}
if (!isset($id_pasien)) {
    die('Variabel $id_pasien belum diset.');
}
$nama_petugas = $nama_petugas ?? 'Petugas';

$pesan = '';
$error = '';

$hubungan_options = [
    'ayah'      => 'Ayah',
    'ibu'       => 'Ibu',
    'suami'     => 'Suami',
    'istri'     => 'Istri',
    'anak'      => 'Anak',
    'kakak'     => 'Kakak',
    'adik'      => 'Adik',
    'kakek'     => 'Kakek',
    'nenek'     => 'Nenek',
    'lain_lain' => 'Lain-lain',
];

// ---------- PROSES SIMPAN DATA ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_dokumentasi_fpe'])) {
    try {
        $id_jadwal          = (isset($_POST['id_jadwal']) && $_POST['id_jadwal'] !== '') ? (int)$_POST['id_jadwal'] : null;
        $asesmen             = trim($_POST['asesmen'] ?? '');
        $hubungan            = trim($_POST['hubungan_dengan_pasien'] ?? '');
        $hubungan_lainnya    = trim($_POST['hubungan_lainnya'] ?? '');
        $hasil_fpe           = trim($_POST['hasil_fpe'] ?? '');
        $kemampuan_pasien    = trim($_POST['kemampuan_pasien'] ?? '');
        $kemampuan_keluarga  = trim($_POST['kemampuan_keluarga'] ?? '');

        if ($asesmen === '' || $hubungan === '') {
            throw new Exception('Asesmen dan hubungan dengan pasien wajib diisi.');
        }
        if (!array_key_exists($hubungan, $hubungan_options)) {
            throw new Exception('Pilihan hubungan dengan pasien tidak valid.');
        }

        $tsql = "
            INSERT INTO tbl_dokumentasi_fpe
                (id_jadwal, id_pasien, asesmen, hubungan_dengan_pasien, hubungan_lainnya, hasil_fpe, kemampuan_pasien, kemampuan_keluarga, nama_ppa, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, SYSDATETIME())
        ";
        $params = [
            $id_jadwal,
            (int)$id_pasien,
            $asesmen,
            $hubungan,
            $hubungan === 'lain_lain' ? ($hubungan_lainnya !== '' ? $hubungan_lainnya : null) : null,
            $hasil_fpe !== '' ? $hasil_fpe : null,
            $kemampuan_pasien !== '' ? $kemampuan_pasien : null,
            $kemampuan_keluarga !== '' ? $kemampuan_keluarga : null,
            $nama_petugas,
        ];

        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            throw new Exception('Gagal menyimpan dokumentasi: ' . ($errs[0]['message'] ?? 'Database error'));
        }
        sqlsrv_free_stmt($stmt);

        $pesan = 'Dokumentasi FPE berhasil disimpan.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ---------- DAFTAR JADWAL FPE PASIEN INI (untuk dropdown penghubung) ----------
$tsqlJadwal = "
    SELECT id_jadwal, 
           CONVERT(VARCHAR(10), tanggal_pelaksanaan, 120) AS tanggal_pelaksanaan, 
           CONVERT(VARCHAR(5), jam_pelaksanaan, 108) AS jam_pelaksanaan
    FROM tbl_jadwal_fpe
    WHERE id_pasien = ?
    ORDER BY tanggal_pelaksanaan DESC, jam_pelaksanaan DESC
";
$stmtJadwal = sqlsrv_query($conn, $tsqlJadwal, [(int)$id_pasien]);
$daftar_jadwal_pasien = [];
if ($stmtJadwal !== false) {
    while ($r = sqlsrv_fetch_array($stmtJadwal, SQLSRV_FETCH_ASSOC)) {
        $daftar_jadwal_pasien[] = $r;
    }
    sqlsrv_free_stmt($stmtJadwal);
}

// ---------- RIWAYAT DOKUMENTASI PASIEN INI ----------
$tsqlRiwayat = "
    SELECT id_dokumentasi, id_jadwal, id_pasien, asesmen, hubungan_dengan_pasien, hubungan_lainnya, hasil_fpe, kemampuan_pasien, kemampuan_keluarga, nama_ppa,
           CONVERT(VARCHAR(19), created_at, 120) AS created_at
    FROM tbl_dokumentasi_fpe
    WHERE id_pasien = ?
    ORDER BY created_at DESC
";
$stmtRiwayat = sqlsrv_query($conn, $tsqlRiwayat, [(int)$id_pasien]);
$daftar_dokumentasi = [];
if ($stmtRiwayat !== false) {
    while ($r = sqlsrv_fetch_array($stmtRiwayat, SQLSRV_FETCH_ASSOC)) {
        $daftar_dokumentasi[] = $r;
    }
    sqlsrv_free_stmt($stmtRiwayat);
}
?>

<div class="card mb-4 shadow-sm border-0">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
    <h5 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-medical me-2"></i>Formulir Bukti Dokumentasi FPE</h5>
    <span class="badge bg-light text-primary px-3 py-2">ID Pasien: <?= htmlspecialchars((string)$id_pasien) ?></span>
  </div>
  <div class="card-body p-4">

    <?php if ($pesan !== ''): ?>
      <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div><?= htmlspecialchars($pesan) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="simpan_dokumentasi_fpe" value="1">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label fw-semibold">Jadwal FPE Terkait (Opsional)</label>
          <select name="id_jadwal" class="form-select">
            <option value="">-- Tidak terhubung ke jadwal manapun --</option>
            <?php foreach ($daftar_jadwal_pasien as $j): ?>
              <option value="<?= (int)$j['id_jadwal'] ?>">
                <?= htmlspecialchars($j['tanggal_pelaksanaan'] . ' ' . substr($j['jam_pelaksanaan'], 0, 5)) ?> WIB
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Hubungan dengan Pasien <span class="text-danger">*</span></label>
          <select name="hubungan_dengan_pasien" id="hubungan_dengan_pasien" class="form-select" required onchange="dokToggleLainnya()">
            <option value="">-- Pilih Hubungan --</option>
            <?php foreach ($hubungan_options as $val => $label): ?>
              <option value="<?= $val ?>"><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12" id="dok_hubungan_lainnya_wrap" style="display:none;">
          <label class="form-label fw-semibold">Sebutkan Hubungan Lainnya</label>
          <input type="text" name="hubungan_lainnya" class="form-control" placeholder="Contoh: Tetangga, Wali Asuh">
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Asesmen (Hasil Wawancara dengan Keluarga) <span class="text-danger">*</span></label>
          <textarea name="asesmen" class="form-control" rows="3" required placeholder="Tuliskan hasil asesmen kesiapan keluarga..."></textarea>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Hasil FPE (Psikoterapi)</label>
          <textarea name="hasil_fpe" class="form-control" rows="3" placeholder="Tuliskan materi psikoedukasi yang disampaikan..."></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Kemampuan Pasien</label>
          <textarea name="kemampuan_pasien" class="form-control" rows="2" placeholder="Catatan respon dan kemampuan pasien..."></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Kemampuan Keluarga</label>
          <textarea name="kemampuan_keluarga" class="form-control" rows="2" placeholder="Catatan kesiapan dan pemahaman keluarga..."></textarea>
        </div>

      </div>

      <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top">
        <span class="text-muted small">Nama PPA / Petugas: <strong><?= htmlspecialchars($nama_petugas) ?></strong></span>
        <button type="submit" name="simpan_dokumentasi_fpe" class="btn btn-primary px-4 py-2 fw-semibold">
          <i class="bi bi-save me-1"></i> Simpan Dokumentasi
        </button>
      </div>
    </form>

    <script>
    function dokToggleLainnya() {
      var val = document.getElementById('hubungan_dengan_pasien').value;
      document.getElementById('dok_hubungan_lainnya_wrap').style.display = (val === 'lain_lain') ? 'block' : 'none';
    }
    </script>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Dokumentasi FPE Pasien Ini</h6>
      <span class="badge bg-secondary"><?= count($daftar_dokumentasi) ?> Dokumentasi</span>
    </div>

    <?php if (empty($daftar_dokumentasi)): ?>
      <div class="text-center py-4 text-muted bg-light rounded">
        <i class="bi bi-file-earmark-x fs-3 d-block mb-2 text-secondary"></i>
        Belum ada dokumentasi FPE untuk pasien ini.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead class="table-light text-center">
            <tr>
              <th style="width: 150px;">Tanggal Input</th>
              <th style="width: 130px;">Hubungan</th>
              <th>Asesmen & Hasil FPE</th>
              <th>Kemampuan</th>
              <th style="width: 140px;">PPA</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar_dokumentasi as $d): ?>
            <tr>
              <td class="text-center small"><?= htmlspecialchars($d['created_at']) ?></td>
              <td class="text-center fw-semibold">
                <?= htmlspecialchars($hubungan_options[$d['hubungan_dengan_pasien']] ?? '-') ?>
                <?php if (!empty($d['hubungan_lainnya'])): ?>
                  <div class="small text-muted">(<?= htmlspecialchars($d['hubungan_lainnya']) ?>)</div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold small text-primary mb-1">Asesmen:</div>
                <div class="small mb-2"><?= nl2br(htmlspecialchars($d['asesmen'] ?? '-')) ?></div>
                <?php if (!empty($d['hasil_fpe'])): ?>
                  <div class="fw-semibold small text-success mb-1">Hasil FPE:</div>
                  <div class="small"><?= nl2br(htmlspecialchars($d['hasil_fpe'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php if (!empty($d['kemampuan_pasien'])): ?>
                  <div><strong>Pasien:</strong> <?= htmlspecialchars($d['kemampuan_pasien']) ?></div>
                <?php endif; ?>
                <?php if (!empty($d['kemampuan_keluarga'])): ?>
                  <div><strong>Keluarga:</strong> <?= htmlspecialchars($d['kemampuan_keluarga']) ?></div>
                <?php endif; ?>
                <?php if (empty($d['kemampuan_pasien']) && empty($d['kemampuan_keluarga'])): ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-center small fw-semibold"><?= htmlspecialchars($d['nama_ppa'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>
