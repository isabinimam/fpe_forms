# FPE Schedule Management + Automated WhatsApp Notification Queue

Transform the existing RSKD Duren Sawit PHP forms project from a manual WhatsApp checkbox workflow to a fully automated notification queue system powered by Node.js + Baileys.

---

## Confirmed Environment

| Item | Value |
|------|-------|
| SQL Server Instance | `localhost\SQLEXPRESS` |
| Authentication | Windows Authentication |
| Database | `form_pfe` (already created, has dummy data) |
| PHP Driver | **Direct `sqlsrv_*`** functions (NOT PDO) |
| Test WhatsApp | `+6285159811407` |
| Project Type | **Standalone testing** — must be portable to main project |

---

## Portability Principle

> [!IMPORTANT]
> This is a **standalone testing project**. The 4 form components + Node.js worker must be designed so they can be **copied directly** into the boss's existing main project with minimal modification.
>
> **What this means for implementation:**
> - Each form PHP file remains a self-contained `include`-ready component (same pattern as current files)
> - Forms expect `$conn` (sqlsrv connection) and `$id_pasien` to be set by the parent page — just like current `$pdo` + `$id_pasien` pattern
> - A **dummy `tbl_pasien`** table and **standalone `index.php`** test harness exist only for testing — clearly separated and documented as "remove when integrating"
> - A dedicated **`docs/integration-guide.md`** documents exactly how to copy these components into an existing project
> - The Node.js worker is a completely independent process that only needs SQL Server access — no coupling to the PHP app

---

## Existing Project Analysis

### Current File Inventory

