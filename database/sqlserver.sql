-- =====================================================================
-- SKEMA DATABASE SQL SERVER - FORMULIR PSIKOEDUKASI (FPE) & WA QUEUE
-- RSKD Duren Sawit
-- Database target: form_pfe
-- =====================================================================

-- 0. TABEL DUMMY PASIEN (HANYA UNTUK TESTING STANDALONE)
-- ⚠️ Catatan Portabilitas: Jangan jalankan bagian ini saat integrasi ke main project
-- karena main project sudah memiliki tabel pasien sendiri.
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_pasien]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_pasien] (
        [id_pasien]     INT IDENTITY(1,1) PRIMARY KEY,
        [nama_pasien]   NVARCHAR(100) NOT NULL,
        [tanggal_lahir] DATE NULL,
        [created_at]    DATETIME2 NOT NULL DEFAULT SYSDATETIME()
    );

    SET IDENTITY_INSERT [dbo].[tbl_pasien] ON;
    INSERT INTO [dbo].[tbl_pasien] ([id_pasien], [nama_pasien], [tanggal_lahir])
    VALUES 
        (123, N'Ny. Aisyah (Pasien Rawat Inap)', '1985-04-12'),
        (1, N'Budi Santoso (Test Pasien)', '1992-08-15'),
        (2, N'Dewi Sartika (Test Pasien)', '1998-11-20');
    SET IDENTITY_INSERT [dbo].[tbl_pasien] OFF;
END;
GO

-- 1. TABEL JADWAL FPE
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_jadwal_fpe]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_jadwal_fpe] (
        [id_jadwal]           INT IDENTITY(1,1) PRIMARY KEY,
        [id_pasien]           INT NOT NULL,
        [tanggal_pelaksanaan] DATE NOT NULL,
        [jam_pelaksanaan]     TIME NOT NULL,
        [metode]              NVARCHAR(20) NOT NULL CONSTRAINT CHK_jadwal_metode CHECK ([metode] IN ('video_call_wa', 'zoom_meeting')),
        [meeting_id]          NVARCHAR(30) NULL,
        [passcode]            NVARCHAR(30) NULL,
        [slot_waktu]          NVARCHAR(15) NOT NULL CONSTRAINT CHK_jadwal_slot CHECK ([slot_waktu] IN ('10.00-12.00', '14.00-15.00')),
        [nomor_wa]            NVARCHAR(20) NOT NULL,
        [nama_keluarga]       NVARCHAR(100) NULL,
        [dibuat_oleh]         NVARCHAR(100) NULL,
        [created_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        [updated_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME()
    );

    CREATE INDEX [idx_jadwal_pasien] ON [dbo].[tbl_jadwal_fpe] ([id_pasien]);
END;
GO

-- 2. TABEL ANTREAN NOTIFIKASI WHATSAPP (QUEUE)
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_wa_queue]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_wa_queue] (
        [id]                  INT IDENTITY(1,1) PRIMARY KEY,
        [id_jadwal]           INT NOT NULL,
        [nomor_tujuan]        NVARCHAR(20) NOT NULL,
        [tipe_notifikasi]     NVARCHAR(30) NOT NULL DEFAULT 'FPE_REMINDER',
        [pesan]               NVARCHAR(MAX) NULL,
        [scheduled_at]        DATETIME2 NOT NULL,
        [status]              NVARCHAR(20) NOT NULL DEFAULT 'pending' 
                              CONSTRAINT CHK_wa_queue_status CHECK ([status] IN ('pending', 'processing', 'sent', 'failed', 'cancelled')),
        [attempts]            INT NOT NULL DEFAULT 0,
        [max_attempts]        INT NOT NULL DEFAULT 3,
        [locked_at]           DATETIME2 NULL,
        [locked_by]           NVARCHAR(100) NULL,
        [sent_at]             DATETIME2 NULL,
        [provider_message_id] NVARCHAR(255) NULL,
        [last_error]          NVARCHAR(MAX) NULL,
        [created_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        [updated_at]          DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        CONSTRAINT [UQ_wa_queue_jadwal_tipe] UNIQUE ([id_jadwal], [tipe_notifikasi]),
        CONSTRAINT [FK_wa_queue_jadwal] FOREIGN KEY ([id_jadwal]) REFERENCES [dbo].[tbl_jadwal_fpe]([id_jadwal]) ON DELETE CASCADE
    );

    CREATE INDEX [IX_wa_queue_due] ON [dbo].[tbl_wa_queue] ([status], [scheduled_at]);
END;
GO

-- 3. TABEL DOKUMENTASI FPE
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_dokumentasi_fpe]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_dokumentasi_fpe] (
        [id_dokumentasi]        INT IDENTITY(1,1) PRIMARY KEY,
        [id_jadwal]             INT NULL,
        [id_pasien]             INT NOT NULL,
        [asesmen]               NVARCHAR(MAX) NULL,
        [hubungan_dengan_pasien] NVARCHAR(255) NULL, -- Multiple choice keluarga yang hadir (misal: 'ayah,ibu')
        [hubungan_lainnya]      NVARCHAR(100) NULL,
        [hasil_fpe]             NVARCHAR(MAX) NULL,
        [kemampuan_pasien]      NVARCHAR(MAX) NULL,
        [kemampuan_keluarga]    NVARCHAR(MAX) NULL,
        [nama_ppa]              NVARCHAR(100) NULL,
        [tanda_tangan_ppa]      NVARCHAR(255) NULL,
        [created_at]            DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        CONSTRAINT [FK_dok_jadwal] FOREIGN KEY ([id_jadwal]) REFERENCES [dbo].[tbl_jadwal_fpe]([id_jadwal]) ON DELETE SET NULL
    );

    CREATE INDEX [idx_dok_pasien] ON [dbo].[tbl_dokumentasi_fpe] ([id_pasien]);
