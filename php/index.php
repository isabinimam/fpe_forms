<?php
/**
 * STANDALONE TEST HARNESS & DASHBOARD FPE
 * RSKD Duren Sawit
 *
 * ⚠️ PERHATIAN PENGEMBANG (PORTABILITAS):
 * File ini HANYA digunakan untuk pengujian lokal / standalone.
 * JANGAN salin file ini ke main project (aplikasi utama sudah memiliki routing/template sendiri).
 */

require_once __DIR__ . '/config/app.php';
$conn = require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/wa_queue.php';

// Ambil daftar pasien dari tabel dummy tbl_pasien
$tsqlPasien = "SELECT id_pasien, nama_pasien, CONVERT(VARCHAR(10), tanggal_lahir, 120) AS tanggal_lahir FROM tbl_pasien ORDER BY id_pasien ASC";
$stmtPasien = sqlsrv_query($conn, $tsqlPasien);
$daftarPasien = [];
if ($stmtPasien !== false) {
    while ($r = sqlsrv_fetch_array($stmtPasien, SQLSRV_FETCH_ASSOC)) {
        $daftarPasien[] = $r;
    }
    sqlsrv_free_stmt($stmtPasien);
}

// Tentukan ID Pasien aktif (dari GET, atau default ke 123 jika ada riwayat, atau pasien pertama)
if (isset($_GET['id_pasien'])) {
    $id_pasien = (int)$_GET['id_pasien'];
} else {
    // Cari apakah pasien 123 ada di daftar, jika ada jadikan default aktif
    $found123 = false;
    foreach ($daftarPasien as $p) {
        if ((int)$p['id_pasien'] === 123) {
            $id_pasien = 123;
            $found123 = true;
            break;
        }
    }
    if (!$found123) {
        $id_pasien = (int)($daftarPasien[0]['id_pasien'] ?? 1);
    }
}
$nama_petugas = 'Petugas Uji Coba';

// Info pasien aktif dari tbl_pasien
$pasienAktif = null;
foreach ($daftarPasien as $p) {
    if ((int)$p['id_pasien'] === $id_pasien) {
        $pasienAktif = $p;
        break;
    }
}
if (!$pasienAktif && !empty($daftarPasien)) {
    $pasienAktif = $daftarPasien[0];
    $id_pasien = (int)$pasienAktif['id_pasien'];
}

// Ambil info kontak keluarga terakhir dari dedicated table tbl_jadwal_fpe (jika ada)
$kontakTerakhir = null;
$tsqlKontak = "SELECT TOP 1 nama_keluarga, nomor_wa FROM tbl_jadwal_fpe WHERE id_pasien = ? ORDER BY id_jadwal DESC";
$stmtKontak = sqlsrv_query($conn, $tsqlKontak, [(int)$id_pasien]);
if ($stmtKontak !== false) {
    $kontakTerakhir = sqlsrv_fetch_array($stmtKontak, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtKontak);
}

// Tab aktif
$tabAktif = $_GET['tab'] ?? 'jadwal';

