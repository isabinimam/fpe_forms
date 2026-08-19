# MASTER AI IMPLEMENTATION PROMPT
## FPE Schedule Management + Automated WhatsApp Notification Queue
### Native PHP + Bootstrap + SQL Server + Node.js + Baileys + WhatsApp Cloud API

---

## 1. ROLE AND MISSION

You are a senior full-stack software engineer, backend architect, database engineer, DevOps engineer, and QA engineer.

Your task is to modify and extend the provided existing project into a fully runnable standalone local application for managing FPE schedules and automatically sending WhatsApp notifications to a family contact one day before the scheduled FPE date.

You must work from the existing project files rather than inventing an unrelated application.

The resulting application must be:

- Runnable locally on Windows.
- Based on native PHP for the web application.
- Styled using Bootstrap.
- Connected to Microsoft SQL Server.
- Configured for Windows Authentication / integrated authentication where practical and supported by the selected SQL Server driver.
- Powered by Node.js for background queue processing and WhatsApp delivery.
- Capable of real WhatsApp sending during local development/testing.
- Using Baileys as the development/test WhatsApp provider.
- Designed with a provider abstraction so that WhatsApp Cloud API / official WhatsApp Business Platform can be used later for production.
- Designed around a notification queue rather than a manual "already sent to WhatsApp" checkbox.
- UI text must remain in Indonesian.
- Source code, comments, technical documentation, architecture notes, variable names, and developer-facing explanations may be in English unless existing project conventions require otherwise.

Do not replace the project with a framework such as Laravel, Symfony, Express, NestJS, Next.js, or another full-stack framework unless explicitly required by an existing dependency. The web application must remain native PHP.

---

# 2. PRIMARY OBJECTIVE

Transform the current FPE scheduling application so that the business flow becomes:

```text
User opens FPE scheduling form
        ↓
User enters FPE schedule information
        ↓
User presses "Simpan Jadwal"
        ↓
PHP validates input
        ↓
PHP saves FPE schedule to SQL Server
        ↓
PHP automatically creates a WhatsApp notification queue record
        ↓
Node.js worker continuously checks due notification jobs
        ↓
When scheduled_at is reached
        ↓
Node.js claims the queue job safely
        ↓
WhatsApp provider sends notification
        ↓
Provider returns success/failure
        ↓
Database updates notification status
        ↓
UI can display current notification state
```

There must be NO manual checkbox that asks the user whether the WhatsApp message has already been sent.

The system itself determines the notification lifecycle.

---

# 3. IMPORTANT BUSINESS RULE

The core business rule is:

> When an FPE schedule is successfully saved, the system must automatically schedule a WhatsApp notification to the configured family contact for one day before the FPE date.

Example:

```text
FPE date:
2026-08-25

Notification date:
2026-08-24
```

The exact notification time must be configurable.

Do not hard-code business behavior in multiple places.

Prefer configuration such as:

```env
WA_NOTIFICATION_LEAD_DAYS=1
WA_NOTIFICATION_TIME=09:00
```

or an equivalent database/system setting if that is more appropriate.

If the scheduled date is already too close to the current date/time, the system must define deterministic behavior rather than silently creating an invalid past-due queue.

Use a clear policy such as:

- schedule immediately if `scheduled_at <= now`,
- or reject/save-with-warning,
- or create a due-now job.

Choose the safest and most operationally useful behavior, document it, and implement it consistently.

---

# 4. EXISTING PROJECT MUST BE INSPECTED FIRST

Before modifying anything:

1. Inspect the entire uploaded project.
2. Identify all PHP files.
3. Identify all SQL/schema files.
4. Identify configuration files.
5. Identify current database structure.
6. Identify current form submission flow.
7. Identify existing JavaScript/CSS.
8. Identify all existing references to:
   - WhatsApp
   - `status_kirim_wa`
   - `form_jadwal_fpe.php`
   - schedule records
   - patient/family contact information
9. Identify any preview-only or temporary files.
10. Identify dead code and obsolete files.
11. Preserve useful existing business logic whenever possible.

Do NOT blindly overwrite the project.

Before implementation, produce an internal dependency map of:

```text
UI
↓
PHP form/request handling
↓
Database access
↓
Database tables
↓
Existing business rules
```

Then design the smallest safe set of changes required to reach the target architecture.

---

# 5. FILE CLEANUP

The user explicitly requested that preview files be removed.

Perform cleanup carefully.

Rules:

- Remove files that are clearly preview-only, temporary, demo-only, or obsolete.
- Do not delete files merely because their purpose is unclear.
- Do not delete production logic accidentally.
- If a file is referenced by another file, inspect the dependency before removal.
- Remove obsolete WhatsApp checkbox UI and associated logic.
- Remove obsolete fields from database schema where applicable.
- Remove unused JavaScript/CSS only when confirmed safe.

After cleanup, make sure no broken references remain.

---

# 6. TECHNOLOGY STACK

## Web Application