END;
GO

-- 4. MASTER KEGIATAN HARIAN
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_master_kegiatan]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_master_kegiatan] (
        [id_kegiatan]   INT IDENTITY(1,1) PRIMARY KEY,
        [waktu]         TIME NOT NULL,
        [nama_kegiatan] NVARCHAR(100) NOT NULL,
        [urutan]        INT NOT NULL DEFAULT 0
    );

    INSERT INTO [dbo].[tbl_master_kegiatan] ([waktu], [nama_kegiatan], [urutan]) VALUES
    ('06:00:00', N'Patuh Obat 5 Benar', 1),
    ('08:00:00', N'Menghardik', 2),
    ('08:30:00', N'Olah raga', 3),
    ('09:00:00', N'Ngobrol dengan teman/keluarga', 4),
    ('10:00:00', N'Nonton TV / kegiatan', 5),
    ('12:00:00', N'Patuh Obat 5 Benar', 6),
    ('13:00:00', N'Sholat / ibadah', 7),
    ('16:00:00', N'Mandi dan berhias', 8),
    ('16:30:00', N'Teknik relaksasi nafas dalam', 9),
    ('17:00:00', N'Merapikan TT', 10),
    ('18:00:00', N'Patuh Obat 5 Benar', 11);
END;
GO

-- 5. REALISASI KEGIATAN HARIAN PASIEN
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_kegiatan_pasien]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_kegiatan_pasien] (
        [id]             INT IDENTITY(1,1) PRIMARY KEY,
        [id_pasien]      INT NOT NULL,
        [id_kegiatan]    INT NOT NULL,
        [hari_ke]        TINYINT NOT NULL,
        [tanggal]        DATE NOT NULL,
        [status_centang] BIT NOT NULL DEFAULT 0,
        CONSTRAINT [UQ_kegiatan_pasien] UNIQUE ([id_pasien], [id_kegiatan], [tanggal]),
        CONSTRAINT [FK_kp_kegiatan] FOREIGN KEY ([id_kegiatan]) REFERENCES [dbo].[tbl_master_kegiatan]([id_kegiatan])
    );

    CREATE INDEX [idx_kp_pasien] ON [dbo].[tbl_kegiatan_pasien] ([id_pasien]);
END;
GO

-- 6. SKRINING RISIKO BUNUH DIRI
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[tbl_skrining_risiko_bunuh_diri]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[tbl_skrining_risiko_bunuh_diri] (
        [id_skrining]           INT IDENTITY(1,1) PRIMARY KEY,
        [id_pasien]             INT NOT NULL,
        [tanggal_datang]        DATE NOT NULL,
        [jam_datang]            TIME NOT NULL,
        [status_pasien]         NVARCHAR(20) NOT NULL CONSTRAINT CHK_skr_status CHECK ([status_pasien] IN ('lama', 'baru')),
        [rujukan]               NVARCHAR(10) NOT NULL CONSTRAINT CHK_skr_rujukan CHECK ([rujukan] IN ('ya', 'tidak')),
        [rujukan_dari]          NVARCHAR(100) NULL,
        [disabilitas]           NVARCHAR(20) NOT NULL CONSTRAINT CHK_skr_disabilitas CHECK ([disabilitas] IN ('ada', 'tidak_ada')),
        [diagnosis]             NVARCHAR(150) NULL,
        [keluhan_saat_ini]      NVARCHAR(MAX) NULL,
        [pertanyaan_1]          NVARCHAR(20) NULL CONSTRAINT CHK_skr_p1 CHECK ([pertanyaan_1] IN ('ya','tidak','menyangkal','tidak_menjawab')),
        [pertanyaan_2]          NVARCHAR(20) NULL CONSTRAINT CHK_skr_p2 CHECK ([pertanyaan_2] IN ('ya','tidak','menyangkal','tidak_menjawab')),
        [pertanyaan_3]          NVARCHAR(20) NULL CONSTRAINT CHK_skr_p3 CHECK ([pertanyaan_3] IN ('ya','tidak','menyangkal','tidak_menjawab')),
        [pertanyaan_3a]         NVARCHAR(30) NULL CONSTRAINT CHK_skr_p3a CHECK ([pertanyaan_3a] IN ('dalam_24jam','dalam_bulan_terakhir','1_6bulan','lebih_6bulan','menyangkal','tidak_menjawab')),
        [hasil_skoring]         NVARCHAR(100) NULL,
        [lokasi]                NVARCHAR(20) NOT NULL DEFAULT 'poli' CONSTRAINT CHK_skr_lokasi CHECK ([lokasi] IN ('igd', 'poli')),
        [nama_petugas_skrining] NVARCHAR(100) NULL,
        [created_at]            DATETIME2 NOT NULL DEFAULT SYSDATETIME()
    );

    CREATE INDEX [idx_skrining_pasien] ON [dbo].[tbl_skrining_risiko_bunuh_diri] ([id_pasien]);
END;
GO
