<?php
/**
 * FORM JADWAL KEGIATAN PASIEN (Grid Centang 10 Hari)
 * =====================================================================
 * RSKD Duren Sawit
 *
 * File ini didesain untuk di-include ke aplikasi yang sudah berjalan.
 *
 * VARIABEL YANG HARUS SUDAH ADA sebelum file ini di-include:
 *   $conn          -> resource sqlsrv_connect, koneksi SQL Server aktif
 *   $id_pasien     -> int, ID pasien yang sedang dibuka
 *
 * Contoh pemanggilan dari halaman induk:
 *   $id_pasien = 12;
 *   include 'form_kegiatan_pasien.php';
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

$pesan = '';
$error = '';
$hari_roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

// ---------- Tentukan periode aktif ----------
$periode_mulai = $_POST['periode_mulai'] ?? $_GET['periode_mulai'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periode_mulai)) {
    $periode_mulai = date('Y-m-d');
}

// ---------- Ambil master kegiatan ----------
$tsqlMaster = "
    SELECT id_kegiatan, 
           CONVERT(VARCHAR(5), waktu, 108) AS waktu, 
           nama_kegiatan, 
           urutan 
    FROM tbl_master_kegiatan 
    ORDER BY urutan ASC, waktu ASC
";
$stmtMaster = sqlsrv_query($conn, $tsqlMaster);
$master_kegiatan = [];
if ($stmtMaster !== false) {
    while ($r = sqlsrv_fetch_array($stmtMaster, SQLSRV_FETCH_ASSOC)) {
        $master_kegiatan[] = $r;
    }
    sqlsrv_free_stmt($stmtMaster);
}

// ---------- PROSES SIMPAN DATA (SQL SERVER MERGE) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_kegiatan_pasien'])) {
    try {
        $checked = $_POST['kegiatan'] ?? []; // format: kegiatan[id_kegiatan][hari_ke] = "1"

        // Statement MERGE SQL Server untuk menggantikan ON DUPLICATE KEY UPDATE MySQL
        $tsqlMerge = "
            MERGE tbl_kegiatan_pasien AS target
            USING (SELECT ? AS id_pasien, ? AS id_kegiatan, ? AS tanggal) AS source
            ON (target.id_pasien = source.id_pasien AND target.id_kegiatan = source.id_kegiatan AND target.tanggal = source.tanggal)
            WHEN MATCHED THEN
                UPDATE SET status_centang = ?, hari_ke = ?
            WHEN NOT MATCHED THEN
                INSERT (id_pasien, id_kegiatan, hari_ke, tanggal, status_centang)
                VALUES (?, ?, ?, ?, ?);
        ";

        foreach ($master_kegiatan as $k) {
            $id_kegiatan = (int)$k['id_kegiatan'];
            for ($hari_ke = 1; $hari_ke <= 10; $hari_ke++) {
                $tanggal = date('Y-m-d', strtotime($periode_mulai . " +" . ($hari_ke - 1) . " days"));
                $is_checked = isset($checked[$id_kegiatan][$hari_ke]) ? 1 : 0;

                $params = [
                    (int)$id_pasien,
                    $id_kegiatan,
                    $tanggal,
                    $is_checked,
                    $hari_ke,
                    (int)$id_pasien,
                    $id_kegiatan,
                    $hari_ke,
                    $tanggal,
                    $is_checked
                ];

                $stmtMerge = sqlsrv_query($conn, $tsqlMerge, $params);
                if ($stmtMerge === false) {
                    $errs = sqlsrv_errors();
                    throw new Exception('Gagal menyimpan jadwal kegiatan: ' . ($errs[0]['message'] ?? 'Database error'));
                }
                sqlsrv_free_stmt($stmtMerge);
            }
        }

        $pesan = 'Jadwal kegiatan pasien untuk periode mulai ' . htmlspecialchars($periode_mulai) . ' berhasil disimpan.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ---------- AMBIL DATA EXISTING UNTUK PERIODE INI (PRE-CHECK) ----------
$tanggal_awal  = $periode_mulai;
$tanggal_akhir = date('Y-m-d', strtotime($periode_mulai . ' +9 days'));

$tsqlExisting = "
    SELECT id_kegiatan, 
           CONVERT(VARCHAR(10), tanggal, 120) AS tanggal, 
           status_centang
    FROM tbl_kegiatan_pasien
    WHERE id_pasien = ? AND tanggal BETWEEN ? AND ?
";
$stmtExisting = sqlsrv_query($conn, $tsqlExisting, [(int)$id_pasien, $tanggal_awal, $tanggal_akhir]);

$existing = [];
if ($stmtExisting !== false) {
    while ($row = sqlsrv_fetch_array($stmtExisting, SQLSRV_FETCH_ASSOC)) {
        $diffDays = (int)((strtotime($row['tanggal']) - strtotime($tanggal_awal)) / 86400);
        $hari_ke = $diffDays + 1;
        $existing[(int)$row['id_kegiatan']][$hari_ke] = (int)$row['status_centang'];
    }
    sqlsrv_free_stmt($stmtExisting);
}
?>

<div class="card mb-4 shadow-sm border-0">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
    <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar3-range me-2"></i>Jadwal Kegiatan Pasien (10 Hari)</h5>
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

    <form method="get" class="row g-2 align-items-end mb-4 bg-light p-3 rounded border">
      <input type="hidden" name="id_pasien" value="<?= (int)$id_pasien ?>">
      <input type="hidden" name="tab" value="kegiatan">
      <div class="col-auto">
        <label class="form-label fw-semibold mb-0"><i class="bi bi-calendar-date text-primary me-1"></i>Tanggal Mulai Periode (Hari I):</label>
        <input type="date" name="periode_mulai" class="form-control" value="<?= htmlspecialchars($periode_mulai) ?>" onchange="this.form.submit()">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary">
          <i class="bi bi-calendar-event me-1"></i> Tampilkan Periode
        </button>
      </div>
    </form>

    <form method="post" action="?id_pasien=<?= (int)$id_pasien ?>&tab=kegiatan&periode_mulai=<?= htmlspecialchars($periode_mulai) ?>">
      <input type="hidden" name="simpan_kegiatan_pasien" value="1">
      <input type="hidden" name="id_pasien" value="<?= (int)$id_pasien ?>">
      <input type="hidden" name="tab" value="kegiatan">
      <input type="hidden" name="periode_mulai" value="<?= htmlspecialchars($periode_mulai) ?>">

      <div class="table-responsive">
        <table class="table table-hover table-bordered text-center align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 80px;">Waktu</th>
              <th style="min-width: 220px;" class="text-start">Nama Kegiatan</th>
              <?php foreach ($hari_roman as $idx => $h): 
                $tglCol = date('d/m', strtotime($periode_mulai . " +{$idx} days"));
              ?>
                <th style="min-width: 45px;">
                  <div><?= $h ?></div>
                  <div class="small text-muted fw-normal" style="font-size: 0.75rem;"><?= $tglCol ?></div>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($master_kegiatan as $k): ?>
            <tr>
              <td class="fw-semibold small text-muted"><?= htmlspecialchars($k['waktu']) ?></td>
              <td class="text-start fw-medium"><?= htmlspecialchars($k['nama_kegiatan']) ?></td>
              <?php for ($hari_ke = 1; $hari_ke <= 10; $hari_ke++):
                  $is_checked = $existing[(int)$k['id_kegiatan']][$hari_ke] ?? 0;
              ?>
                <td>
                  <input type="checkbox"
                         class="form-check-input"
                         style="width: 1.25em; height: 1.25em;"
                         name="kegiatan[<?= (int)$k['id_kegiatan'] ?>][<?= $hari_ke ?>]"
                         value="1"
                         <?= $is_checked ? 'checked' : '' ?>>
                </td>
              <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
        <p class="text-muted mb-0 small">
          Rentang Periode Aktif: <strong><?= htmlspecialchars($tanggal_awal) ?></strong> s/d <strong><?= htmlspecialchars($tanggal_akhir) ?></strong> (Hari I - X)
        </p>
        <button type="submit" name="simpan_kegiatan_pasien" class="btn btn-primary px-4 py-2 fw-semibold">
          <i class="bi bi-save me-1"></i> Simpan Jadwal Kegiatan
        </button>
      </div>
    </form>

  </div>
</div>