- Native PHP
- Bootstrap 5.x
- Standard HTML5
- Vanilla JavaScript unless an existing project dependency justifies otherwise
- SQL Server database
- Responsive UI

## Backend Worker

- Node.js
- Modern LTS-compatible Node.js
- JavaScript or TypeScript only if the project benefits materially from it
- Prefer plain JavaScript for simplicity unless TypeScript significantly improves maintainability

## WhatsApp Development Provider

- Baileys
- Real WhatsApp account/session
- QR-based authentication or supported pairing flow
- Persistent authentication state

## Production Provider

- Official WhatsApp Business Platform / WhatsApp Cloud API
- Provider abstraction must make switching possible without rewriting queue logic

## Database

- Microsoft SQL Server
- Windows Authentication / integrated authentication
- Parameterized queries
- Proper indexes
- Transactional consistency for schedule + notification queue creation

---

# 7. DATABASE MIGRATION REQUIREMENT

The existing project contains a database schema that may use MySQL/MariaDB syntax.

Do NOT assume it can run directly on SQL Server.

Inspect and convert the schema to proper SQL Server syntax.

Examples of concepts that may require conversion:

```text
AUTO_INCREMENT
ENUM
TINYINT(1)
ENGINE=InnoDB
backtick identifiers
MySQL-specific defaults
MySQL-specific indexes
```

Convert them into appropriate SQL Server equivalents such as:

```text
IDENTITY
NVARCHAR / VARCHAR as appropriate
BIT
DATETIME2
CONSTRAINTS
SQL Server indexes
```

Do not perform a superficial syntax replacement.

Review:

- primary keys
- foreign keys
- indexes
- nullability
- default values
- unique constraints
- date/time data types
- text lengths
- Unicode requirements
- cascade behavior
- transaction semantics

---

# 8. REQUIRED DATABASE DESIGN

The system should use a normalized separation between:

1. FPE schedule data.
2. WhatsApp notification queue data.
3. Optional notification delivery/audit data.

At minimum, create or adapt tables equivalent to:

## FPE Schedule Table

Example conceptual structure:

```text
tbl_jadwal_fpe
------------------------------
id_jadwal
id_pasien
tanggal_pelaksanaan
jam_pelaksanaan
metode
meeting_id
passcode
slot_waktu
dibuat_oleh
created_at
updated_at
```

Use the existing project's actual naming conventions where possible.

## WhatsApp Notification Queue

Example conceptual structure:

```text
tbl_wa_queue
------------------------------
id
id_jadwal
nomor_tujuan
tipe_notifikasi
pesan
scheduled_at
status
attempts
max_attempts
locked_at
locked_by
sent_at
last_error
created_at
updated_at
```

You may introduce additional fields if needed for reliability.

Possible status values:

```text
pending
processing
sent
failed
cancelled
```

You may use lookup tables or constrained strings instead of database ENUM.

---

# 9. QUEUE DESIGN REQUIREMENTS

The queue is a first-class part of the system.

The worker must NOT simply scan all schedules and blindly send messages.

Instead:

```text
schedule
    ↓
notification job
    ↓
queue
    ↓
worker
    ↓
provider
```

Each WhatsApp notification must have an explicit lifecycle.

Recommended lifecycle:

```text
pending
   ↓
processing
   ↓
sent
```

Failure:

```text
processing
   ↓
failed
```

Retry:

```text
failed
   ↓
pending
   ↓
processing
```

The system must track:

- attempt count
- last error
- scheduled time
- actual sent time
- processing lock information
- provider/message identifier if available

---

# 10. ATOMIC SCHEDULE CREATION

When the user saves an FPE schedule:

```text
BEGIN TRANSACTION

INSERT schedule

INSERT WhatsApp notification queue

COMMIT
```

If either operation fails:

```text
ROLLBACK
```

Do not allow the schedule to exist successfully while its notification queue is missing, unless the system explicitly documents and handles eventual consistency.

The preferred implementation is atomic transaction-based creation.

---

# 11. DUPLICATE JOB PREVENTION

The system must prevent accidental duplicate WhatsApp notification jobs for the same schedule and notification type.

Example:

```text
id_jadwal = 123
tipe_notifikasi = FPE_REMINDER
```

should have a unique logical identity.

Enforce this at database level where practical.

Do not rely solely on application logic.

---

# 12. NODE.JS WORKER

Node.js must run as an independent process.

Conceptually:

```text
node worker.js
```

The worker should:

1. Connect to SQL Server.
2. Initialize the selected WhatsApp provider.
3. Ensure provider readiness.
4. Poll for due jobs.
5. Safely claim jobs.
6. Send messages.
7. Update queue state.
8. Retry failures according to policy.
9. Log useful operational information.
10. Recover gracefully after temporary errors.

---

# 13. WORKER POLLING

Do NOT implement scheduling entirely using long-lived `setTimeout()` calls based on individual jobs.

Instead use a polling worker.

Example conceptual behavior:

```text
Every 30-60 seconds:

SELECT due pending jobs
WHERE scheduled_at <= current time
AND status = pending
```

Then claim and process them.

Use a configurable interval:

```env
QUEUE_POLL_INTERVAL_MS=30000
```

The worker must recover if it was offline for several minutes or hours.

A due job must not be lost just because the worker was temporarily stopped.

---

# 14. SAFE JOB CLAIMING

The worker must avoid multiple worker instances sending the same message.

Use a database-level claim strategy.

Conceptually:

```text
pending
   ↓
atomically claim
   ↓
processing
```

Possible mechanisms:

- transaction
- row locking
- update-and-select pattern
- `UPDLOCK`
- `READPAST`
- lock token
- `locked_by`
- `locked_at`

Implement a robust strategy compatible with SQL Server.

Document the chosen locking strategy.

---

# 15. STALE JOB RECOVERY

If the Node.js process crashes after changing a job to:

```text
processing
```

but before completing the send, the job must not remain permanently stuck.

Implement stale processing recovery.

Example:

```env
QUEUE_PROCESSING_TIMEOUT_MINUTES=10
```

Jobs with:

```text
status = processing
AND locked_at < NOW - timeout
```

may be safely returned to:

```text
pending
```

provided this does not create an unacceptable duplicate-send risk.

Document the trade-off.

Where the provider supports idempotency or a provider-side message ID, use it.

---

# 16. RETRY POLICY

Implement controlled retries.

Example:

```env
WA_MAX_ATTEMPTS=3
```

Suggested behavior:

```text
attempt 1 → fail
attempt 2 → retry
attempt 3 → retry
then → failed permanently
```

Do not create infinite retries.

Use backoff where practical.

Example:

```text
1st retry: short delay
2nd retry: longer delay
```

Do not retry errors that are clearly permanent unless there is a strong reason.

---

# 17. WHATSAPP PROVIDER ABSTRACTION

This is mandatory.

Do not put Baileys-specific logic directly into queue scheduling code.

Create a provider interface or equivalent abstraction.

Conceptually:

```text
WhatsAppProvider
├── BaileysProvider
└── CloudApiProvider
```

Example conceptual API:

```javascript
connect()
disconnect()
isReady()
sendMessage({ to, message })
getStatus()
```

Additional methods may be added as needed.

Queue logic should know only about:

```text
provider.sendMessage(...)
```

and not about Baileys internals.

---

# 18. BAILEYS DEVELOPMENT MODE

Baileys is the default local testing provider.

Configuration:

```env
WHATSAPP_PROVIDER=baileys
```

Requirements:

- Persistent authentication state.
- QR login capability.
- Clear first-run instructions.
- Reconnection handling.
- Connection state logging.
- Authentication failure handling.
- WhatsApp disconnection handling.
- Graceful shutdown.
- Session persistence across Node.js restarts.

Do NOT require the developer to scan a QR code every time the worker starts if the Baileys session is already authenticated.

Store authentication state outside source-controlled code.

Example:

```text
node/auth_info/
```

Add to `.gitignore`.

---

# 19. IMPORTANT BAILEYS SAFETY / OPERATIONAL REQUIREMENT

Baileys is intended for development/testing in this project.

Do not implement:

- mass messaging
- bulk unsolicited messaging
- spam behavior
- aggressive retry loops
- scraping of unrelated WhatsApp data
- abusive automation

The application is intended for controlled notification of scheduled FPE events.

Implement reasonable rate limiting and one-job-at-a-time or conservative concurrency unless a stronger requirement exists.

---

# 20. OFFICIAL WHATSAPP CLOUD API

Implement the official provider as a separate provider.

Configuration should be environment-driven.

Conceptually:

```env
WHATSAPP_PROVIDER=cloud_api

WA_CLOUD_API_BASE_URL=...
WA_PHONE_NUMBER_ID=...
WA_ACCESS_TOKEN=...
WA_API_VERSION=...
```

Do not hard-code credentials.

Never store access tokens directly in PHP source, Node.js source, SQL files, or committed configuration.

Use environment variables or secure secret management.

---

# 21. PROVIDER-SPECIFIC MESSAGE LOGIC

The message body should be produced by a common application-level message generator.

Example conceptual input:

```text
patient/family name
FPE date
FPE time
method
meeting ID
passcode
instructions
```

Then:

```text
MessageTemplateService
        ↓
WhatsAppProvider
```

The Baileys provider and Cloud API provider should not produce contradictory message content.

For Cloud API, respect official WhatsApp Business messaging/template rules.

Where template messages are required, isolate the template configuration inside the Cloud API provider layer.

---

# 22. WHATSAPP MESSAGE CONTENT

The UI and message content must be in Indonesian.

Create a professional and concise reminder.

Example conceptual format:

```text
Pengingat Jadwal FPE

Yth. Keluarga [Nama],

Kami mengingatkan bahwa FPE untuk [Nama Pasien] dijadwalkan pada:

Tanggal: [Tanggal]
Waktu: [Jam]
Metode: [Metode]

[Meeting ID jika tersedia]
[Passcode jika tersedia]

Mohon memastikan kesiapan sebelum jadwal berlangsung.

Terima kasih.
```

Do not copy this blindly if the existing project contains an official message format.

Inspect the current project and preserve existing approved business wording where available.

---

# 23. PHONE NUMBER NORMALIZATION

Implement a dedicated phone-number normalization function.

Inputs may be:

```text
08123456789
+628123456789
628123456789
```

Normalize to the provider's expected canonical format.

For Indonesia, carefully handle:

```text
08...
+62...
62...
```

Do not blindly prepend `62` when it would create an invalid number.

Validate the final number.

Keep normalization logic centralized.

---

# 24. PHP DATABASE CONNECTION

Create a single reusable database configuration/connection layer.

Use:

- SQL Server driver supported by the environment.
- Parameterized queries.
- Exception/error handling.
- Connection reuse where practical.

The application must support Windows Authentication.

Do not hard-code:

```text
username
password
```

for SQL Server when integrated authentication is being used.

Allow configurable server/database names through environment/configuration.

Example conceptual configuration:

```env
DB_SERVER=localhost
DB_DATABASE=form_pfe
DB_TRUST_SERVER_CERTIFICATE=true
```

Adapt to the actual PHP SQL Server driver syntax.

---

# 25. DATABASE DRIVER VERIFICATION

Before assuming a driver exists, detect and document the required PHP extensions.

Potential environments may include:

```text
sqlsrv
pdo_sqlsrv
```

Use the most suitable option for the project's needs.

The implementation must explicitly document:

- required PHP version
- required SQL Server driver
- required Node.js version
- required npm packages
- required SQL Server version/features

---

# 26. FRONTEND REQUIREMENTS

The frontend must use Bootstrap.

All visible UI must be Indonesian.

Examples:

```text
Simpan Jadwal
Batal
Tanggal FPE
Jam FPE
Metode
Nomor WhatsApp Keluarga
Status Notifikasi
Riwayat Jadwal
Notifikasi Otomatis
Terkirim
Terjadwal
Gagal
Diproses
```

Do not expose English technical terms to the end user unless they are standard product names.

Technical names such as:

```text
Baileys
WhatsApp Cloud API
Meeting ID
```

may remain as product/technical names.

---

# 27. FORM DESIGN

Improve the existing form instead of creating an unrelated form.

The form should:

- use Bootstrap grid
- have proper labels
- have clear validation messages
- be mobile responsive
- preserve existing business fields
- group related fields logically
- avoid unnecessary visual clutter

Remove:

```text
"Saya sudah mengirim WhatsApp"
"Sudah dikirim ke WA keluarga"
```

or any equivalent manual notification flag.

Replace with informational UI:

```text
Notifikasi WhatsApp
Notifikasi akan dikirim secara otomatis H-1 sebelum tanggal FPE.
```

---

# 28. SAVE RESULT UI

After successful save, display:

```text
Jadwal berhasil disimpan.

Notifikasi WhatsApp:
Terjadwal

Tanggal pengiriman:
[date/time]
```

If saving the schedule succeeds but queue creation fails, do not silently report full success.

Show an appropriate error state and ensure transaction rollback where applicable.

---

# 29. NOTIFICATION STATUS UI

The application should expose notification status wherever the schedule is displayed.

Recommended statuses:

```text
Terjadwal
Sedang diproses
Terkirim
Gagal
Dibatalkan
```

Map backend status names to Indonesian UI labels.

Example:

```text
pending → Terjadwal
processing → Sedang diproses
sent → Terkirim
failed → Gagal
cancelled → Dibatalkan
```

---

# 30. TEST MODE

Implement a controlled test mode for end-to-end testing.

The user must NOT need to wait 24 hours to test H-1 behavior.

Possible configuration:

```env
APP_ENV=local
WA_TEST_MODE=true
```

Provide one or more safe testing mechanisms, such as:

### Option A
Override notification lead time:

```env
WA_NOTIFICATION_LEAD_DAYS=0
```

### Option B
Allow configurable test schedule.

### Option C
Allow an explicit:

```text
"Kirim Tes WhatsApp"
```

button restricted to local development.

Prefer a clean architecture where test mode does not corrupt production logic.

Do not scatter `if test mode` logic throughout the application.

---

# 31. REAL END-TO-END TEST

The project must support a test such as:

```text
1. Start SQL Server.
2. Start PHP application.
3. Start Node.js worker.
4. Authenticate Baileys.
5. Open FPE scheduling form.
6. Enter real test recipient number.
7. Save schedule.
8. Verify schedule record.
9. Verify queue record.
10. Verify scheduled_at.
11. Wait for due time or use test mode.
12. Worker claims job.
13. Baileys sends real WhatsApp.
14. Check recipient phone.
15. Verify queue status = sent.
16. Verify sent_at is populated.
17. Verify attempts count.
18. Verify logs.
```

