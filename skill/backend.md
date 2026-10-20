# DATS — Backend Reference

**Stack:** Plain PHP 8.2 (no framework — no Laravel/Symfony/Slim), PDO/MySQL for persistence, native PHP sessions for auth, filesystem for document storage. No Composer/`vendor/` present — zero third-party PHP packages. Entry points are called directly as `.php` files (typical XAMPP/Apache or PHP built-in server deployment), not routed through a front controller.

## File map

| File | Role |
|---|---|
| `config/database.php` | Env-driven PDO factory (`dats_db()`), `dats_env()`, `dats_json()` helper |
| `auth.php` | Session bootstrap, `dats_current_user()`, `dats_login()`, `dats_require_login()`, `dats_logout()`, `dats_user_initials()` |
| `login.php` | POST-only JSON login endpoint |
| `logout.php` | Destroys session, redirects to `index.php` |
| `api.php` | The entire application API — single-file resource-based dispatcher (~1200 lines) |
| `index.php` | Public landing page, reads `vw_archive_analytics` + latest 4 documents directly |
| `admin/*.php` | Server-rendered, login-gated pages (no role tiers); mostly thin shells whose data comes from `api.php` client-side (a few pages, noted below, run their own direct queries) |

## Auth (`auth.php`)

There is no role/permission concept anywhere in auth — every signed-in account has identical access. (This used to be an Admin/Staff split; the Staff role and the `users.role` column were removed.)

- Session cookie hardened: `httponly=true`, `samesite=Lax`, `secure` mirrors `$_SERVER['HTTPS']`.
- `dats_login($email, $password)`: looks up `users` by `email + status='Active'`, verifies with `password_verify()`, calls `session_regenerate_id(true)` (fixation mitigation), stores `user_id`/`full_name` in `$_SESSION`, updates `last_login_at`.
- `dats_current_user()`: memoized per-request; re-validates against DB every call chain start (`status='Active'` re-checked, so deactivating a user invalidates their session on next lookup).
- `dats_require_login()`: used at the top of every `admin/*.php` page; **redirects** (302 to `../index.php`) if there's no current user, rather than returning a 4xx — no distinct "forbidden" page, and no role check (just "signed in or not").
- `dats_logout()`: clears `$_SESSION`, expires the cookie, `session_destroy()`.
- Password hashing: `password_hash(..., PASSWORD_DEFAULT)` (bcrypt on current PHP), min 8 chars enforced app-side on create/update.

