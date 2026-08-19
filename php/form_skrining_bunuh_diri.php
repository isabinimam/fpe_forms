<?php
/**
 * FORM SKRINING RISIKO BUNUH DIRI
 * =====================================================================
 * RSKD Duren Sawit
 *
 * File ini didesain untuk di-include ke aplikasi yang sudah berjalan.
 *
 * VARIABEL YANG HARUS SUDAH ADA sebelum file ini di-include:
 *   $conn          -> resource sqlsrv_connect, koneksi SQL Server aktif
 *   $id_pasien     -> int, ID pasien yang sedang dibuka
 *   $nama_petugas  -> string, nama petugas skrining yang login (opsional)
 *
 * Contoh pemanggilan dari halaman induk:
 *   $id_pasien    = 12;
 *   $nama_petugas = $_SESSION['nama'] ?? 'Petugas';
 *   include 'form_skrining_bunuh_diri.php';
 *
 * Skoring dihitung otomatis oleh PHP (lihat fungsi hitungSkoringBunuhDiri):
 *   - Pertanyaan 1 = Ya                              -> "Depresi"
 *   - Pertanyaan 2 = Ya, atau Pertanyaan 3 = Ya       -> "Risiko Bunuh Diri"
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

if (!function_exists('hitungSkoringBunuhDiri')) {
    function hitungSkoringBunuhDiri(string $p1, string $p2, string $p3, ?string $p3a): string
    {
        $kategori = [];

        if ($p1 === 'ya') {
            $kategori[] = 'Depresi';
        }
        if ($p2 === 'ya' || $p3 === 'ya') {
            $kategori[] = 'Risiko Bunuh Diri';
        }

        return empty($kategori) ? 'Tidak Berisiko' : implode(', ', array_unique($kategori));
    }
}

// ---------- PROSES SIMPAN DATA ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_skrining'])) {
    try {
        $tanggal_datang   = trim($_POST['tanggal_datang'] ?? '');
        $jam_datang       = trim($_POST['jam_datang'] ?? '');
        $status_pasien    = trim($_POST['status_pasien'] ?? '');
        $rujukan          = trim($_POST['rujukan'] ?? '');
        $rujukan_dari     = trim($_POST['rujukan_dari'] ?? '');
        $disabilitas      = trim($_POST['disabilitas'] ?? '');
        $diagnosis        = trim($_POST['diagnosis'] ?? '');
        $keluhan          = trim($_POST['keluhan_saat_ini'] ?? '');
        $p1               = trim($_POST['pertanyaan_1'] ?? '');
        $p2               = trim($_POST['pertanyaan_2'] ?? '');
        $p3               = trim($_POST['pertanyaan_3'] ?? '');
        $p3a              = trim($_POST['pertanyaan_3a'] ?? '');
        $lokasi           = trim($_POST['lokasi'] ?? '');

        if ($tanggal_datang === '' || $jam_datang === '' || $status_pasien === '' || $rujukan === '' || $disabilitas === '' || $lokasi === '') {
            throw new Exception('Mohon lengkapi semua kolom wajib (tanggal, jam, status pasien, rujukan, disabilitas, lokasi).');
        }

        $hasil_skoring = hitungSkoringBunuhDiri($p1, $p2, $p3, $p3a ?: null);

        $tsql = "
            INSERT INTO tbl_skrining_risiko_bunuh_diri
                (id_pasien, tanggal_datang, jam_datang, status_pasien, rujukan, rujukan_dari, disabilitas, diagnosis, keluhan_saat_ini,
                 pertanyaan_1, pertanyaan_2, pertanyaan_3, pertanyaan_3a, hasil_skoring, lokasi, nama_petugas_skrining, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?, ?, ?, ?, SYSDATETIME())
        ";
        $params = [
            (int)$id_pasien,
            $tanggal_datang,
            $jam_datang,
            $status_pasien,
            $rujukan,
            $rujukan === 'ya' && $rujukan_dari !== '' ? $rujukan_dari : null,
            $disabilitas,
            $diagnosis !== '' ? $diagnosis : null,
            $keluhan !== '' ? $keluhan : null,
            $p1 ?: null,
            $p2 ?: null,
            $p3 ?: null,
            ($p3 === 'ya' && $p3a !== '') ? $p3a : null,
            $hasil_skoring,
            $lokasi,
            $nama_petugas,
        ];

        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            throw new Exception('Gagal menyimpan skrining: ' . ($errs[0]['message'] ?? 'Database error'));
        }
        sqlsrv_free_stmt($stmt);

        $pesan = 'Skrining risiko bunuh diri berhasil disimpan. Hasil skoring otomatis: <strong>' . htmlspecialchars($hasil_skoring) . '</strong>.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ---------- RIWAYAT SKRINING PASIEN INI ----------
$tsqlRiwayat = "
    SELECT id_skrining, id_pasien, 
           CONVERT(VARCHAR(10), tanggal_datang, 120) AS tanggal_datang, 
           CONVERT(VARCHAR(5), jam_datang, 108) AS jam_datang, 
           status_pasien, rujukan, rujukan_dari, disabilitas, diagnosis, keluhan_saat_ini,
           pertanyaan_1, pertanyaan_2, pertanyaan_3, pertanyaan_3a, hasil_skoring, lokasi, nama_petugas_skrining,
           CONVERT(VARCHAR(19), created_at, 120) AS created_at
    FROM tbl_skrining_risiko_bunuh_diri
    WHERE id_pasien = ?
    ORDER BY tanggal_datang DESC, jam_datang DESC
";
$stmtRiwayat = sqlsrv_query($conn, $tsqlRiwayat, [(int)$id_pasien]);
$daftar_skrining = [];
if ($stmtRiwayat !== false) {
    while ($r = sqlsrv_fetch_array($stmtRiwayat, SQLSRV_FETCH_ASSOC)) {
        $daftar_skrining[] = $r;
    }
    sqlsrv_free_stmt($stmtRiwayat);
}
?>

<div class="card mb-4 shadow-sm border-0">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
    <h5 class="mb-0 fw-semibold"><i class="bi bi-shield-shaded me-2"></i>Formulir Skrining Risiko Bunuh Diri</h5>
    <span class="badge bg-light text-primary px-3 py-2">ID Pasien: <?= htmlspecialchars((string)$id_pasien) ?></span>
  </div>
  <div class="card-body p-4">

    <?php if ($pesan !== ''): ?>
      <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div><?= $pesan ?></div>
      </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="simpan_skrining" value="1">
      <div class="row g-3">

        <div class="col-md-4">
          <label class="form-label fw-semibold">Tanggal Datang <span class="text-danger">*</span></label>
          <input type="date" name="tanggal_datang" class="form-control" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Jam Datang <span class="text-danger">*</span></label>
          <input type="time" name="jam_datang" class="form-control" required value="<?= date('H:i') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Status Pasien <span class="text-danger">*</span></label>
          <select name="status_pasien" class="form-select" required>
            <option value="">-- Pilih Status --</option>
            <option value="lama">Pasien Lama</option>
            <option value="baru">Pasien Baru</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Rujukan <span class="text-danger">*</span></label>
          <select name="rujukan" id="rujukan" class="form-select" required onchange="skrToggleRujukan()">
            <option value="">-- Pilih --</option>
            <option value="ya">Ya</option>
            <option value="tidak">Tidak</option>
          </select>
        </div>
        <div class="col-md-4" id="skr_rujukan_dari_wrap" style="display:none;">
          <label class="form-label fw-semibold">Rujukan Dari</label>
          <input type="text" name="rujukan_dari" class="form-control" placeholder="Contoh: Puskesmas Duren Sawit">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Disabilitas <span class="text-danger">*</span></label>
          <select name="disabilitas" class="form-select" required>
            <option value="">-- Pilih --</option>
            <option value="tidak_ada">Tidak Ada</option>
            <option value="ada">Ada</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Diagnosis Medis/Psikiatri</label>
          <input type="text" name="diagnosis" class="form-control" placeholder="Contoh: Skizofrenia Paranoid (F20.0)">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Lokasi Pemeriksaan <span class="text-danger">*</span></label>
          <select name="lokasi" class="form-select" required>
            <option value="">-- Pilih Lokasi --</option>
            <option value="igd">IGD</option>
            <option value="poli">Poliklinik</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label fw-semibold">Keluhan Saat Ini</label>
          <textarea name="keluhan_saat_ini" class="form-control" rows="2" placeholder="Keluhan utama pasien saat datang..."></textarea>
        </div>
      </div>

      <hr class="my-4">
      <div class="alert alert-secondary py-2 small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Pertanyaan Skrining Risiko (Usia &ge; 12 Tahun):</strong> Ditanyakan kepada semua pasien sesuai standar keselamatan RS.
      </div>

      <div class="mb-3 p-3 bg-light rounded border">
        <label class="form-label fw-bold">1. Selama 2 minggu terakhir ini, apakah Anda merasa sedih, tertekan, atau putus asa?</label><br>
        <?php foreach (['ya' => 'Ya', 'tidak' => 'Tidak', 'menyangkal' => 'Menyangkal', 'tidak_menjawab' => 'Tidak Menjawab'] as $val => $label): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="pertanyaan_1" value="<?= $val ?>" id="p1_<?= $val ?>" required>
            <label class="form-check-label" for="p1_<?= $val ?>"><?= $label ?></label>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mb-3 p-3 bg-light rounded border">
        <label class="form-label fw-bold">2. Selama 2 minggu terakhir ini, apakah Anda pernah berpikir untuk bunuh diri?</label><br>
        <?php foreach (['ya' => 'Ya', 'tidak' => 'Tidak', 'menyangkal' => 'Menyangkal', 'tidak_menjawab' => 'Tidak Menjawab'] as $val => $label): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="pertanyaan_2" value="<?= $val ?>" id="p2_<?= $val ?>" required>
            <label class="form-check-label" for="p2_<?= $val ?>"><?= $label ?></label>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mb-3 p-3 bg-light rounded border">
        <label class="form-label fw-bold">3. Dalam hidup Anda, apakah pernah mencoba bunuh diri?</label><br>
        <?php foreach (['ya' => 'Ya', 'tidak' => 'Tidak', 'menyangkal' => 'Menyangkal', 'tidak_menjawab' => 'Tidak Menjawab'] as $val => $label): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="pertanyaan_3" id="p3_<?= $val ?>" value="<?= $val ?>" required onclick="skrToggle3a()">
            <label class="form-check-label" for="p3_<?= $val ?>"><?= $label ?></label>
          </div>
        <?php endforeach; ?>

        <div class="mt-3 pt-2 border-top" id="skr_p3a_wrap" style="display:none;">
          <label class="form-label fw-semibold text-danger">3a. Jika jawaban Ya, kapan terakhir Anda melakukan percobaan bunuh diri?</label><br>
          <?php
          $p3a_options = [
              'dalam_24jam'          => 'Dalam 24 jam terakhir (termasuk hari ini)',
              'dalam_bulan_terakhir' => 'Dalam bulan terakhir (tidak termasuk hari ini)',
              '1_6bulan'             => 'Antara 1 dan 6 bulan yang lalu',
              'lebih_6bulan'         => 'Lebih dari 6 bulan yang lalu',
              'menyangkal'           => 'Menyangkal',
              'tidak_menjawab'       => 'Tidak Menjawab',
          ];
          foreach ($p3a_options as $val => $label): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="pertanyaan_3a" value="<?= $val ?>" id="p3a_<?= $val ?>">
              <label class="form-check-label" for="p3a_<?= $val ?>"><?= $label ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top">
        <span class="text-muted small">Petugas Skrining: <strong><?= htmlspecialchars($nama_petugas) ?></strong></span>
        <button type="submit" name="simpan_skrining" class="btn btn-primary px-4 py-2 fw-semibold">
          <i class="bi bi-save me-1"></i> Simpan Hasil Skrining
        </button>
      </div>
    </form>

    <script>
    function skrToggleRujukan() {
      var val = document.getElementById('rujukan').value;
      document.getElementById('skr_rujukan_dari_wrap').style.display = (val === 'ya') ? 'block' : 'none';
    }
    function skrToggle3a() {
      var checked = document.querySelector('input[name="pertanyaan_3"]:checked');
      var show = checked && checked.value === 'ya';
      document.getElementById('skr_p3a_wrap').style.display = show ? 'block' : 'none';
    }
    </script>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Skrining Pasien Ini</h6>
      <span class="badge bg-secondary"><?= count($daftar_skrining) ?> Skrining</span>
    </div>

    <?php if (empty($daftar_skrining)): ?>
      <div class="text-center py-4 text-muted bg-light rounded">
        <i class="bi bi-shield-x fs-3 d-block mb-2 text-secondary"></i>
        Belum ada riwayat skrining untuk pasien ini.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead class="table-light text-center">
            <tr>
              <th style="width: 140px;">Tanggal & Jam</th>
              <th style="width: 160px;">Hasil Skoring</th>
              <th>Lokasi</th>
              <th>Diagnosis / Keluhan</th>
              <th style="width: 140px;">Petugas</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar_skrining as $s): ?>
            <tr>
              <td class="text-center small">
                <div class="fw-semibold"><?= htmlspecialchars($s['tanggal_datang']) ?></div>
                <div class="text-muted"><?= htmlspecialchars($s['jam_datang']) ?> WIB</div>
              </td>
              <td class="text-center">
                <?php if (strpos($s['hasil_skoring'] ?? '', 'Risiko Bunuh Diri') !== false): ?>
                  <span class="badge bg-danger px-2 py-1"><i class="bi bi-exclamation-octagon me-1"></i><?= htmlspecialchars($s['hasil_skoring']) ?></span>
                <?php elseif (strpos($s['hasil_skoring'] ?? '', 'Depresi') !== false): ?>
                  <span class="badge bg-warning text-dark px-2 py-1"><?= htmlspecialchars($s['hasil_skoring']) ?></span>
                <?php else: ?>
                  <span class="badge bg-success px-2 py-1"><?= htmlspecialchars($s['hasil_skoring'] ?? 'Tidak Berisiko') ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center fw-semibold"><?= strtoupper(htmlspecialchars($s['lokasi'])) ?></td>
              <td class="small">
                <?php if (!empty($s['diagnosis'])): ?>
                  <div><strong>Diagnosis:</strong> <?= htmlspecialchars($s['diagnosis']) ?></div>
                <?php endif; ?>
                <?php if (!empty($s['keluhan_saat_ini'])): ?>
                  <div class="text-muted"><?= htmlspecialchars($s['keluhan_saat_ini']) ?></div>
                <?php endif; ?>
                <?php if (empty($s['diagnosis']) && empty($s['keluhan_saat_ini'])): ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-center small"><?= htmlspecialchars($s['nama_petugas_skrining'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>
