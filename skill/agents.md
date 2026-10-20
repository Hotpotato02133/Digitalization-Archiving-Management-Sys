# DATS — Project Overview

**DATS** (Digitalization Archiving Management System) is a records-management web app built for the **ZPPSU Registrar's Office** (Zamboanga Peninsula Polytechnic State University). It lets Registrar staff scan/upload student documents (transcripts, certificates, diplomas, etc.), extract text via OCR, and archive them immediately in a searchable, per-student, per-college/course structure — replacing paper filing with a digital one. Every account is an **Admin** account — there is a single, undifferentiated user type with full access to the whole app.

This file is the entry point. For depth, see the companion docs in this same `skill/` folder:

- **[frontend.md](frontend.md)** — pages, routing/auth guards, the shared `data-sync.js` data-binding layer, the scan/camera/OCR client flow, CSS design system, CDN dependencies.
- **[backend.md](backend.md)** — `api.php` endpoint reference, auth/session mechanics, document review workflow, file storage conventions, security posture, OCR/AI implementation reality.
- **[database.md](database.md)** — full schema (tables, views, FKs, delete semantics), connection config, seed data, known data-model inconsistencies.

## Tech stack at a glance

| Layer | Technology |
|---|---|
| Language | PHP 8.2 (typed, `declare(strict_types=1)` in core files) |
| Framework | **None** — plain procedural PHP, files served directly (no front controller/router) |
| Database | MariaDB 10.4 (MySQL-compatible), accessed via PDO with real prepared statements |
| Frontend | Server-rendered HTML + vanilla JavaScript (no framework) + hand-written CSS (no Tailwind/Bootstrap) |
| Auth | Native PHP sessions, bcrypt password hashing, single undifferentiated user type (no role/permission tiers) |
| File storage | Local filesystem (`uploads/`, `uploads/colleges/`, `archive/`) — no S3/cloud storage |
| OCR | Server: Tesseract OCR binary (if installed) via `exec()`/`proc_open()`, plus Ghostscript for PDF page rasterization (Tesseract can't read PDFs natively). Client fallback: Tesseract.js + pdf.js (CDN) |
| "AI" categorization | Client-side keyword heuristic only — **no real AI/LLM integration** despite `ai_summary`/`ai_detected_category` DB columns |
| Icons/fonts/PDF | Lucide (CDN), Google Fonts (DM Mono, Manrope), jsPDF (CDN, for report export) |
| Package management | None — no Composer, no npm; every dependency is a CDN `<script>` tag |
| Deployment assumption | Apache/XAMPP-style, app rooted at `/DATS/` (hardcoded in one client-side fetch helper) |

## Repository layout

```
DATS/
├── index.php, login.php, logout.php, api.php   # public entry + core API
├── auth.php                                      # session/auth helpers
├── config/database.php                           # PDO factory, env config, JSON helper
├── dats.sql                                       # full schema + seed data dump
├── admin/                                         # all authenticated app pages (login-gated, not role-gated)
├── uploads/, uploads/colleges/, archive/          # document & logo storage
├── img/                                           # static branding assets
├── styles.css, script.js, profile.css             # public/shared frontend assets
├── data-sync.js                                   # shared client-side data-binding layer
└── skill/                                         # this documentation set
```

## Core domain model (one-paragraph version)

A `user` uploads a `document` on behalf of a `student` (who belongs to a `college` → `course`), tagged with a `document_category` (TOR, COR, COE, etc.) and an `academic_year`. The document is moved directly into `archive/<academic_year>/`, stored with `status='Archived'`, and paired with an automatically approved `document_review` row for audit history. `activity_logs` records the archive event. Two SQL views (`vw_archive_analytics`, `vw_recent_uploads`) power the dashboards.

## One role, full access

Every account can do everything: users, colleges/courses, categories, students, scanning/upload, files/bulk-download, and analytics. There is no permission tier below this — `dats_require_login()` (see `backend.md`) only checks that someone is signed in, not what they're allowed to do. See `frontend.md` for the full page-by-page breakdown.

## Where to start for common tasks

| Task | Look at |
|---|---|
| Add/change an API endpoint or business rule | `api.php` (dispatcher + handler functions) — see `backend.md` |
| Add a new page or change existing UI | matching file under `admin/`, plus `data-sync.js` if it needs live data — see `frontend.md` |
| Change the schema | `dats.sql`, then re-import; remember `ensure_document_ocr_columns()`/`ensure_enrollment_years_table()` self-heal two specific tables at runtime — see `database.md` |
| Investigate a security or data-integrity question | `backend.md` §Security posture and `database.md` §Notable data-model gaps |
| Understand the OCR/"AI" feature | `backend.md` §Key business logic (server reality) and `frontend.md` §admin/script.js (client heuristics) |

## Known project-wide caveats worth remembering

- No test suite, no CI config, no `.env.example` — configuration is entirely via OS environment variables (`DATS_DB_HOST/NAME/USER/PASSWORD`) with hardcoded local-dev defaults (`127.0.0.1` / `root` / empty password).
- No CSRF protection; relies on `SameSite=Lax` cookies.
- "AI-detected category" and "AI summary" are marketing-adjacent naming for a keyword heuristic — there is no LLM call in this codebase today.
- The app assumes it's served from `/DATS/` (see `frontend.md`) — moving the deployment path requires updating `data-sync.js`.
- Not currently a git repository (`git status` shows no `.git` — verify before assuming version-control workflows apply).
