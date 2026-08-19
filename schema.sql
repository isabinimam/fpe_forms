-- =====================================================================
-- SKEMA DATABASE - FORMULIR PSIKOEDUKASI (FPE)
-- RSKD Duren Sawit
-- =====================================================================
-- Import file ini ke database aplikasi yang sudah ada.
-- Sesuaikan tipe id_pasien / dibuat_oleh dengan struktur tabel pasien
-- dan tabel petugas yang sudah ada di aplikasi Anda bila diperlukan
-- (mis. ganti INT menjadi tipe yang sama dengan primary key tabel pasien).
-- =====================================================================

-- 1. Jadwal FPE
CREATE TABLE IF NOT EXISTS tbl_jadwal_fpe (
    id_jadwal INT AUTO_INCREMENT PRIMARY KEY,
    id_pasien INT NOT NULL,
    tanggal_pelaksanaan DATE NOT NULL,
    jam_pelaksanaan TIME NOT NULL,
    metode ENUM('video_call_wa','zoom_meeting') NOT NULL,
    meeting_id VARCHAR(30) DEFAULT NULL,
    passcode VARCHAR(30) DEFAULT NULL,
    slot_waktu ENUM('10.00-12.00','14.00-15.00') NOT NULL,
    status_kirim_wa TINYINT(1) NOT NULL DEFAULT 0,
    dibuat_oleh VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jadwal_pasien (id_pasien)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Dokumentasi FPE
CREATE TABLE IF NOT EXISTS tbl_dokumentasi_fpe (
    id_dokumentasi INT AUTO_INCREMENT PRIMARY KEY,
    id_jadwal INT DEFAULT NULL,
    id_pasien INT NOT NULL,
    asesmen TEXT,
    hubungan_dengan_pasien ENUM('ayah','ibu','suami','istri','anak','kakak','adik','kakek','nenek','lain_lain') DEFAULT NULL,
    hubungan_lainnya VARCHAR(100) DEFAULT NULL,
    hasil_fpe TEXT,
    kemampuan_pasien TEXT,
    kemampuan_keluarga TEXT,
    nama_ppa VARCHAR(100) DEFAULT NULL,
    tanda_tangan_ppa VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dok_pasien (id_pasien),
    CONSTRAINT fk_dok_jadwal FOREIGN KEY (id_jadwal) REFERENCES tbl_jadwal_fpe(id_jadwal) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3a. Master kegiatan (data tetap)
CREATE TABLE IF NOT EXISTS tbl_master_kegiatan (
    id_kegiatan INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIME NOT NULL,
    nama_kegiatan VARCHAR(100) NOT NULL,
    urutan INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_master_kegiatan (waktu, nama_kegiatan, urutan) VALUES
('06:00:00', 'Patuh Obat 5 Benar', 1),
('08:00:00', 'Menghardik', 2),
('08:30:00', 'Olah raga', 3),
('09:00:00', 'Ngobrol dengan teman/keluarga', 4),
('10:00:00', 'Nonton TV / kegiatan', 5),
('12:00:00', 'Patuh Obat 5 Benar', 6),
('13:00:00', 'Sholat / ibadah', 7),
('16:00:00', 'Mandi dan berhias', 8),
('16:30:00', 'Teknik relaksasi nafas dalam', 9),
('17:00:00', 'Merapikan TT', 10),
('18:00:00', 'Patuh Obat 5 Benar', 11);

-- 3b. Realisasi kegiatan harian pasien
CREATE TABLE IF NOT EXISTS tbl_kegiatan_pasien (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pasien INT NOT NULL,
    id_kegiatan INT NOT NULL,
    hari_ke TINYINT NOT NULL, -- 1 s/d 10, sesuai kolom I-X di form
    tanggal DATE NOT NULL,
    status_centang TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_kegiatan_pasien (id_pasien, id_kegiatan, tanggal),
    INDEX idx_kp_pasien (id_pasien),
    CONSTRAINT fk_kp_kegiatan FOREIGN KEY (id_kegiatan) REFERENCES tbl_master_kegiatan(id_kegiatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Skrining Risiko Bunuh Diri
CREATE TABLE IF NOT EXISTS tbl_skrining_risiko_bunuh_diri (
    id_skrining INT AUTO_INCREMENT PRIMARY KEY,
    id_pasien INT NOT NULL,
    tanggal_datang DATE NOT NULL,
    jam_datang TIME NOT NULL,
    status_pasien ENUM('lama','baru') NOT NULL,
    rujukan ENUM('ya','tidak') NOT NULL,
    rujukan_dari VARCHAR(100) DEFAULT NULL,
    disabilitas ENUM('ada','tidak_ada') NOT NULL,
    diagnosis VARCHAR(150) DEFAULT NULL,
    keluhan_saat_ini TEXT,
    pertanyaan_1 ENUM('ya','tidak','menyangkal','tidak_menjawab') DEFAULT NULL,
    pertanyaan_2 ENUM('ya','tidak','menyangkal','tidak_menjawab') DEFAULT NULL,
    pertanyaan_3 ENUM('ya','tidak','menyangkal','tidak_menjawab') DEFAULT NULL,
    pertanyaan_3a ENUM('dalam_24jam','dalam_bulan_terakhir','1_6bulan','lebih_6bulan','menyangkal','tidak_menjawab') DEFAULT NULL,
    hasil_skoring VARCHAR(100) DEFAULT NULL,
    lokasi ENUM('igd','poli') NOT NULL,
    nama_petugas_skrining VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_skrining_pasien (id_pasien)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
