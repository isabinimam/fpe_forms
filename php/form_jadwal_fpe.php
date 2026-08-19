<?php
/**
 * FORM PENJADWALAN PSIKOEDUKASI (FPE) & ANTREAN WHATSAPP OTOMATIS
 * =====================================================================
 * RSKD Duren Sawit
 *
 * File ini didesain untuk di-include ke aplikasi yang sudah berjalan.
 * Tidak berisi header/footer/menu - hanya kartu form + riwayat jadwal.
 *
 * VARIABEL YANG HARUS SUDAH ADA sebelum file ini di-include:
 *   $conn          -> resource sqlsrv_connect, koneksi SQL Server aktif
 *   $id_pasien     -> int, ID pasien yang sedang dibuka di halaman induk
 *   $nama_petugas  -> string, nama petugas yang login (opsional)
 *
 * Contoh pemanggilan dari halaman induk:
 *   $id_pasien    = 12;
 *   $nama_petugas = $_SESSION['nama'] ?? 'Petugas';
 *   include 'form_jadwal_fpe.php';
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

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/wa_queue.php';

$pesan = '';
$error = '';
$notifInfo = '';

// Ambil info nama pasien dari tbl_pasien (hanya id_pasien dan nama_pasien)
$namaPasienDefault = "Pasien #$id_pasien";
$namaKeluargaDefault = "";
$nomorWaDefault = "";

$tsqlPasien = "SELECT nama_pasien FROM tbl_pasien WHERE id_pasien = ?";
$stmtPasien = sqlsrv_query($conn, $tsqlPasien, [(int)$id_pasien]);
if ($stmtPasien !== false) {
    if ($rowP = sqlsrv_fetch_array($stmtPasien, SQLSRV_FETCH_ASSOC)) {
        $namaPasienDefault = $rowP['nama_pasien'] ?? $namaPasienDefault;
    }
    sqlsrv_free_stmt($stmtPasien);
}

// Ambil kontak keluarga default dari jadwal terakhir pasien ini di tbl_jadwal_fpe (tabel dedicated)
$tsqlLastContact = "SELECT TOP 1 nama_keluarga, nomor_wa FROM tbl_jadwal_fpe WHERE id_pasien = ? ORDER BY id_jadwal DESC";
$stmtLastContact = sqlsrv_query($conn, $tsqlLastContact, [(int)$id_pasien]);
if ($stmtLastContact !== false) {
    if ($rowLC = sqlsrv_fetch_array($stmtLastContact, SQLSRV_FETCH_ASSOC)) {
        $namaKeluargaDefault = $rowLC['nama_keluarga'] ?? '';
        $nomorWaDefault      = $rowLC['nomor_wa'] ?? '';
    }
    sqlsrv_free_stmt($stmtLastContact);
}

// ---------- PROSES SIMPAN DATA (ATOMIC TRANSACTION) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan_jadwal_fpe']) || isset($_POST['tanggal_pelaksanaan']))) {
    // Mulai transaksi atomik
    if (sqlsrv_begin_transaction($conn) === false) {
        $error = 'Gagal memulai transaksi database.';
    } else {
        try {
            $tanggal               = trim($_POST['tanggal_pelaksanaan'] ?? '');
            $jam                   = trim($_POST['jam_pelaksanaan'] ?? '');
            $metode                = trim($_POST['metode'] ?? '');
            $meeting_id            = trim($_POST['meeting_id'] ?? '');
            $passcode              = trim($_POST['passcode'] ?? '');
            $slot_waktu            = trim($_POST['slot_waktu'] ?? '');
            $nomor_wa_keluarga     = trim($_POST['nomor_wa_keluarga'] ?? '');
            $nama_keluarga         = trim($_POST['nama_keluarga'] ?? '');
            $kirimManualLangsung   = isset($_POST['kirim_manual_langsung']) && $_POST['kirim_manual_langsung'] == '1';

            // Validasi kolom wajib
            if ($tanggal === '' || $jam === '' || $metode === '' || $slot_waktu === '' || $nomor_wa_keluarga === '') {
                throw new Exception('Tanggal, jam, metode, slot waktu, dan nomor WhatsApp keluarga wajib diisi.');
            }

            // Normalisasi nomor telepon
            $cleanPhone = normalizePhoneNumber($nomor_wa_keluarga);
            if (!$cleanPhone) {
                throw new Exception('Nomor WhatsApp keluarga tidak valid. Masukkan nomor ponsel Indonesia yang benar (contoh: 081234567890).');
            }

            // 1. Hitung Waktu Pengiriman Notifikasi
            $leadDays = WA_NOTIFICATION_LEAD_DAYS;
            $notifTime = WA_NOTIFICATION_TIME;
            if ($kirimManualLangsung) {
                $scheduledAt = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
                $tipeNotif   = 'FPE_MANUAL';
            } else {
                $scheduledAt = calculateScheduledAt($tanggal, $leadDays, $notifTime);
                $tipeNotif   = 'FPE_REMINDER';
            }

            // 2. Susun Teks Pesan Pengingat
            $pesanWa = buildFpeReminderMessage([
                'nama_pasien'         => $namaPasienDefault,
                'nama_keluarga'       => $nama_keluarga !== '' ? $nama_keluarga : $namaKeluargaDefault,
                'tanggal_pelaksanaan' => $tanggal,
                'jam_pelaksanaan'     => $jam,
                'metode'              => $metode,
                'slot_waktu'          => $slot_waktu,
                'meeting_id'          => $meeting_id,
                'passcode'            => $passcode
            ]);

            // 3. Simpan Jadwal FPE ke tbl_jadwal_fpe
            $tsqlJadwal = "
                INSERT INTO tbl_jadwal_fpe
                    (id_pasien, tanggal_pelaksanaan, jam_pelaksanaan, metode, meeting_id, passcode, slot_waktu, nomor_wa, nama_keluarga, status_kirim_wa, jadwal_kirim_wa, pesan_wa, dibuat_oleh, created_at)
                OUTPUT INSERTED.id_jadwal
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, SYSDATETIME())
            ";
            $paramsJadwal = [
                (int)$id_pasien,
                $tanggal,
                $jam,
                $metode,
                $meeting_id !== '' ? $meeting_id : null,
                $passcode !== '' ? $passcode : null,
                $slot_waktu,
                $cleanPhone,
                $nama_keluarga !== '' ? $nama_keluarga : null,
                $scheduledAt,
                $pesanWa,
                $nama_petugas
            ];

            $stmtJadwal = sqlsrv_query($conn, $tsqlJadwal, $paramsJadwal);
            if ($stmtJadwal === false) {
                $errs = sqlsrv_errors();
                throw new Exception('Gagal menyimpan jadwal FPE: ' . ($errs[0]['message'] ?? 'Database error'));
            }

            $rowJadwal = sqlsrv_fetch_array($stmtJadwal, SQLSRV_FETCH_ASSOC);
            $idJadwalBaru = (int)$rowJadwal['id_jadwal'];
            sqlsrv_free_stmt($stmtJadwal);

            // 4. Buat Antrean Notifikasi di tbl_wa_queue
            createWaQueueJob($conn, $idJadwalBaru, $cleanPhone, $pesanWa, $scheduledAt, $tipeNotif);

            // Commit transaksi jika seluruh langkah berhasil
            sqlsrv_commit($conn);

            $pesan = "Jadwal FPE berhasil disimpan.";
            if ($kirimManualLangsung) {
                $notifInfo = "<span class='badge bg-primary me-1'><i class='bi bi-send-fill me-1'></i>Kirim Manual</span> Notifikasi WhatsApp langsung dimasukkan ke antrean kirim saat ini ke nomor <strong>+" . htmlspecialchars($cleanPhone) . "</strong>. Pengingat otomatis H-1 tidak akan dikirim ganda.";
            } else {
                $notifInfo = "Notifikasi WhatsApp otomatis dijadwalkan untuk dikirim pada: <strong>" . htmlspecialchars($scheduledAt) . " WIB</strong> (H-{$leadDays}) ke nomor <strong>+" . htmlspecialchars($cleanPhone) . "</strong>.";
            }

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// ---------- PROSES PENGIRIMAN MANUAL DARI TABEL RIWAYAT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_manual_jadwal'])) {
    $idJadwalKirim = (int)$_POST['id_jadwal_kirim'];
    try {
        $tsqlJ = "SELECT * FROM tbl_jadwal_fpe WHERE id_jadwal = ?";
        $stmtJ = sqlsrv_query($conn, $tsqlJ, [$idJadwalKirim]);
        if (!$stmtJ || !($rowJ = sqlsrv_fetch_array($stmtJ, SQLSRV_FETCH_ASSOC))) {
            throw new Exception("Jadwal #{$idJadwalKirim} tidak ditemukan.");
        }

        $nowStr = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
        $cleanPhone = normalizePhoneNumber($rowJ['nomor_wa']);
        if (!$cleanPhone) {
            throw new Exception("Nomor WhatsApp pada jadwal #{$idJadwalKirim} tidak valid.");
        }

        $tglStr = $rowJ['tanggal_pelaksanaan'] instanceof DateTimeInterface ? $rowJ['tanggal_pelaksanaan']->format('Y-m-d') : (string)$rowJ['tanggal_pelaksanaan'];
        $jamStr = $rowJ['jam_pelaksanaan'] instanceof DateTimeInterface ? $rowJ['jam_pelaksanaan']->format('H:i:s') : (string)$rowJ['jam_pelaksanaan'];

        $pesanWa = buildFpeReminderMessage([
            'nama_pasien'         => $namaPasienDefault,
            'nama_keluarga'       => $rowJ['nama_keluarga'],
            'tanggal_pelaksanaan' => $tglStr,
            'jam_pelaksanaan'     => $jamStr,
            'metode'              => $rowJ['metode'],
            'slot_waktu'          => $rowJ['slot_waktu'],
            'meeting_id'          => $rowJ['meeting_id'],
            'passcode'            => $rowJ['passcode']
        ]);

        // Cari antrean yang ada untuk jadwal ini
        $tsqlCheckQ = "SELECT id, status FROM tbl_wa_queue WHERE id_jadwal = ? ORDER BY id DESC";
        $stmtCheckQ = sqlsrv_query($conn, $tsqlCheckQ, [$idJadwalKirim]);
        $existingQueue = null;
        if ($stmtCheckQ && ($rowQ = sqlsrv_fetch_array($stmtCheckQ, SQLSRV_FETCH_ASSOC))) {
            $existingQueue = $rowQ;
        }

        if ($existingQueue && $existingQueue['status'] === 'pending') {
            // Ubah antrean pending yang ada menjadi kirim manual sekarang
            $queueId = (int)$existingQueue['id'];
            $tsqlUpdQ = "
                UPDATE tbl_wa_queue 
                SET status = 'pending', 
                    scheduled_at = SYSDATETIME(), 
                    tipe_notifikasi = 'FPE_MANUAL',
                    pesan = ?, 
                    attempts = 0, 
                    last_error = NULL, 
                    updated_at = SYSDATETIME() 
                WHERE id = ?
            ";
            sqlsrv_query($conn, $tsqlUpdQ, [$pesanWa, $queueId]);
        } else {
            // Buat job antrean manual baru
            $queueId = createWaQueueJob($conn, $idJadwalKirim, $cleanPhone, $pesanWa, $nowStr, 'FPE_MANUAL');
        }

        // Batalkan (gugurkan) semua antrean pending otomatis lain untuk jadwal ini agar tidak terkirim ganda
        $tsqlCancelOther = "
            UPDATE tbl_wa_queue 
            SET status = 'cancelled', 
                last_error = 'Otomatis gugur karena telah dikirim secara manual', 
                updated_at = SYSDATETIME() 
            WHERE id_jadwal = ? AND id != ? AND status = 'pending'
        ";
        sqlsrv_query($conn, $tsqlCancelOther, [$idJadwalKirim, $queueId]);

        // Perbarui status jadwal di tbl_jadwal_fpe ke pending
        sqlsrv_query($conn, "UPDATE tbl_jadwal_fpe SET status_kirim_wa = 'pending', jadwal_kirim_wa = SYSDATETIME() WHERE id_jadwal = ?", [$idJadwalKirim]);

        $pesan = "Notifikasi WhatsApp untuk Jadwal #{$idJadwalKirim} disetel untuk DIKIRIM MANUAL SEKARANG.";
        $notifInfo = "Pesan ditujukan ke nomor <strong>+" . htmlspecialchars($cleanPhone) . "</strong>. Pengingat otomatis H-1 yang belum terkirim otomatis <strong>gugur</strong> agar tidak ada pesan ganda.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ---------- RIWAYAT JADWAL & STATUS NOTIFIKASI PASIEN INI ----------
$tsqlRiwayat = "
    SELECT 
        j.id_jadwal,
        j.id_pasien,
        CONVERT(VARCHAR(10), j.tanggal_pelaksanaan, 120) AS tanggal_pelaksanaan,
        CONVERT(VARCHAR(5), j.jam_pelaksanaan, 108) AS jam_pelaksanaan,
        j.metode,
        j.meeting_id,
        j.passcode,
        j.slot_waktu,
        j.nomor_wa,
        j.nama_keluarga,
        j.dibuat_oleh,
        CONVERT(VARCHAR(19), j.created_at, 120) AS created_at,
        q.id AS id_queue,
        COALESCE(q.status, j.status_kirim_wa, 'pending') AS wa_status,
        COALESCE(CONVERT(VARCHAR(19), q.scheduled_at, 120), CONVERT(VARCHAR(19), j.jadwal_kirim_wa, 120)) AS wa_scheduled_at,
        COALESCE(CONVERT(VARCHAR(19), q.sent_at, 120), CONVERT(VARCHAR(19), j.waktu_terkirim, 120)) AS wa_sent_at,
        COALESCE(q.attempts, 1) AS wa_attempts,
        COALESCE(q.last_error, j.log_error) AS wa_last_error
    FROM tbl_jadwal_fpe j
    LEFT JOIN tbl_wa_queue q ON j.id_jadwal = q.id_jadwal AND q.tipe_notifikasi = 'FPE_REMINDER'
    WHERE j.id_pasien = ?
    ORDER BY j.tanggal_pelaksanaan DESC, j.jam_pelaksanaan DESC
";

$stmtRiwayat = sqlsrv_query($conn, $tsqlRiwayat, [(int)$id_pasien]);
$daftar_jadwal = [];
if ($stmtRiwayat !== false) {
    while ($row = sqlsrv_fetch_array($stmtRiwayat, SQLSRV_FETCH_ASSOC)) {
        $daftar_jadwal[] = $row;
    }
    sqlsrv_free_stmt($stmtRiwayat);
}
?>

<div class="card mb-4 shadow-sm border-0">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
    <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2"></i>Formulir Penjadwalan Psikoedukasi (FPE)</h5>
    <span class="badge bg-light text-primary px-3 py-2">ID Pasien: <?= htmlspecialchars((string)$id_pasien) ?></span>
  </div>
  <div class="card-body p-4">

    <?php if ($pesan !== ''): ?>
      <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>
          <div><?= htmlspecialchars($pesan) ?></div>
          <?php if ($notifInfo !== ''): ?>
            <div class="small mt-1"><?= $notifInfo ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <!-- Banner Informasi Otomatisasi WhatsApp -->
    <div class="alert alert-info border-info border-start border-4 bg-light py-2 px-3 mb-4">
      <div class="d-flex align-items-center">
        <span class="badge bg-success me-2"><i class="bi bi-whatsapp"></i> WhatsApp Otomatis</span>
        <span class="small text-dark">
          Pengingat jadwal akan <strong>dikirim secara otomatis H-<?= WA_NOTIFICATION_LEAD_DAYS ?></strong> (pukul <?= htmlspecialchars(WA_NOTIFICATION_TIME) ?> WIB) ke WhatsApp keluarga oleh antrean sistem.
        </span>
      </div>
    </div>

    <form method="post" id="formJadwalFpe" onsubmit="fpeDisableSubmitBtn()">
      <input type="hidden" name="simpan_jadwal_fpe" value="1">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label fw-semibold">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
          <input type="date" name="tanggal_pelaksanaan" id="fpe_tanggal" class="form-control" required min="<?= date('Y-m-d') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Jam Pelaksanaan <span class="text-danger">*</span></label>
          <input type="time" name="jam_pelaksanaan" id="fpe_jam" class="form-control" required value="10:00">
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Metode FPE <span class="text-danger">*</span></label>
          <select name="metode" id="metode" class="form-select" required onchange="fpeToggleZoom()">
            <option value="">-- Pilih Metode --</option>
            <option value="video_call_wa">Video Call (WA) Tab Ruangan</option>
            <option value="zoom_meeting">Zoom Meeting</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Slot Waktu <span class="text-danger">*</span></label>
          <select name="slot_waktu" class="form-select" required>
            <option value="">-- Pilih Slot Waktu --</option>
            <option value="10.00-12.00">10.00 - 12.00 WIB</option>
            <option value="14.00-15.00">14.00 - 15.00 WIB</option>
          </select>
        </div>

        <!-- Kolom WhatsApp Kontak Keluarga -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Nomor WhatsApp Keluarga <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
            <input type="tel" name="nomor_wa_keluarga" class="form-control" placeholder="Contoh: 081234567890" required value="<?= htmlspecialchars($nomorWaDefault) ?>">
          </div>
          <div class="form-text">Pesan pengingat otomatis akan dikirim ke nomor ini.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Nama Keluarga Kontak (Opsional)</label>
          <input type="text" name="nama_keluarga" class="form-control" placeholder="Contoh: Ibu Siti (Istri Pasien)" value="<?= htmlspecialchars($namaKeluargaDefault) ?>">
        </div>

        <!-- Kolom Khusus Zoom (Ditampilkan jika metode = zoom_meeting) -->
        <div id="fpe_zoom_id" class="col-md-6" style="display:none;">
          <label class="form-label fw-semibold">Meeting ID Zoom</label>
          <input type="text" name="meeting_id" class="form-control" placeholder="Contoh: 838 1051 3404">
        </div>

        <!-- Opsi Kirim Langsung Sekarang (Manual) -->
        <div class="col-12">
          <div class="form-check form-switch p-3 bg-light rounded border">
            <input class="form-check-input ms-0 me-2" type="checkbox" name="kirim_manual_langsung" id="kirim_manual_langsung" value="1">
            <label class="form-check-label fw-semibold text-primary" for="kirim_manual_langsung">
              <i class="bi bi-send-check-fill me-1"></i> Langsung Kirim Notifikasi WhatsApp Sekarang (Manual)
            </label>
            <div class="form-text text-muted ps-4">Centang opsi ini jika ingin notifikasi WhatsApp langsung dikirim detik ini juga saat jadwal disimpan (pengingat otomatis H-1 tidak akan dikirim ganda).</div>
          </div>
        </div>

      </div>

      <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top">
        <span class="text-muted small">Petugas Pencatat: <strong><?= htmlspecialchars($nama_petugas) ?></strong></span>
        <button type="submit" name="simpan_jadwal_fpe" id="btnSimpanJadwalFpe" class="btn btn-primary px-4 py-2 fw-semibold">
          <i class="bi bi-save me-1"></i> Simpan Jadwal & Jadwalkan Notifikasi
        </button>
      </div>
    </form>

    <script>
    function fpeToggleZoom() {
      var metode = document.getElementById('metode').value;
      var display = (metode === 'zoom_meeting') ? 'block' : 'none';
      document.getElementById('fpe_zoom_id').style.display = display;
      document.getElementById('fpe_zoom_pass').style.display = display;
    }

    function fpeDisableSubmitBtn() {
      var btn = document.getElementById('btnSimpanJadwalFpe');
      if (btn) {
        setTimeout(function() {
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan...';
        }, 10);
      }
    }
    </script>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Jadwal FPE Pasien Ini</h6>
      <span class="badge bg-secondary"><?= count($daftar_jadwal) ?> Jadwal Tercatat</span>
    </div>

    <?php if (empty($daftar_jadwal)): ?>
      <div class="text-center py-4 text-muted bg-light rounded">
        <i class="bi bi-calendar-x fs-3 d-block mb-2 text-secondary"></i>
        Belum ada jadwal FPE untuk pasien ini.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead class="table-light text-center">
            <tr>
              <th style="width: 110px;">Tanggal</th>
              <th style="width: 80px;">Jam</th>
              <th>Metode</th>
              <th>Slot</th>
              <th>WhatsApp Keluarga</th>
              <th style="width: 140px;">Status Notifikasi</th>
              <th>Jadwal Kirim</th>
              <th>Petugas</th>
              <th style="width: 150px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar_jadwal as $j): ?>
            <tr>
              <td class="text-center fw-semibold"><?= htmlspecialchars($j['tanggal_pelaksanaan']) ?></td>
              <td class="text-center"><?= htmlspecialchars($j['jam_pelaksanaan']) ?></td>
              <td>
                <?php if ($j['metode'] === 'zoom_meeting'): ?>
                  <span class="badge bg-primary"><i class="bi bi-camera-video me-1"></i>Zoom</span>
                  <?php if (!empty($j['meeting_id'])): ?>
                    <div class="small text-muted mt-1">ID: <?= htmlspecialchars($j['meeting_id']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-success"><i class="bi bi-whatsapp me-1"></i>Video Call WA</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= htmlspecialchars($j['slot_waktu']) ?></td>
              <td>
                <div class="fw-semibold">+<?= htmlspecialchars($j['nomor_wa'] ?? '') ?></div>
                <?php if (!empty($j['nama_keluarga'])): ?>
                  <div class="small text-muted"><?= htmlspecialchars($j['nama_keluarga']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <span class="badge <?= waStatusBadgeClass($j['wa_status']) ?> px-2 py-1">
                  <?= htmlspecialchars(waStatusLabel($j['wa_status'])) ?>
                </span>
                <?php if ($j['wa_status'] === 'sent' && !empty($j['wa_sent_at'])): ?>
                  <div class="small text-success mt-1" title="Waktu Terkirim">
                    <i class="bi bi-check2-all"></i> <?= htmlspecialchars(substr($j['wa_sent_at'], 11, 5)) ?>
                  </div>
                <?php elseif ($j['wa_status'] === 'failed'): ?>
                  <div class="small text-danger mt-1" title="<?= htmlspecialchars($j['wa_last_error'] ?? '') ?>">
                    Coba: <?= (int)$j['wa_attempts'] ?>x
                  </div>
                <?php endif; ?>
              </td>
              <td class="small text-muted text-center">
                <?= !empty($j['wa_scheduled_at']) ? htmlspecialchars($j['wa_scheduled_at']) : '-' ?>
              </td>
              <td class="small text-center"><?= htmlspecialchars($j['dibuat_oleh'] ?? '-') ?></td>
              <td class="text-center">
                <?php if ($j['wa_status'] === 'sent'): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="id_jadwal_kirim" value="<?= (int)$j['id_jadwal'] ?>">
                    <button type="submit" name="kirim_manual_jadwal" class="btn btn-sm btn-outline-secondary px-2 py-1 text-nowrap" title="Kirim ulang pesan WhatsApp ke nomor keluarga">
                      <i class="bi bi-arrow-repeat me-1"></i> Kirim Ulang
                    </button>
                  </form>
                <?php elseif ($j['wa_status'] === 'processing'): ?>
                  <span class="badge bg-info text-dark px-2 py-1">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim...
                  </span>
                <?php else: ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="id_jadwal_kirim" value="<?= (int)$j['id_jadwal'] ?>">
                    <button type="submit" name="kirim_manual_jadwal" class="btn btn-sm btn-success px-2 py-1 text-nowrap" title="Kirim notifikasi WhatsApp sekarang (pengingat otomatis akan gugur)">
                      <i class="bi bi-send-fill me-1"></i> Kirim Manual
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>