| File | Purpose | Action |
|------|---------|--------|
| [schema.sql](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/schema.sql) | MySQL schema — 5 tables | **Replace** with SQL Server version |
| [form_jadwal_fpe.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/form_jadwal_fpe.php) | FPE scheduling form + history | **Refactor** (remove checkbox, add queue, sqlsrv) |
| [form_dokumentasi_fpe.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/form_dokumentasi_fpe.php) | FPE documentation form | **Adapt** to sqlsrv |
| [form_kegiatan_pasien.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/form_kegiatan_pasien.php) | Patient activity checklist (10-day grid) | **Adapt** to sqlsrv |
| [form_skrining_bunuh_diri.php](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/form_skrining_bunuh_diri.php) | Suicide risk screening form | **Adapt** to sqlsrv |
| [README.md](file:///c:/Users/RSKD%20Duren%20Sawit/Downloads/php_forms/README.md) | Basic project docs | **Rewrite** |
| Master AI Implementation Prompt...md | Specification | **Remove** from project |

### Key Changes from PDO → sqlsrv

All 4 PHP forms currently use PDO. Every instance must be converted:

```text
PDO Pattern (current)              →  sqlsrv Pattern (new)
─────────────────────────────────     ──────────────────────────────
$pdo = new PDO(...)                →  $conn = sqlsrv_connect(...)
$stmt = $pdo->prepare("...")       →  $stmt = sqlsrv_prepare($conn, "...", $params)
$stmt->execute([':key' => $val])   →  sqlsrv_execute($stmt)
$stmt->fetchAll(PDO::FETCH_ASSOC)  →  while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
$pdo->beginTransaction()           →  sqlsrv_begin_transaction($conn)
$pdo->commit()                     →  sqlsrv_commit($conn)
$pdo->rollback()                   →  sqlsrv_rollback($conn)
NOW()                              →  SYSDATETIME()
ON DUPLICATE KEY UPDATE            →  MERGE ... WHEN MATCHED / WHEN NOT MATCHED
:named_params                      →  ? positional params with array
```

---

## Proposed Changes

### Component 1: Project Structure

```text
php_forms/
│
├── php/
│   ├── config/
│   │   ├── database.php            [NEW] sqlsrv connection to localhost\SQLEXPRESS
│   │   └── app.php                 [NEW] Env loader, timezone, constants
│   ├── includes/
│   │   ├── helpers.php             [NEW] Phone normalization, date calc, status labels
│   │   └── wa_queue.php            [NEW] Queue insertion helper
│   ├── form_jadwal_fpe.php         [MODIFY] Remove checkbox, add phone, atomic queue, sqlsrv
│   ├── form_dokumentasi_fpe.php    [MODIFY] PDO → sqlsrv
│   ├── form_kegiatan_pasien.php    [MODIFY] PDO → sqlsrv, MERGE instead of ON DUPLICATE KEY
│   ├── form_skrining_bunuh_diri.php [MODIFY] PDO → sqlsrv
│   └── index.php                   [NEW] ⚠️ TEST HARNESS ONLY — not for production
│
├── node/
│   ├── src/
│   │   ├── worker.js               [NEW] Main polling worker
│   │   ├── db.js                   [NEW] SQL Server connection (mssql package)
│   │   ├── queue.js                [NEW] Queue claim/update/recovery
│   │   ├── message-template.js     [NEW] Indonesian WhatsApp message builder
│   │   ├── phone.js                [NEW] Phone normalization (mirrors PHP helper)
│   │   └── whatsapp/
│   │       ├── WhatsAppProvider.js  [NEW] Base class / interface
│   │       ├── BaileysProvider.js   [NEW] Baileys implementation
│   │       └── CloudApiProvider.js  [NEW] Cloud API implementation
│   ├── tests/
│   │   └── unit.test.js            [NEW] Unit tests
│   ├── auth_info/                  [NEW] Baileys session (gitignored)
│   ├── package.json                [NEW]
│   └── .env.example                [NEW]
│
├── database/
│   └── sqlserver.sql               [NEW] Complete SQL Server schema (replaces schema.sql)
│
├── docs/
│   ├── architecture.md             [NEW] System architecture
│   ├── setup-windows.md            [NEW] Windows setup guide
│   ├── testing.md                  [NEW] Testing procedures
│   ├── whatsapp-providers.md       [NEW] Provider switching guide
│   └── integration-guide.md        [NEW] ⭐ How to copy into main project
│
├── schema.sql                      [KEEP] Original MySQL schema as reference
├── .env.example                    [NEW] PHP-side environment template
├── .gitignore                      [NEW]
└── README.md                       [MODIFY] Complete rewrite
```

---

### Component 2: SQL Server Schema

#### [NEW] `database/sqlserver.sql`

**Dummy patient table** (for standalone testing only):
```sql
-- ⚠️ TESTING ONLY — remove when integrating into main project
-- The main project already has its own patient table
CREATE TABLE tbl_pasien (
    id_pasien       INT IDENTITY(1,1) PRIMARY KEY,
    nama_pasien     NVARCHAR(100) NOT NULL,
    nama_keluarga   NVARCHAR(100) NULL,
    nomor_wa_keluarga NVARCHAR(20) NULL,
    created_at      DATETIME2 NOT NULL DEFAULT SYSDATETIME()
);

-- Seed test data
INSERT INTO tbl_pasien (nama_pasien, nama_keluarga, nomor_wa_keluarga)
VALUES (N'Pasien Uji Coba', N'Keluarga Uji Coba', N'6285159811407');
```

**Converted existing tables** (all MySQL → SQL Server):

- **`tbl_jadwal_fpe`**:
  - `INT IDENTITY` instead of `AUTO_INCREMENT`
  - `NVARCHAR(20)` with `CHECK` constraint instead of `ENUM('video_call_wa','zoom_meeting')`
  - `NVARCHAR(15)` with `CHECK` instead of `ENUM('10.00-12.00','14.00-15.00')`
  - **Remove** `status_kirim_wa` column
  - **Add** `nomor_wa_keluarga NVARCHAR(20) NOT NULL`
  - **Add** `nama_keluarga NVARCHAR(100) NULL` (for message template)
  - **Add** `updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME()`

- **`tbl_wa_queue`** [NEW]:
  - Full queue table as specified in original plan
  - Unique constraint `UQ_wa_queue_jadwal_tipe (id_jadwal, tipe_notifikasi)`
  - Index `IX_wa_queue_due (status, scheduled_at)` for efficient polling
  - FK to `tbl_jadwal_fpe`

- **`tbl_dokumentasi_fpe`**: Convert ENUM → NVARCHAR + CHECK, AUTO_INCREMENT → IDENTITY
- **`tbl_master_kegiatan`**: Convert + include seed data (11 activities)
- **`tbl_kegiatan_pasien`**: Convert, add unique constraint replacing MySQL `UNIQUE KEY`
- **`tbl_skrining_risiko_bunuh_diri`**: Convert all 7 ENUMs → NVARCHAR + CHECK constraints

---

### Component 3: PHP Database Layer

#### [NEW] `php/config/database.php`

```php
// Direct sqlsrv connection — Windows Authentication
$serverName = "localhost\\SQLEXPRESS";
$connectionOptions = [
    "Database"                => "form_pfe",
    "TrustServerCertificate"  => true,
    "CharacterSet"            => "UTF-8"
];
// No UID/PWD — Windows Auth
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die('Koneksi database gagal: ' . print_r(sqlsrv_errors(), true));
}
```

#### [NEW] `php/config/app.php`
- Simple `.env` file parser (no Composer)
- Sets `date_default_timezone_set('Asia/Jakarta')`
- Defines notification constants from env

#### [NEW] `php/includes/helpers.php`
- `normalizePhoneNumber($phone)` — `08xx`→`628xx`, `+628xx`→`628xx`, validation
- `calculateScheduledAt($fpeDate, $leadDays, $notifTime)` — H-1 calculation
- `waStatusLabel($status)` — `pending`→`Terjadwal`, `processing`→`Sedang Diproses`, etc.
- `waStatusBadgeClass($status)` — Bootstrap badge color mapping

#### [NEW] `php/includes/wa_queue.php`
- `createWaQueueJob($conn, $idJadwal, $nomorTujuan, $pesan, $scheduledAt)` — sqlsrv INSERT into `tbl_wa_queue`
- `buildFpeReminderMessage($data)` — builds the Indonesian message text

---

### Component 4: FPE Schedule Form Refactor

#### [MODIFY] `form_jadwal_fpe.php`

**Contract change** (for portability):
```php
// OLD: expects $pdo (PDO object)
// NEW: expects $conn (sqlsrv resource)
// Still expects: $id_pasien, $nama_petugas
// Still: include-ready, no header/footer
```

**Removals:**
- `status_kirim_wa` from INSERT statement and params
- Checkbox UI: "Sudah dikirim ke WA keluarga" (lines 126-131)
- History table column: "Status Kirim WA" + Sudah/Belum badges (lines 171, 182-188)

**Additions:**
- New fields: `Nomor WhatsApp Keluarga` (required, tel input), `Nama Keluarga` (required, text)
- Info banner: *"Notifikasi WhatsApp akan dikirim secara otomatis H-1 sebelum tanggal FPE."*
- **Atomic transaction**:
  ```text
  sqlsrv_begin_transaction($conn)
  → INSERT tbl_jadwal_fpe → get id_jadwal
  → Calculate H-1 scheduled_at
  → INSERT tbl_wa_queue
  → sqlsrv_commit($conn) or sqlsrv_rollback($conn)
  ```
- **Past-due policy**: if `scheduled_at <= now`, create job as due-now (worker picks up immediately)
- Success message: *"Jadwal berhasil disimpan. Notifikasi WhatsApp: Terjadwal — Pengiriman: [date]"*
- History table: new column `Status Notifikasi` with colored badge (JOIN to `tbl_wa_queue`)
- Double-submit protection: JS button disable on submit

**Preserved:**
- All business fields: tanggal_pelaksanaan, jam_pelaksanaan, metode, meeting_id, passcode, slot_waktu
- Zoom toggle JS (`fpeToggleZoom()`)
- Card layout, Bootstrap structure
- Validation logic (enhanced for new fields)

---

### Component 5: Other PHP Forms — PDO → sqlsrv

#### [MODIFY] `form_dokumentasi_fpe.php`
- Replace `$pdo->prepare()` → `sqlsrv_prepare($conn, ...)`
- Replace `$stmt->execute()` → `sqlsrv_execute($stmt)`
- Replace `$stmt->fetchAll(PDO::FETCH_ASSOC)` → loop with `sqlsrv_fetch_array()`
- Replace `NOW()` → `SYSDATETIME()`
- Contract: `$conn` instead of `$pdo`

#### [MODIFY] `form_kegiatan_pasien.php`
- Same sqlsrv conversion as above
- Replace `ON DUPLICATE KEY UPDATE` → SQL Server `MERGE` statement
- Contract: `$conn` instead of `$pdo`

#### [MODIFY] `form_skrining_bunuh_diri.php`
- Same sqlsrv conversion
- Contract: `$conn` instead of `$pdo`

---

### Component 6: Standalone Test Harness

#### [NEW] `php/index.php`

> [!WARNING]
> This file is for **standalone testing only**. It will NOT be copied to the main project. The main project has its own layout, authentication, and routing.

- Full HTML page with Bootstrap 5 CDN
- Creates `$conn` via `config/database.php`
- Patient selector dropdown (from `tbl_pasien` dummy table)
- Tabbed interface showing all 4 forms
- "Kirim Tes WhatsApp" button (only when `WA_TEST_MODE=true`)
- Queue status dashboard showing all pending/sent/failed notifications

---

### Component 7: Node.js Worker

*(Unchanged from original plan — fully independent of PHP, only needs SQL Server)*

#### [NEW] `node/src/worker.js` — Main entry, polling loop
#### [NEW] `node/src/db.js` — SQL Server connection via `mssql` package

```javascript
// Windows Authentication to localhost\SQLEXPRESS
const config = {
    server: 'localhost',
    options: {
        instanceName: 'SQLEXPRESS',
        database: 'form_pfe',
        trustServerCertificate: true
    },
    authentication: {
        type: 'ntlm',
        options: {
            domain: '',  // local machine
            userName: '',
            password: ''
        }
    }
};
```

#### [NEW] `node/src/queue.js` — Job claiming with `UPDLOCK, READPAST`, status updates, stale recovery
#### [NEW] `node/src/message-template.js` — Indonesian WhatsApp message builder
#### [NEW] `node/src/phone.js` — Phone normalization (mirrors PHP-side logic)

---

### Component 8: WhatsApp Providers

#### [NEW] `node/src/whatsapp/WhatsAppProvider.js` — Base class
#### [NEW] `node/src/whatsapp/BaileysProvider.js` — Development provider
- QR code in terminal
- Persistent auth in `node/auth_info/`
- Auto-reconnect
- Rate limiting (one message at a time)

#### [NEW] `node/src/whatsapp/CloudApiProvider.js` — Production provider
- Environment-driven credentials
- Functional implementation using `fetch`
- Requires real API credentials to test

---

### Component 9: Configuration & Security

#### [NEW] `.env.example` (project root)
```env
APP_ENV=local
DB_SERVER=localhost\SQLEXPRESS
DB_DATABASE=form_pfe
DB_TRUST_SERVER_CERTIFICATE=true
WA_NOTIFICATION_LEAD_DAYS=1
WA_NOTIFICATION_TIME=09:00
WA_TEST_MODE=true
WA_TEST_PHONE=6285159811407
```

#### [NEW] `node/.env.example`
```env
DB_SERVER=localhost
DB_INSTANCE=SQLEXPRESS
DB_DATABASE=form_pfe
WHATSAPP_PROVIDER=baileys
QUEUE_POLL_INTERVAL_MS=30000
QUEUE_PROCESSING_TIMEOUT_MINUTES=10
WA_MAX_ATTEMPTS=3
WA_NOTIFICATION_LEAD_DAYS=1
WA_NOTIFICATION_TIME=09:00
HEALTH_PORT=3001
TZ=Asia/Jakarta
```

#### [NEW] `.gitignore`

---

### Component 10: Documentation

#### [MODIFY] `README.md` — Full rewrite
#### [NEW] `docs/architecture.md` — System diagram and explanation
#### [NEW] `docs/setup-windows.md` — Detailed Windows setup for SQL Server Express, PHP sqlsrv extension, Node.js
#### [NEW] `docs/testing.md` — Unit tests, integration tests, E2E WhatsApp test procedure
#### [NEW] `docs/whatsapp-providers.md` — How to switch between Baileys and Cloud API

#### [NEW] `docs/integration-guide.md` ⭐

> This is the key document for transferring to the main project.

Contents:
1. **Prerequisites** — what the main project needs (sqlsrv extension, SQL Server, Node.js)
2. **Database migration** — which tables to add to the existing database (run specific parts of `sqlserver.sql`, skip `tbl_pasien` dummy table)
3. **Files to copy** — exact list:
   - `php/config/database.php` → adapt to existing connection
   - `php/includes/helpers.php` → copy as-is
   - `php/includes/wa_queue.php` → copy as-is
   - `php/form_jadwal_fpe.php` → copy, set `$conn` from existing connection
   - `php/form_dokumentasi_fpe.php` → copy, set `$conn`
   - `php/form_kegiatan_pasien.php` → copy, set `$conn`
   - `php/form_skrining_bunuh_diri.php` → copy, set `$conn`
   - `node/` entire directory → copy as independent service
4. **Files NOT to copy** — `php/index.php` (test harness), `tbl_pasien` dummy table
5. **Integration steps** — how to include each form in existing pages
6. **Variable contract** — what each form expects (`$conn`, `$id_pasien`, `$nama_petugas`)
7. **Node.js deployment** — how to run the worker alongside the main app
8. **Environment config** — what `.env` values to set

---

## Implementation Order

| Step | Task | Depends On |
|------|------|-----------|
| 1 | Create `database/sqlserver.sql` (full SQL Server schema) | — |
| 2 | Create `php/config/database.php` + `app.php` | Step 1 |
| 3 | Create `php/includes/helpers.php` + `wa_queue.php` | Step 2 |
| 4 | Refactor `form_jadwal_fpe.php` (PDO→sqlsrv, remove checkbox, add queue) | Steps 2-3 |
| 5 | Adapt `form_dokumentasi_fpe.php` (PDO→sqlsrv) | Step 2 |
| 6 | Adapt `form_kegiatan_pasien.php` (PDO→sqlsrv, MERGE) | Step 2 |
| 7 | Adapt `form_skrining_bunuh_diri.php` (PDO→sqlsrv) | Step 2 |
| 8 | Create `php/index.php` test harness | Steps 4-7 |
| 9 | Create Node.js project (`package.json`, `db.js`, `queue.js`) | Step 1 |
| 10 | Create WhatsApp provider abstraction + BaileysProvider | Step 9 |
| 11 | Create CloudApiProvider stub | Step 10 |
| 12 | Create `worker.js` (polling, claiming, sending) | Steps 9-11 |
| 13 | Create `message-template.js` + `phone.js` | Step 9 |
| 14 | Create `.env.example` files, `.gitignore` | — |
| 15 | Create unit tests | Steps 3, 13 |
| 16 | Rewrite `README.md` | All |
| 17 | Write `docs/` (architecture, setup, testing, providers, **integration-guide**) | All |
| 18 | Execute schema on SQL Server Express | Step 1 |
| 19 | Verify PHP forms load and save | Steps 4-8, 18 |
| 20 | Verify Node.js worker + Baileys E2E | Steps 12, 18 |

---

## Verification Plan

### Automated
```bash
# PHP syntax check — all files
php -l php/config/database.php
php -l php/config/app.php
php -l php/includes/helpers.php
php -l php/includes/wa_queue.php
php -l php/form_jadwal_fpe.php
php -l php/form_dokumentasi_fpe.php
php -l php/form_kegiatan_pasien.php
php -l php/form_skrining_bunuh_diri.php
php -l php/index.php

# Node.js unit tests
cd node && npm test
```

### Manual — PHP
1. Execute `database/sqlserver.sql` against `localhost\SQLEXPRESS` → `form_pfe`
2. Start PHP: `php -S localhost:8080 -t php/`
3. Open `http://localhost:8080` → verify all 4 forms render
4. Save FPE schedule → verify both `tbl_jadwal_fpe` and `tbl_wa_queue` rows created
5. Check history table shows notification status badge

### Manual — Node.js E2E WhatsApp
```text
1. cd node && npm install
2. Copy .env.example → .env, configure
3. npm start
4. Scan QR code with WhatsApp (first time)
5. Save a schedule via PHP form with WA_NOTIFICATION_LEAD_DAYS=0
6. Worker detects due job → claims → sends via Baileys
7. Check phone +6285159811407 for received message
8. Verify tbl_wa_queue.status = 'sent'
```