// Handler Kirim Tes WhatsApp Cepat (Hanya saat WA_TEST_MODE = true)
$pesanTes = '';
$errorTes = '';
if (WA_TEST_MODE && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_tes_wa'])) {
    $nomorTes = trim($_POST['nomor_tujuan_tes'] ?? '');
    $pesanManual = trim($_POST['pesan_tes'] ?? '');
    
    $cleanTes = normalizePhoneNumber($nomorTes);
    if (!$cleanTes) {
        $errorTes = 'Nomor WhatsApp tujuan tes tidak valid.';
    } else {
        try {
            // Buat jadwal dummy dan antrean due-now
            $tsqlJadwalTes = "
                INSERT INTO tbl_jadwal_fpe
                    (id_pasien, tanggal_pelaksanaan, jam_pelaksanaan, metode, slot_waktu, nomor_wa, nama_keluarga, status_kirim_wa, dibuat_oleh, created_at)
                OUTPUT INSERTED.id_jadwal
                VALUES
                    (?, CAST(GETDATE() AS DATE), CAST(GETDATE() AS TIME), 'video_call_wa', '10.00-12.00', ?, N'Keluarga Tes', 'pending', N'Tester Otomatis', SYSDATETIME())
            ";
            $stmtJT = sqlsrv_query($conn, $tsqlJadwalTes, [$id_pasien, $cleanTes]);
            if ($stmtJT === false) {
                throw new Exception('Gagal membuat jadwal tes: ' . print_r(sqlsrv_errors(), true));
            }
            $rJT = sqlsrv_fetch_array($stmtJT, SQLSRV_FETCH_ASSOC);
            $idJadwalTes = (int)$rJT['id_jadwal'];
            sqlsrv_free_stmt($stmtJT);

            $nowStr = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
            if ($pesanManual === '') {
                $pesanManual = buildFpeReminderMessage([
                    'nama_pasien'         => $pasienAktif['nama_pasien'] ?? "Pasien #$id_pasien",
                    'nama_keluarga'       => 'Bpk/Ibu Penguji',
                    'tanggal_pelaksanaan' => date('Y-m-d'),
                    'jam_pelaksanaan'     => date('H:i'),
                    'metode'              => 'video_call_wa',
                    'slot_waktu'          => '10.00-12.00',
                ]);
            }

            createWaQueueJob($conn, $idJadwalTes, $cleanTes, $pesanManual, $nowStr, 'FPE_TEST');
            $pesanTes = "Pesan tes WhatsApp berhasil dimasukkan ke antrean (ID Jadwal: #$idJadwalTes, Nomor: +$cleanTes). Node.js worker akan segera memproses pengiriman.";
            $tabAktif = 'queue';
        } catch (Exception $e) {
            $errorTes = $e->getMessage();
        }
    }
}

// Ambil data antrean WhatsApp untuk tab monitoring
$tsqlQueueAll = "
    SELECT TOP 30
        q.id,
        q.id_jadwal,
        q.nomor_tujuan,
        q.tipe_notifikasi,
        q.status,
        q.attempts,
        q.max_attempts,
        q.last_error,
        CONVERT(VARCHAR(19), q.scheduled_at, 120) AS scheduled_at,
        CONVERT(VARCHAR(19), q.locked_at, 120) AS locked_at,
        CONVERT(VARCHAR(19), q.sent_at, 120) AS sent_at,
        CONVERT(VARCHAR(19), q.created_at, 120) AS created_at,
        j.tanggal_pelaksanaan,
        j.jam_pelaksanaan,
        j.metode
    FROM tbl_wa_queue q
    LEFT JOIN tbl_jadwal_fpe j ON q.id_jadwal = j.id_jadwal
    ORDER BY q.id DESC
