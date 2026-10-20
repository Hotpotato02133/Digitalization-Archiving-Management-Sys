# DATS — Database Reference

Source of truth: `dats.sql` (phpMyAdmin dump, MariaDB 10.4.32, PHP 8.2.12 target). Engine: **InnoDB**, charset **utf8mb4**, collation **utf8mb4_general_ci** (JSON column uses `utf8mb4_bin`). Connection layer: `config/database.php` via **PDO** (see `backend.md`).

## Connecting

`dats_db()` in `config/database.php` opens a single lazily-cached PDO connection:

```
mysql:host=<DATS_DB_HOST>;dbname=<DATS_DB_NAME>;charset=utf8mb4
```

Env vars (with defaults), read via `dats_env()` (checks `getenv()`, then `$_ENV`/`$_SERVER`):

| Var | Default |
|---|---|
| `DATS_DB_HOST` | `127.0.0.1` |
| `DATS_DB_NAME` | `dats` |
| `DATS_DB_USER` | `root` |
| `DATS_DB_PASSWORD` | `` (empty) |

PDO attributes: `ERRMODE_EXCEPTION`, `FETCH_ASSOC` default, **`EMULATE_PREPARES => false`** (forces real server-side prepared statements — hardens against SQL injection edge cases). Connection failures are swallowed to `null` (caller must check); `api.php` responds `503` if `dats_db()` returns null.

No migration framework — `dats.sql` is imported directly (e.g. via phpMyAdmin/XAMPP). Two tables self-heal their schema at runtime instead of via migrations:
- `documents` — `ensure_document_ocr_columns()` in `api.php` runs `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` for the OCR/AI columns on **every API request**.
- `enrollment_years` — `ensure_enrollment_years_table()` in `api.php` runs `CREATE TABLE IF NOT EXISTS` + a best-effort FK add, called on demand.
- `enrollment_years` — `ensure_enrollment_years_table()` creates the table, migrates a legacy `YEAR` column to `SMALLINT UNSIGNED`, and best-effort adds the FK on demand.

## Entity overview

```
users ──┬───────────────< documents >──── document_categories
        │                    │  │
        │                    │  └──< document_reviews (1:1 per document)
        │                    │
        │              students >── colleges ──< courses
        │
        ├──< activity_logs
        ├──< notifications (recipient_id / actor_id, both -> users)
        └──< enrollment_years (created_by)
```

## Tables