`login.php` is the only endpoint that accepts unauthenticated POSTs; it takes just `{email, password}` (no role field — there's only one kind of account), 401s on bad credentials, and returns `{message, redirect: 'admin/index.php'}` on success — the actual redirect is performed client-side by `script.js`.

## `api.php` — request lifecycle

1. `require auth.php` → 401 JSON if no current user.
2. `dats_db()` → 503 JSON if DB unreachable.
3. `$resource = strtolower($_GET['resource'])`, `$method = $_SERVER['REQUEST_METHOD']`.
4. Body: `$_POST` if present, else JSON-decoded `php://input`.
5. `ensure_document_ocr_columns($pdo)` and `ensure_document_search_index($pdo)` run on **every** call (idempotent `ALTER TABLE ADD COLUMN/INDEX IF NOT EXISTS`) — self-migrating schema, no dedicated migration step needed after a fresh `dats.sql` import predating the OCR columns or the OCR-text FULLTEXT index.
6. Dispatch via `match($resource)` for GET, and again for POST; a few resources (`document-view`, `document-download`, `document-download-bulk`) are special-cased *before* the `match` because they stream binary responses and `exit` early rather than returning JSON.
7. Unknown resource → 404 `{error:'Unknown resource.'}`. Any thrown `Throwable` in the whole block → 422 `{error: message}` (no differentiation between validation errors, `InvalidArgumentException`, and unexpected server errors — all become 422).

There is no per-resource role gate anymore — every resource below is reachable by any authenticated user. (There used to be a blanket admin-only POST gate for `users|colleges|courses|reviews|enrollment-years` plus a few individual role checks; both were removed along with the Staff role, since there's no longer a lower tier to gate against.)

All JSON responses go through `dats_json()` (`config/database.php`) — sets `Content-Type: application/json`, `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`, and calls `exit`.

## Endpoint reference

### GET

Every resource below is available to any signed-in user (no role distinction).

| `resource` | Function | Returns |
|---|---|---|
| `analytics` | `get_analytics()` | `vw_archive_analytics` row + queue/review/processed-this-week/last-week counts + `archive_by_college` |
| `annual-report` | `get_annual_report()` | summary counts, by-year, by-category, by-college breakdowns |
| `students` | inline | all students + college/course name + document_count |
| `categories` | inline | active categories + document_count |
| `documents` | `get_student_documents()` or `get_document_search_results()` | student's own docs (`?student_id=`), or global search over filename/OCR text/student/category (`?search=`) |
| `enrollment-years` | `get_enrollment_years()` | years + student/document counts (auto-synced first) |
| `uploads` | `get_uploads()` | documents by uploader (`?user_id=`; omitted means all uploads) |
| `activity` | inline | last 50 `activity_logs` joined to `users` |
| `notifications` | `get_notifications()` | current user's last 20 notifications |
| `profile` | — | current session user record |
| `requests` | inline | top 3 recently archived documents for the dashboard |
| `colleges` | `get_colleges()` | active colleges nested with active courses + student counts |
| `users` | inline | all users (id, name, email, status, last_login_at, created_at) |
| `document-view` | `handle_document_view()` | streams file inline (viewer) |
| `document-download` | `handle_document_download()` | streams file as attachment |

### POST

Every resource below is POST-able by any signed-in user (no role distinction).

| `resource` | Function | Notes |
|---|---|---|
| `document-download-bulk` | `handle_document_download_batch()` | ZIP of a student's documents (all or selected IDs) |
| `users` | `handle_user_write()` | create / update / delete (reassigns docs, then removes user, transactional) |
| `categories` | `create_category()` / `update_category()` | keyed off `action` in body |
| `colleges` | `create_college()` / `update_college()` / `delete_college()` | multipart for logo upload |
| `courses` | `create_course()` / `update_course()` / `delete_course()` | |
| `students` | `create_student()` / `update_student()` / `delete_student()` | resolves college/course by id or by name; upserts `enrollment_years` |
| `document-ocr` | `handle_document_ocr()` | standalone OCR pre-check upload (used before final archive) |
| `documents` | `create_document()` | direct archive action: stores/runs OCR, moves into the year folder, inserts an Archived document + Approved audit row |
| `enrollment-years` | `create_enrollment_year()` | manual year entry |
| `notifications` | `mark_notifications_read()` | marks *all* of the caller's unread as read |
| `profile` | `update_profile()` | update own name/email/password |

## Key business logic

**Direct archive workflow** (`create_document()`): the upload and OCR metadata are prepared, then the file is `rename()`d into `archive/<academic_year>/` (or `archive/Unsorted/`) inside the intake transaction. The document is inserted as `Archived`, an automatically approved `document_reviews` audit row is created, and the archive event is logged. If the DB work fails, the newly moved file is removed as filesystem compensation. `migrate_pending_documents_to_archive()` performs the same year-folder move and approval update idempotently for legacy pending rows.

**Path traversal defense** (`safe_document_path()`): rejects any path containing `..`, resolves with `realpath()`, and requires the resolved path to start with the app root — used by every file-serving function (`handle_document_view`, `handle_document_download`, `handle_document_download_batch`). All three also scope lookups by `document_id AND student_id` together, so a forged document ID can't be used to fetch another student's file even before the path check runs. It transparently handles the nested `archive/<year>/...` paths above with no special-casing needed.

**Bulk download** (`handle_document_download_batch()`): builds a temp ZIP via `ZipArchive` (500 if the extension is missing — **not currently installed on this dev box**, so bulk download 500s until the `zip` PHP extension is enabled), either all of a student's documents (`download_all`, parsed with `FILTER_VALIDATE_BOOLEAN`) or an explicit `document_ids[]` array — always additionally constrained to `student_id` in the SQL `IN (...)` clause, deduplicates in-zip filenames (`_1`, `_2`, ...), streams in 8KB chunks, deletes the temp file afterward.

**Academic year normalization** (`normalize_academic_year()`): extracts a single valid 4-digit year (2000–2100) from arbitrary input ("2026-2027", "AY 2026-2027", "2026") and returns `null` for anything else. `create_document()` requires this to succeed (throws otherwise) — previously a blank/malformed academic year silently stored as literal `'0000'` in the `YEAR(4)` column (verified against a live MariaDB instance) and was then invisible to `sync_enrollment_years()`/the Files year-folder browser forever. The scan UI also requires and auto-suggests a year now (see `frontend.md`), so this is defense-in-depth, not the primary guard.

**OCR pipeline** (`detect_server_ocr_binary()` / `detect_server_ghostscript_binary()` / `run_server_ocr_if_available()`): looks for a real **Tesseract OCR** binary at known Windows paths (Program Files, XAMPP) or Unix paths, or via `PATH`/`where`/`which`. If found, shells out via `run_tesseract_on_image()` (`tesseract <file> stdout --psm 6`, wrapped with a hard timeout via `run_shell_command_with_timeout()` using `proc_open`) to get real `ocr_text`. **PDFs are rasterized to per-page PNGs via Ghostscript first** (`run_server_ocr_on_pdf()`, capped at `DATS_OCR_MAX_PDF_PAGES` pages) since Tesseract cannot read PDFs natively — if Ghostscript isn't installed, PDF OCR defers to the client the same way missing Tesseract does. If no server OCR binary is found at all, returns `ocr_status: 'Processing'` with an explanatory `ocr_error`, deferring to whatever the client already computed (client-side Tesseract.js fallback — see `frontend.md`). Both binary paths and the file path are stripped of quote characters before shell interpolation; actual filenames are always the app's own randomized names, limiting injection surface. `create_document()` skips re-running server OCR when the client already sent a `Completed` result (the client just ran OCR moments ago on the same file during the scan preview step), only falling back to the server pass as a safety net.

**Upload validation** (`validate_uploaded_document_file()`): allow-lists `image/jpeg`, `image/png`, `application/pdf` by real sniffed MIME type (via `finfo`, never the client-supplied type) and enforces a 25MB cap, used by both `handle_document_ocr()` and `create_document()`. This closed a prior gap where general document uploads had no MIME/size validation (college logos already had this).

**"AI" fields**: `ai_summary` and `ai_detected_category` are still populated by a client-side keyword-matching heuristic in `admin/script.js`, not a real LLM/vision call — the server only ever threads through whatever the client computed. The Pending Reviews queue now surfaces this to the reviewer (a note flags when the AI-detected category disagrees with the filed one) and lets them expand the raw extracted text before approving — see `frontend.md`.

**Notifications**: `update_review()` notifies a document's uploader when someone *else* reviews it ("Document approved"/"Document review result"), via a plain `recipient id !== reviewer id` check — no read receipts beyond a bulk "mark all read". (There used to also be a broadcast-to-all-admins notification, `create_staff_notification()`, fired on every Staff POST; it was removed along with the Staff role since it existed specifically to surface Staff activity to Admins.)

**Enrollment years**: self-healing table (`ensure_enrollment_years_table()` creates it + FK if missing) kept in sync with active students and their matching document years (`sync_enrollment_years()`), plus manual entries via the API. `upsert_enrollment_year()` is called from `create_student`/`update_student`/`create_document` to keep the lookup current automatically. Deleting a year folder is a transactional soft delete: every active student whose `academic_year` matches the folder is marked with `deleted_at`, the `enrollment_years` row is removed, and the students' documents/files remain recoverable until each student is permanently purged from the Files-page Recycle Bin.

## File storage conventions

| Directory | Contents | Naming |
|---|---|---|
| `uploads/` | Temporary staging while OCR and intake validation run | `bin2hex(random_bytes(12))` + `_` + sanitized original filename (`[^A-Za-z0-9._-]` stripped) |
| `uploads/colleges/` | College logos | `bin2hex(random_bytes(16))` + real extension (from validated MIME, not client-supplied extension) |
| `archive/<year>/` | Directly archived documents, physically foldered by `academic_year` (`Unsorted/` if none) | `bin2hex(random_bytes(12))` + `_` + `basename(stored_filename)`, moved via `rename()` from `uploads/` |

Rejected documents' files are deleted outright; the DB row is kept (status `Rejected`, file columns nulled) purely as history.

## Security posture

- **SQL injection**: consistently parameterized (`PDO::prepare`) wherever user input is involved; `PDO::ATTR_EMULATE_PREPARES=false` forces real prepared statements. A handful of `lastInsertId()` follow-up `SELECT`s interpolate a value directly into SQL, but only after `(int)`-casting it, so it's not exploitable.
- **Path traversal**: centrally handled by `safe_document_path()` (see above); all served filenames are server-generated random names, not user input.
- **File upload validation**: strong for college logos (`store_college_logo()` — size cap, `is_uploaded_file()`, `getimagesize()`, real MIME sniffed via `finfo` against an allowlist, extension forced from MIME not client input), and now equally strong for archived documents via `validate_uploaded_document_file()` (real-MIME allowlist of JPG/PNG/PDF, 25MB cap) used by both `create_document()` and `handle_document_ocr()`.
- **Search query construction**: `get_document_search_results()` previously reused the same named PDO placeholder (`:search`) five times in one query — with `EMULATE_PREPARES=false` (real server-side prepares), PDO_MySQL throws `SQLSTATE[HY093]: Invalid parameter number` on any repeated named parameter, so **every non-empty document search crashed with a 422** until this was fixed to use distinct placeholder names per occurrence. Verified directly against the live DB before and after the fix. Worth remembering if you ever see this error again elsewhere in `api.php` — grep for a placeholder appearing more than once inside a single `->prepare()` string.
- **No permission tiers**: there is exactly one kind of account. `dats_require_login()` gates which *pages* an unauthenticated visitor can load (signed in or not, nothing finer), and `api.php` has no per-resource role checks at all — every resource is reachable by any signed-in user. This used to be a two-role (Admin/Staff) system with a page-level guard and a separate, partially-inconsistent set of API-level role checks; both layers and the `users.role` column were removed together, so there's no longer a gap between them to worry about.
- **Session hardening**: `httponly`, `SameSite=Lax`, `secure` on HTTPS, session ID regenerated on login. No CSRF token anywhere — reliant entirely on `SameSite=Lax` + `credentials:'same-origin'` fetches for cross-site protection; state-changing GET requests don't exist, which helps, but POST endpoints have no anti-CSRF token.
- **Password storage**: bcrypt via `password_hash()`/`password_verify()`, 8-char minimum, no complexity rules, no rate limiting on `login.php` (no lockout/backoff observed).
- **Command execution**: the only `exec()` calls are the OCR binary detection/invocation, operating on trusted, randomly-named local files — not directly attacker-controlled paths.
- **Error handling**: a single catch-all `catch (Throwable $error)` around the whole request maps every failure (validation, DB, filesystem) to HTTP 422 with the raw exception message — convenient for a small app but means unexpected internal errors also leak their message text to the client (no distinction between "your input was bad" and "something broke").

## Notes on `admin/*.php` direct DB access

Most `admin/*.php` pages contain **no** direct SQL — they're static shells hydrated client-side by `data-sync.js` calling `api.php`. Three pages are exceptions:
- `admin/index.php` — a few inline aggregate counts (documents total / processing / needs-review) plus a **disk usage scan** of `archive/` and `uploads/` via `RecursiveDirectoryIterator`. Much of the rest of the dashboard markup is static placeholder HTML, not DB-driven.
- `admin/students.php` — a few inline counts; the visible table rows in the raw markup are hardcoded sample names (real data is rendered over/replaces this client-side via `data-sync.js`).
- `admin/student-record-view.php` — two prepared, properly parameterized queries (student lookup, and a document lookup scoped to `document_id AND student_id`) to render a single-record preview page; embeds an `<img>`/`<iframe>` pointing back at `api.php?resource=document-view`.