This test must be documented.

---

# 32. TESTING REQUIREMENTS

Implement or document at minimum:

## Unit-level testing

Test:

- date calculation
- H-1 calculation
- phone normalization
- message generation
- status mapping
- retry decisions

## Integration testing

Test:

- PHP → SQL Server
- Node.js → SQL Server
- Node.js → Baileys
- queue claim behavior

## End-to-end testing

Test:

```text
Browser
→ PHP
→ SQL Server
→ queue
→ Node.js
→ WhatsApp
```

## Failure testing

Simulate:

- SQL Server unavailable
- wrong database configuration
- WhatsApp disconnected
- recipient invalid
- provider API failure
- process crash
- duplicate worker
- stale processing job
- duplicate save request

Document expected behavior.

---

# 33. LOGGING

Implement structured, useful logs.

Node.js logs should indicate:

```text
[INFO] Worker started
[INFO] Database connected
[INFO] WhatsApp provider initialized
[INFO] WhatsApp connected
[INFO] Queue polling
[INFO] Job claimed
[INFO] Sending WhatsApp
[INFO] WhatsApp sent
[ERROR] WhatsApp send failed
```

Do not log:

- access tokens
- passwords
- sensitive secrets
- unnecessary personal data

Mask phone numbers where appropriate in logs.

---

# 34. ENVIRONMENT CONFIGURATION

Create a safe example environment file:

```text
.env.example
```

It may contain placeholders but never real secrets.

Example conceptual configuration:

```env
APP_ENV=local

DB_SERVER=localhost
DB_DATABASE=form_pfe
DB_TRUST_SERVER_CERTIFICATE=true

WHATSAPP_PROVIDER=baileys

WA_NOTIFICATION_LEAD_DAYS=1
WA_NOTIFICATION_TIME=09:00

QUEUE_POLL_INTERVAL_MS=30000
QUEUE_PROCESSING_TIMEOUT_MINUTES=10

WA_MAX_ATTEMPTS=3

WA_TEST_MODE=true
```

For Cloud API:

```env
WA_CLOUD_API_BASE_URL=
WA_CLOUD_API_VERSION=
WA_PHONE_NUMBER_ID=
WA_ACCESS_TOKEN=
```

---

# 35. GITIGNORE / SECRET PROTECTION

Make sure these are excluded from version control:

```text
.env
.env.local
node/auth_info/
logs/
runtime/
temporary files
```

Do not commit:

- Baileys session credentials
- access tokens
- SQL credentials
- personal test credentials

---

# 36. SECURITY REQUIREMENTS

Apply basic production-quality security.

PHP:

- parameterized queries
- server-side validation
- output escaping
- CSRF protection if the architecture supports it
- session security where applicable
- no trust in client-side validation

Node.js:

- environment-based secrets
- no secrets in logs
- controlled retry behavior
- input validation before provider calls
- safe error handling

Do not expose raw SQL errors or stack traces to end users.

---

# 37. ERROR HANDLING PHILOSOPHY

Errors must be explicit and recoverable.

Bad:

```text
Something went wrong.
```

Prefer:

```text
Jadwal tidak dapat disimpan karena koneksi ke database gagal.
```

For administrators/developers, logs may contain technical diagnostics.

For end users, show understandable Indonesian messages.

---

# 38. API / COMMUNICATION BOUNDARY

The PHP frontend and Node.js worker do NOT need direct HTTP communication for basic queue processing.

The preferred architecture is:

```text
PHP
  ↓
SQL Server queue
  ↓
Node.js worker
```

This reduces coupling.

A Node.js HTTP API may be added only if there is a clear reason, for example:

- manual test send
- worker health endpoint
- administrative status endpoint

Do not introduce an API server unnecessarily.

---

# 39. OPTIONAL HEALTH CHECK

A small Node.js health endpoint is recommended.

Example:

```text
GET /health
```

Response:

```json
{
  "status": "ok",
  "database": true,
  "whatsapp": true
}
```

Do not expose secrets.

This endpoint can help determine whether the worker environment is healthy.

---

# 40. LOCAL DEVELOPMENT PROCESS

The system must be runnable locally without Docker unless Docker is already part of the provided project.

Preferred local services:

```text
SQL Server
Apache/PHP
Node.js
Browser
WhatsApp account
```

Document exact startup commands.

Example conceptual commands:

```bash
cd node
npm install
npm run start
```

and the PHP application can be served through:

```text
XAMPP / Apache
```

or PHP's built-in server if compatible.

The project must explain the recommended Windows setup.

---

# 41. WINDOWS-SPECIFIC REQUIREMENTS

Because the target local environment is Windows:

Document:

- PHP installation
- SQL Server installation/configuration
- SQL Server service
- Windows Authentication
- PHP SQL Server extension installation
- Node.js LTS installation
- npm installation
- firewall considerations if needed
- Baileys QR authentication
- starting both PHP and Node.js processes