";
$stmtQAll = sqlsrv_query($conn, $tsqlQueueAll);
$daftarQueue = [];
if ($stmtQAll !== false) {
    while ($r = sqlsrv_fetch_array($stmtQAll, SQLSRV_FETCH_ASSOC)) {
        $daftarQueue[] = $r;
    }
    sqlsrv_free_stmt($stmtQAll);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistem Formulir FPE & Notifikasi WhatsApp — RSKD Duren Sawit</title>
  <!-- Bootstrap 5 CSS CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background-color: #f4f6f9; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .nav-tabs .nav-link { font-weight: 500; color: #495057; }
    .nav-tabs .nav-link.active { font-weight: 600; color: #0d6efd; border-bottom: 3px solid #0d6efd; }
    .card-header { font-weight: 600; }
  </style>
</head>
<body class="pb-5">

<!-- Navbar Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
      <i class="bi bi-hospital me-2 fs-4"></i>
      RSKD Duren Sawit &bull; Formulir Psikoedukasi Keluarga (FPE)
    </a>
    <div class="d-flex align-items-center">
      <span class="badge bg-warning text-dark me-3 px-3 py-2">
        <i class="bi bi-tools me-1"></i> Standalone Test Mode
      </span>
      <span class="text-white small">
        <i class="bi bi-database me-1"></i> <?= htmlspecialchars(getenv('DB_DATABASE') ?: 'form_pfe') ?>
      </span>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 mt-4">

  <!-- Portability Alert Callout -->
  <div class="alert alert-secondary border-0 shadow-sm d-flex justify-content-between align-items-center py-2 px-3 mb-4">
    <div class="small text-muted">
      <i class="bi bi-info-circle-fill text-primary me-1"></i>
      <strong>Catatan Pengujian:</strong> Halaman ini adalah <em>test harness</em>. Setiap komponen form di bawah siap disalin langsung ke proyek utama bos Anda (lihat <code>docs/integration-guide.md</code>).
    </div>
    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Direct SQL Server Driver (sqlsrv)</span>
  </div>

  <!-- Header Pasien & Pemilih Pasien Aktif -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
      <div class="row align-items-center g-3">
        <div class="col-md-4">
          <form method="get" class="d-flex align-items-center">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tabAktif) ?>">
            <label class="form-label fw-semibold me-2 mb-0 text-nowrap"><i class="bi bi-person-fill text-primary me-1"></i>Pilih Pasien:</label>
            <select name="id_pasien" class="form-select form-select-sm" onchange="this.form.submit()">
              <?php foreach ($daftarPasien as $p): ?>
                <option value="<?= (int)$p['id_pasien'] ?>" <?= (int)$p['id_pasien'] === $id_pasien ? 'selected' : '' ?>>
                  [ID <?= (int)$p['id_pasien'] ?>] <?= htmlspecialchars($p['nama_pasien']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
        <div class="col-md-6">
          <div class="d-flex flex-wrap gap-2 text-muted small align-items-center">
            <div><strong>Pasien:</strong> <span class="text-dark fw-semibold"><?= htmlspecialchars($pasienAktif['nama_pasien'] ?? "Pasien #$id_pasien") ?></span></div>
            <?php if (!empty($pasienAktif['tanggal_lahir'])): 
              $umurPasienAktif = hitungUmurLengkap($pasienAktif['tanggal_lahir']);
            ?>
              <div>&bull;</div>
              <div><strong>Lahir:</strong> <?= htmlspecialchars(formatTanggalIndo($pasienAktif['tanggal_lahir'], false)) ?> <span class="badge bg-primary-subtle text-primary border ms-1"><?= htmlspecialchars($umurPasienAktif['teks']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($kontakTerakhir['nama_keluarga']) || !empty($kontakTerakhir['nomor_wa'])): ?>
              <div>&bull;</div>
              <div><strong>Kontak FPE:</strong> <span class="text-dark"><?= htmlspecialchars($kontakTerakhir['nama_keluarga'] ?? '-') ?> (+<?= htmlspecialchars($kontakTerakhir['nomor_wa'] ?? '-') ?>)</span></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-2 text-end">
          <span class="badge bg-light text-secondary border px-2 py-1">Petugas: <?= htmlspecialchars($nama_petugas) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigasi Tab Komponen Form -->
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link <?= $tabAktif === 'jadwal' ? 'active' : '' ?>" href="?id_pasien=<?= $id_pasien ?>&tab=jadwal">
        <i class="bi bi-calendar-check me-1"></i> 1. Penjadwalan FPE & WhatsApp
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tabAktif === 'dok' ? 'active' : '' ?>" href="?id_pasien=<?= $id_pasien ?>&tab=dok">
        <i class="bi bi-file-earmark-medical me-1"></i> 2. Dokumentasi FPE
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tabAktif === 'kegiatan' ? 'active' : '' ?>" href="?id_pasien=<?= $id_pasien ?>&tab=kegiatan">
        <i class="bi bi-calendar3-range me-1"></i> 3. Jadwal Kegiatan (10 Hari)
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tabAktif === 'skrining' ? 'active' : '' ?>" href="?id_pasien=<?= $id_pasien ?>&tab=skrining">
        <i class="bi bi-shield-shaded me-1"></i> 4. Skrining Risiko Bunuh Diri
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tabAktif === 'queue' ? 'active' : '' ?>" href="?id_pasien=<?= $id_pasien ?>&tab=queue">
        <i class="bi bi-broadcast me-1"></i> 5. Monitoring Antrean WhatsApp
      </a>
    </li>
  </ul>

  <!-- Konten Tab Sesuai Pilihan -->
  <?php if ($tabAktif === 'jadwal'): ?>
    <?php include __DIR__ . '/form_jadwal_fpe.php'; ?>

  <?php elseif ($tabAktif === 'dok'): ?>
    <?php include __DIR__ . '/form_dokumentasi_fpe.php'; ?>

  <?php elseif ($tabAktif === 'kegiatan'): ?>
    <?php include __DIR__ . '/form_kegiatan_pasien.php'; ?>

  <?php elseif ($tabAktif === 'skrining'): ?>
    <?php include __DIR__ . '/form_skrining_bunuh_diri.php'; ?>

  <?php elseif ($tabAktif === 'queue'): ?>
    <!-- TAB MONITORING ANTREAN WHATSAPP & PENGIRIMAN MANUAL -->
    <div class="row">
      <!-- Panel Pengiriman WhatsApp Manual -->
      <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-success text-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-send-fill me-2"></i>Kirim Notifikasi WhatsApp Manual</h6>
          </div>
          <div class="card-body p-4">
            <?php if ($pesanTes !== ''): ?>
              <div class="alert alert-success small mb-3"><?= htmlspecialchars($pesanTes) ?></div>
            <?php endif; ?>
            <?php if ($errorTes !== ''): ?>
              <div class="alert alert-danger small mb-3"><?= htmlspecialchars($errorTes) ?></div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="kirim_tes_wa" value="1">
              <div class="mb-3">
                <label class="form-label fw-semibold">Nomor WhatsApp Tujuan</label>
                <input type="tel" name="nomor_tujuan_tes" class="form-control" placeholder="Contoh: 085159811407" required value="<?= htmlspecialchars(getenv('WA_TEST_PHONE') ?: '6285159811407') ?>">
                <div class="form-text">Nomor ponsel penerima notifikasi.</div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Pesan Khusus (Opsional)</label>
                <textarea name="pesan_tes" class="form-control" rows="3" placeholder="Biarkan kosong untuk menggunakan format resmi FPE..."></textarea>
              </div>

              <div class="alert alert-light border small text-muted">
                <i class="bi bi-info-circle me-1"></i> Pesan akan langsung dimasukkan ke antrean pengiriman dan dikirim oleh worker Node.js.
              </div>

              <button type="submit" name="kirim_tes_wa" class="btn btn-success w-100 fw-semibold">
                <i class="bi bi-send-fill me-1"></i> Kirim Notifikasi Sekarang
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Panel Antrean Terkini -->
      <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-list-task me-2"></i>Status Antrean Notifikasi (tbl_wa_queue)</h6>
            <a href="?id_pasien=<?= $id_pasien ?>&tab=queue" class="btn btn-sm btn-light"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
          </div>
          <div class="card-body p-3">
            <?php if (empty($daftarQueue)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                Belum ada data antrean di tabel <code>tbl_wa_queue</code>.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle small">
                  <thead class="table-light text-center">
                    <tr>
                      <th>ID</th>
                      <th>Jadwal</th>
                      <th>Nomor Tujuan</th>
                      <th>Status</th>
                      <th>Jadwal Kirim</th>
                      <th>Terkirim</th>
                      <th>Coba</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($daftarQueue as $q): ?>
                    <tr>
                      <td class="text-center fw-semibold">#<?= (int)$q['id'] ?></td>
                      <td class="text-center">#<?= (int)$q['id_jadwal'] ?></td>
                      <td class="fw-medium">+<?= htmlspecialchars($q['nomor_tujuan']) ?></td>
                      <td class="text-center">
                        <span class="badge <?= waStatusBadgeClass($q['status']) ?> px-2 py-1">
                          <?= htmlspecialchars(waStatusLabel($q['status'])) ?>
                        </span>
                        <?php if (!empty($q['last_error'])): ?>
                          <div class="text-danger small mt-1" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($q['last_error']) ?>">
                            <?= htmlspecialchars($q['last_error']) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="text-center text-muted"><?= htmlspecialchars($q['scheduled_at'] ?? '-') ?></td>
                      <td class="text-center text-success fw-medium"><?= htmlspecialchars($q['sent_at'] ?? '-') ?></td>
                      <td class="text-center"><?= (int)$q['attempts'] ?> / <?= (int)$q['max_attempts'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- Bootstrap 5 JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