### `users`
Auth/identity table. There is no role column — every account has identical access (the Staff role and this table's `role` ENUM were removed; see `backend.md`).

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `full_name` | VARCHAR(120) | |
| `email` | VARCHAR(190) | UNIQUE |
| `password_hash` | VARCHAR(255) | bcrypt via `password_hash()` |
| `status` | ENUM('Active','Inactive') | default Active; inactive = soft-disabled |
| `last_login_at` | DATETIME NULL | set on successful login |
| `created_at` / `updated_at` | DATETIME | auto timestamps |

Index: `idx_users_status (status)`. Login always filters `status = 'Active'`.

### `colleges`
| Column | Notes |
|---|---|
| `id`, `name` (UNIQUE), `short_name`, `logo_path`, `status` ENUM('Active','Inactive'), timestamps | Logos stored under `uploads/colleges/`. |

Deleting a college (`delete_college()`) is a transaction: nulls `students.course_id` for courses under it, nulls `students.college_id`, deletes its `courses`, then deletes the college row — a **hard delete**, not a soft `status='Inactive'` toggle (inconsistent with the `status` column's apparent purpose).

### `courses`
| Column | Notes |
|---|---|
| `id`, `college_id` (FK -> colleges, ON UPDATE CASCADE), `name`, `short_name`, `status`, timestamps | UNIQUE `(college_id, name)` |

Deleting a course nulls `students.course_id` first, then hard-deletes.

### `students`
Registrar's student directory.

| Column | Notes |
|---|---|
| `id` | PK |
| `student_number` | UNIQUE |
| `first_name`, `middle_initial`, `last_name` | indexed `(last_name, first_name)` for sorting |
| `gender` | ENUM Female/Male/Other/Undisclosed |
| `birthdate`, `address`, `civil_status`, `contact_number` | |
| `college_id`, `course_id` | FK, `ON DELETE SET NULL` |
| `academic_year` | YEAR(4), nullable |
| `year_level` | ENUM 1st–5th Year / Graduate / Undisclosed |
| `status` | ENUM Active/Inactive/Graduated |

`vw_archive_analytics.total_students` counts everyone with `status <> 'Inactive'` (Graduated students still count).

### `document_categories`
Registrar document types (TOR, COR, COE, COG, GMC, DIP, ACR, OTH, plus custom ones like `MC`, `INC FORM`).

| Column | Notes |
|---|---|
| `code` | UNIQUE, short (e.g. `TOR`), uppercased on write |
| `name` | UNIQUE |
| `description` | required |
| `status` | Active/Inactive — soft delete; `update_category()` only touches rows where `status='Active'` |
| `created_by` | FK -> users, `ON DELETE SET NULL` |

Any signed-in user can create/edit categories via `admin/categories.php` — there's no role gate on this (or any other) resource (see `backend.md` §Security).

### `documents`
Core record — one row per uploaded/scanned file.

| Column | Notes |
|---|---|
| `id` | PK |
| `student_id` | FK -> students |
| `category_id` | FK -> document_categories |
| `uploaded_by` | FK -> users |
| `original_filename` | as provided by the client |
| `stored_filename`, `file_path` | on-disk name/path; **NULL after rejection** (file deleted) |
| `mime_type`, `file_size_bytes` | |
| `academic_year` | YEAR(4) — always normalized to a single valid year or `NULL` by `normalize_academic_year()`/`create_document()` (never `''`/`'0000'`); see `backend.md` |
| `upload_source` | ENUM('Camera','Manual upload') |
| `status` | ENUM('Processing','Needs review','Uploaded','Rejected','Archived') — default Archived; pending values remain for legacy history/migration |
| `ocr_status` | ENUM('Pending','Processing','Completed','Failed') — self-healed column |
| `ocr_text` | LONGTEXT — full extracted text, also used for full-text-ish `LIKE` search |
| `ocr_error` | TEXT |
| `ocr_processed_at` | DATETIME |
| `ai_summary` | TEXT — **always empty in current backend code**, scaffolding only |
| `ai_detected_category` | VARCHAR(150) — same; a client-side keyword heuristic populates this in `admin/script.js`, not a real backend AI call |
| `uploaded_at`, `archived_at`, `updated_at` | |

Indexes: `(status, uploaded_at)`, `(student_id)`, `(uploaded_by, uploaded_at)`, `(category_id)`, plus a self-healing `FULLTEXT (original_filename, ocr_text)` (`ensure_document_search_index()`) backing the global document search.

**Lifecycle:** a completed intake is physically `rename()`d from `uploads/` into `archive/<academic_year>/` (or `archive/Unsorted/`) during creation, then stored with `status='Archived'` and `archived_at` set. A paired automatically approved review row is retained only as an audit record. Legacy `Processing`/`Needs review` rows are migrated automatically and idempotently.

### `document_reviews`
1:1 with `documents` (`UNIQUE(document_id)`), created automatically alongside every new document row.

| Column | Notes |
|---|---|
| `document_id` | FK, `ON DELETE CASCADE` |
| `reviewed_by` | FK -> users, `ON DELETE SET NULL` |
| `decision` | ENUM('Pending','Approved','Rejected') — default Approved; pending values remain for legacy migration |
| `notes`, `reviewed_at` | notes only really used on rejection |

### `activity_logs`
Append-only audit trail. Currently only written for document Approve/Reject and initial Upload (seed data), i.e. not every mutation is logged — student/college/course/category/user CRUD do **not** write here.

| Column | Notes |
|---|---|
| `user_id` | FK -> users, `ON DELETE SET NULL` |
| `action` | free text, e.g. `UPLOAD`, `APPROVED`, `REJECTED` |
| `entity_type`, `entity_id` | polymorphic reference (`'document'` in all current usage) |
| `description` | human-readable string |
| `metadata` | LONGTEXT with a `CHECK (json_valid(...))` constraint — reserved for structured detail, unused today (always NULL) |

Indexes: `(user_id, created_at)`, `(entity_type, entity_id)`.

### `notifications`
Tells a document's uploader when someone else reviews it.

| Column | Notes |
|---|---|
| `recipient_id` | FK -> users, `ON DELETE CASCADE` |
| `actor_id` | FK -> users, `ON DELETE SET NULL` |
| `title`, `message` | e.g. "Document approved" / "Your uploaded document \"`{filename}`\" was approved and added to the student files." |
| `resource`, `entity_id` | which API resource triggered it, and the created row's id |
| `is_read` | TINYINT(1), bulk-marked (no per-row read API) |

Index: `(recipient_id, is_read, created_at)`.

### `enrollment_years`
Simple lookup of active academic years in use, auto-populated from active students and their matching documents (`sync_enrollment_years()`), or manually via the API. Deleting a year folder removes this row and soft-deletes all active students assigned to that year; their document rows and files remain attached for recycle-bin recovery until a permanent student purge.

| Column | Notes |
|---|---|
| `year` | YEAR(4), UNIQUE |
| `year` | SMALLINT UNSIGNED, UNIQUE; API accepts four-digit values from 1800 through 9999 |
| `created_by` | FK -> users, `ON DELETE SET NULL` |

## Views

### `vw_archive_analytics`
Single-row dashboard summary:
```sql
total_students        -- students.status <> 'Inactive'
total_documents        -- COUNT(*) documents
total_colleges         -- colleges.status = 'Active'
total_courses          -- courses.status = 'Active'
completed_documents    -- documents.status IN ('Uploaded','Archived')
processing_documents   -- documents.status = 'Processing'
documents_needing_review -- documents.status = 'Needs review'
```
Used by `index.php` (public landing metrics) and `api.php?resource=analytics` (extended with queue/review/weekly counts computed alongside it).

### `vw_recent_uploads`
Denormalized document + student + category + uploader join, all document/OCR/AI columns included. Used as the canonical "fetch the row I just inserted" shape after `create_document()` commits (`SELECT * FROM vw_recent_uploads WHERE document_id = :id`), and as the base for the `uploads` resource listing.

## Referential integrity & delete semantics

| FK | On delete | On update |
|---|---|---|
| `activity_logs.user_id -> users.id` | SET NULL | CASCADE |
| `courses.college_id -> colleges.id` | (none specified — restrict) | CASCADE |
| `documents.category_id -> document_categories.id` | (restrict) | CASCADE |
| `documents.student_id -> students.id` | (restrict) | CASCADE |
| `documents.uploaded_by -> users.id` | (restrict) | CASCADE |
| `document_categories.created_by -> users.id` | SET NULL | CASCADE |
| `document_reviews.document_id -> documents.id` | CASCADE | CASCADE |
| `document_reviews.reviewed_by -> users.id` | SET NULL | CASCADE |
| `notifications.actor_id -> users.id` | SET NULL | CASCADE |
| `notifications.recipient_id -> users.id` | CASCADE | CASCADE |
| `students.college_id -> colleges.id` | SET NULL | CASCADE |
| `students.course_id -> courses.id` | SET NULL | CASCADE |

Because `documents.student_id`/`category_id`/`uploaded_by` have no explicit `ON DELETE`, MariaDB defaults to **RESTRICT** — a student, category, or user with existing documents cannot be deleted at the DB level. The API works around this for users: `handle_user_write()`'s `delete` action first reassigns that user's documents (`uploaded_by`) to the current user before deleting the user row, inside a transaction.

## Seed / sample data present in `dats.sql`
The dump ships with real-looking sample rows: 4 users (2 `Active`, 2 `Inactive` — all equivalent now that there's no role column), 4 colleges (COED, CBA, CICS, CET), 6 courses, 6 students, 10 document categories, 8 documents (mix of Archived/Rejected), 8 document_reviews, 1 notification, 1 enrollment_year (2026). Treat this as **development/demo data**, not production data — passwords are bcrypt hashes of unknown plaintext, emails follow `@zppsu.edu.ph`.

## Notable data-model gaps / inconsistencies
- `status` on `colleges`/`courses`/`document_categories` implies soft-delete, but `colleges`/`courses` are actually **hard-deleted** by the API (`delete_college`, `delete_course`) while `document_categories` are **never hard-deleted** through any endpoint (no delete handler exists for categories at all).
- `ai_summary`/`ai_detected_category` are real columns with no server-side AI producing them — see `backend.md`.
- `activity_logs` is not comprehensively used — most CRUD operations (students, colleges, courses, categories, users) leave no audit trail, only document approve/reject does.