Do not assume Linux-only commands.

When a command differs between Windows and Unix systems, prioritize Windows instructions.

---

# 42. DATABASE SETUP

Create a clean SQL Server setup script.

For example:

```text
database/sqlserver.sql
```

The script must:

1. Create required tables.
2. Create indexes.
3. Create constraints.
4. Create necessary defaults.
5. Be safe to execute in the intended environment.
6. Avoid destructive `DROP DATABASE` behavior unless explicitly documented.
7. Include comments where business behavior is not obvious.

Database name:

```text
form_pfe
```

Use the existing database naming requirement.

---

# 43. EXISTING SCHEMA MIGRATION

Do not lose existing data unnecessarily.

If the existing schema has useful data:

- preserve it,
- provide migration SQL,
- document whether the migration is destructive,
- provide backup instructions.

If the project is clearly still local/development-only and has no valuable production data, a clean rebuild may be acceptable, but state that explicitly.

---

# 44. TIMEZONE

The application is intended for Indonesia.

Use a consistent timezone strategy.

Preferred:

```text
Asia/Jakarta
```

Do not rely blindly on server-local timezone.

Document:

- PHP timezone
- Node.js timezone
- SQL Server datetime handling
- queue scheduling semantics

Choose one canonical representation and use it consistently.

---

# 45. DATE/TIME RULES

Do not perform date arithmetic inconsistently between PHP, Node.js, and SQL Server.

The H-1 calculation must produce the same result regardless of which layer calculates it.

Prefer having one authoritative scheduling rule.

Example:

```text
FPE date = 2026-08-25
lead days = 1
notification date = 2026-08-24
notification time = 09:00
```

Document the exact algorithm.

---

# 46. DATA VALIDATION

Server-side validation must cover:

- required fields
- valid date
- valid time
- valid schedule method
- valid phone number
- maximum field lengths
- optional fields
- meeting ID format where applicable
- passcode format where applicable

Do not rely solely on Bootstrap/browser validation.

---

# 47. IDEMPOTENCY / DOUBLE SUBMISSION

Protect against the user clicking:

```text
Simpan Jadwal
```

multiple times.

Use appropriate techniques such as:

- UI button disabling
- POST/Redirect/GET
- unique database constraints
- duplicate detection

The database must remain authoritative.

---

# 48. MANUAL TEST SEND

Provide a local-development-only test capability.

Possible UI:

```text
Kirim Tes WhatsApp
```

It must:

- be clearly labeled as testing
- require an explicit recipient
- not create a fake FPE notification record unless intended
- be disabled or restricted outside local/test mode
- use the same provider abstraction used by the queue

This is important for verifying Baileys independently of scheduling.

---

# 49. USER EXPERIENCE

The UI should make the automation obvious.

The user should NOT wonder:

> "Did I manually send the message?"

Instead they should see:

> "Notifikasi WhatsApp akan dikirim otomatis H-1."

This is the core UX change.

---

# 50. UI LANGUAGE POLICY

Strict requirement:

## User-facing interface:
INDONESIAN

Examples:

```text
Dashboard
→ Dasbor

Schedule
→ Jadwal

Save Schedule
→ Simpan Jadwal

Notification
→ Notifikasi

Queued
→ Terjadwal

Processing
→ Sedang Diproses

Sent
→ Terkirim

Failed
→ Gagal
```

## Technical source:
English is acceptable.

Do not mix languages unnecessarily in end-user UI.

---

# 51. BOOTSTRAP DESIGN QUALITY

Use Bootstrap components appropriately:

- cards
- forms
- badges
- alerts
- buttons
- responsive grid
- tables
- modal only where justified

Notification statuses should be visually distinguishable.

Do not over-design the system.

Prioritize:

```text
clear
professional
responsive
easy to operate
```

---

# 52. ACCESSIBILITY

Follow basic accessibility practices:

- proper `<label>` for inputs
- meaningful button labels
- sufficient contrast
- keyboard-friendly controls
- validation messages associated with fields
- semantic headings

Do not rely solely on color to indicate status.

---

# 53. CODE QUALITY

Write maintainable code.

Avoid:

- giant PHP files
- duplicated SQL
- hard-coded dates
- hard-coded phone numbers
- hard-coded credentials
- global mutable state where avoidable
- duplicated WhatsApp logic

Prefer separation of concerns:

```text
PHP:
Presentation / Request handling

Database:
Persistence

Node.js:
Background processing

Provider:
WhatsApp transport
```

---

# 54. PROJECT STRUCTURE

Use a structure similar to:

```text
fpe-local/
│
├── php/
│   ├── config/
│   │   └── database.php
│   ├── includes/
│   │   ├── auth.php
│   │   ├── helpers.php
│   │   └── ...
│   ├── form_jadwal_fpe.php
│   ├── ...
│   └── index.php
│
├── node/
│   ├── src/
│   │   ├── worker.js
│   │   ├── db/
│   │   ├── queue/
│   │   ├── whatsapp/
│   │   │   ├── WhatsAppProvider.js
│   │   │   ├── BaileysProvider.js
│   │   │   └── CloudApiProvider.js
│   │   ├── services/
│   │   └── utils/
│   ├── auth_info/
│   ├── package.json
│   └── .env.example
│
├── database/
│   └── sqlserver.sql
│
├── docs/
│   ├── architecture.md
│   ├── setup-windows.md
│   ├── testing.md
│   └── whatsapp-providers.md
│
├── .gitignore
└── README.md
```

Adapt this to the actual existing project rather than destroying the existing file organization without reason.

---

# 55. DOCUMENTATION REQUIREMENTS

Create/update:

```text
README.md
docs/architecture.md
docs/setup-windows.md
docs/testing.md
docs/whatsapp-providers.md
```

README must explain:

- project purpose
- architecture
- prerequisites
- database setup
- PHP setup
- Node.js setup
- Baileys authentication
- how to start PHP
- how to start Node.js
- how to test end-to-end
- how to switch to Cloud API
- common troubleshooting

---

# 56. ARCHITECTURE DOCUMENTATION

Explain this architecture:

```text
Browser
   ↓
Native PHP
   ↓
SQL Server
   ↓
Notification Queue
   ↓
Node.js Worker
   ↓
WhatsApp Provider
   ├── Baileys
   └── Cloud API
```

Also explain why the queue exists.

Explain:

- reliability
- retry
- recovery
- decoupling
- provider abstraction

---

# 57. IMPLEMENTATION ORDER

Execute implementation in this order unless the actual codebase requires a safer sequence:

### Step 1
Inspect existing project.

### Step 2
Map existing schema and application flow.

### Step 3
Remove confirmed obsolete preview/checkbox components.

### Step 4
Create SQL Server schema/migration.

### Step 5
Implement PHP SQL Server connection.

### Step 6
Refactor FPE schedule save flow.

### Step 7
Add atomic queue creation.

### Step 8
Build notification query/status layer.

### Step 9
Initialize Node.js project.

### Step 10
Implement SQL Server queue worker.

### Step 11
Implement provider abstraction.

### Step 12
Implement Baileys provider.

### Step 13
Implement test mode.

### Step 14
Implement Cloud API provider interface.

### Step 15
Upgrade Bootstrap UI.

### Step 16
Implement status display.

### Step 17
Create tests.

### Step 18
Run end-to-end tests.

### Step 19
Fix all discovered issues.

### Step 20
Write setup documentation.

---

# 58. DO NOT STOP AFTER WRITING CODE

After implementation, actually verify the application.

Do not claim:

```text
"Everything works"
```

unless verified.

At minimum verify:

```text
database connection
schedule insertion
queue insertion
queue due detection
queue claiming
WhatsApp provider initialization
actual WhatsApp test send
status update
retry behavior
restart recovery
```

If an external dependency cannot be tested because credentials or an external account are unavailable, state exactly what was and was not verified.

---

# 59. REAL WHATSAPP TESTING REQUIREMENT

The target is real delivery.

Do not replace WhatsApp sending with:

```text
console.log("message sent")
```

or a fake mock during the final local integration test.

Mocks may be used for unit tests.

But the end-to-end test must use:

```text
Baileys
→ real WhatsApp
→ real recipient
```

when the test account is configured.

---

# 60. TEST DATA

Create controlled test data.

Do not use real patient/family data from the project unless the project already contains intentional development data.

Prefer:

```text
TEST USER
TEST FAMILY
TEST PHONE
TEST SCHEDULE
```

The test data should be easy to remove.

---

# 61. FINAL ACCEPTANCE CRITERIA

The implementation is acceptable only when ALL relevant criteria below are satisfied:

### Application

- [ ] PHP application runs locally.
- [ ] Bootstrap UI works.
- [ ] UI is Indonesian.
- [ ] Existing FPE scheduling functionality remains usable.
- [ ] Manual WhatsApp checkbox is removed.

### Database

- [ ] SQL Server database works.
- [ ] Windows Authentication works.
- [ ] FPE schedule table works.
- [ ] WhatsApp queue table works.
- [ ] Indexes/constraints exist.
- [ ] Duplicate notification jobs are prevented.

### Scheduling

- [ ] Saving a schedule automatically creates notification job.
- [ ] H-1 calculation works.
- [ ] Notification time is configurable.
- [ ] Due jobs are detected automatically.

### Node.js

- [ ] Worker starts.
- [ ] Worker connects to SQL Server.
- [ ] Worker polls queue.
- [ ] Worker claims jobs safely.
- [ ] Worker updates status.
- [ ] Worker retries failures.
- [ ] Worker recovers stale jobs.

### Baileys

- [ ] QR authentication works.
- [ ] Session persists.
- [ ] Worker reconnects after disconnect.
- [ ] Real test message can be sent.
- [ ] Successful send updates queue state.

### Cloud API

- [ ] Provider abstraction exists.
- [ ] Cloud API provider exists or is clearly implemented behind configuration.
- [ ] Credentials are environment-based.
- [ ] Switching provider does not require changing scheduling logic.

### UX

- [ ] User sees "Notifikasi akan dikirim otomatis H-1".
- [ ] User sees notification status.
- [ ] Error messages are understandable.
- [ ] UI is responsive.

### Documentation

- [ ] Windows setup documented.
- [ ] SQL Server setup documented.
- [ ] Node.js setup documented.
- [ ] Baileys setup documented.
- [ ] Cloud API configuration documented.
- [ ] End-to-end test documented.

---

# 62. FAILURE REPORTING

At the end of implementation, provide an implementation report with exactly these sections:

## A. What Was Changed

Summarize actual files and changes.

## B. Database Changes

List:

- tables created/modified
- columns added/removed
- indexes
- constraints
- migrations

## C. Architecture

Explain:

```text
PHP → SQL Server → Queue → Node.js → Provider
```

## D. Testing Performed

List the tests actually executed.

For each test, report:

```text
PASS
FAIL
BLOCKED
```

Do not mark anything PASS without evidence.

## E. Remaining Limitations

State all unresolved issues.

## F. How to Run

Provide exact Windows commands.

## G. How to Perform a Real WhatsApp Test

Provide step-by-step instructions.

---

# 63. IMPORTANT ENGINEERING RULES

Follow these rules throughout implementation:

1. Do not invent requirements that conflict with this specification.
2. Do not replace native PHP with another backend framework.
3. Do not remove existing business logic without inspection.
4. Do not hard-code credentials.
5. Do not hard-code WhatsApp numbers.
6. Do not implement WhatsApp sending directly in PHP.
7. Do not make the frontend responsible for scheduling.
8. Do not use a checkbox as the notification source of truth.
9. Do not use an in-memory-only queue.
10. Do not rely solely on `setTimeout()` for queue reliability.
11. Do not lose queue jobs after process restart.
12. Do not silently swallow errors.
13. Do not claim tests passed when they were not run.
14. Do not use fake WhatsApp delivery in the final E2E test.
15. Keep production and development provider logic separated.
16. Keep user-facing UI Indonesian.
17. Keep technical documentation clear and reproducible.
18. Prefer simple, maintainable solutions over unnecessary abstractions.
19. Use database transactions where business consistency requires them.
20. Protect against duplicate processing.
21. Make configuration explicit.
22. Make the local setup reproducible from a clean machine.
23. Never expose secrets in logs, source code, or documentation.
24. Preserve the ability to replace Baileys with official WhatsApp Cloud API.
25. When uncertain about an existing business rule, inspect the existing implementation before changing behavior.

---

# 64. AGENT EXECUTION STYLE

Work iteratively.

For each major phase:

```text
Inspect
→ Modify
→ Validate
→ Test
→ Fix
→ Continue
```

Do not make a huge unverified rewrite in one step.

When a problem is discovered:

1. Identify root cause.
2. Fix root cause.
3. Re-test affected functionality.
4. Document important compatibility decisions.

Prefer evidence over assumptions.

---

# 65. START NOW

Begin by:

1. Inspecting the uploaded project files.
2. Identifying the current FPE scheduling flow.
3. Identifying the current SQL schema.
4. Identifying the current WhatsApp checkbox logic.
5. Identifying files that can safely be removed.
6. Producing the implementation plan based on the real codebase.
7. Then implementing the system phase by phase.

Do not begin by generating an unrelated greenfield project.

The final application must be a coherent evolution of the supplied project.

The desired end state is:

```text
            ┌────────────────────────────┐
            │        Browser/User        │
            └─────────────┬──────────────┘
                          │
                          ▼
            ┌────────────────────────────┐
            │    Native PHP + Bootstrap  │
            │     FPE Scheduling UI      │
            └─────────────┬──────────────┘
                          │
                          ▼
            ┌────────────────────────────┐
            │        SQL Server          │
            │       form_pfe             │
            │                            │
            │  FPE Schedule              │
            │  WhatsApp Queue            │
            └─────────────┬──────────────┘
                          │
                          ▼
            ┌────────────────────────────┐
            │     Node.js Worker         │
            │                            │
            │  Poll → Claim → Send       │
            │  Retry → Update Status     │
            └─────────────┬──────────────┘
                          │
                    Provider Layer
                    ┌─────┴─────┐
                    ▼           ▼
              ┌──────────┐ ┌──────────────┐
              │ Baileys  │ │ Cloud API    │
              │ Testing  │ │ Official     │
              └────┬─────┘ └──────┬───────┘
                   │               │
                   ▼               ▼
                WhatsApp        WhatsApp
```

The most important functional rule is:

> Saving an FPE schedule automatically creates a WhatsApp notification job for H-1. The user does not manually mark the notification as sent. Node.js is responsible for processing the queue and sending the actual WhatsApp message through the configured provider.

Build the implementation around this rule.