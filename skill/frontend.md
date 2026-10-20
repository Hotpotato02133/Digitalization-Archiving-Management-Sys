# DATS — Frontend Reference

**Stack:** No JS framework, no bundler, no CSS framework. Server-rendered PHP pages (`.php` files serving full HTML documents) + hand-written vanilla JS + hand-written CSS. Icons via **Lucide** (CDN), fonts via **Google Fonts**, PDF export via **jsPDF** (CDN), OCR via **Tesseract.js** + **pdf.js** (CDN, lazy-loaded). This is effectively a classic multi-page app (MPA) with a thin client-side "data-binding" layer, not a SPA.

## Page architecture

Every authenticated page follows the same pattern:

1. `require_once '../auth.php'; dats_require_login();` — hard server-side redirect to `../index.php` if there's no signed-in session (see `backend.md`). This is the only route guard; there's no client-side auth check, and there's no role/permission check either — being signed in is the only gate.
2. Some pages compute a handful of values server-side with inline `<?= ... ?>` (e.g. `admin/index.php`'s disk-usage bar, `admin/students.php`'s header counts) — these are the exception, not the rule.
3. Static HTML markup for the page shell (nav, cards, table skeletons, modal skeletons — all `hidden` or empty until JS fills them).
4. Bottom of `<body>`: `../data-sync.js` (almost always) + one or two page JS files.

No CSRF tokens, no client router, no component system — DOM is queried by ID/class and mutated directly (`innerHTML`, `replaceChildren`, template-string HTML).

### Page inventory

All authenticated pages live under `admin/` — there used to be a parallel `staff/` tree, but the Staff role was removed and every account now has full access through these same pages.

| Page | JS loaded | CSS loaded | Purpose |
|---|---|---|---|
| `index.php` (root) | `script.js` | `styles.css` | Marketing/landing + login modal (email/password, no role picker) |
| `admin/index.php` | data-sync.js + `admin/script.js` | `admin/styles.css` | Dashboard (KPIs, storage usage, recent activity) |
| `admin/analytics.php` | same | same | Charts/report + jsPDF export |
| `admin/categories.php` | data-sync.js + `admin/categories.js` | `admin/styles.css` + `admin/categories.css` | Category CRUD |
| `admin/colleges.php` | `admin/script.js` | `admin/styles.css` (+ inline overrides) | College/course CRUD, logo upload |
| `admin/scan.php` | `admin/script.js` | `admin/styles.css` (+ inline) | Camera capture / manual upload / OCR |
| `admin/students.php` | `admin/script.js` | `admin/styles.css` + `admin/student-table.css` | Student directory CRUD |
| `admin/users.php` | `admin/script.js` | `admin/styles.css` + `user-actions.css` | User management (no role field — every account is equivalent) |
| `admin/files.php` | data-sync.js + `admin/script.js` + a large inline page script | `admin/styles.css` (+ large inline block) | Year-folder browser, preview, bulk ZIP download |
| `admin/student-record-view.php` | none | fully inline, standalone | Single document preview + student info panel |
| `admin/settings.php` | data-sync.js + `admin/script.js` | `admin/styles.css` + `settings.css` + `dashboard-buttons.css` | Account settings (name, email, password) |

`admin/categories.js` was relocated from the old `staff/script.js` (it's still the same ~210-line file: dashboard greeting, sidebar toggle, student-modal helpers, and the "add/edit category" flow that `admin/categories.php` depends on) — most of its no-op-guarded code (history-table filtering, `[data-action]` routing) is now dead weight since nothing on `categories.php` uses it, but it was left in place since removing it isn't required for correctness.

## `data-sync.js` — the shared data-binding layer

Loaded on nearly every authenticated page. A single `DOMContentLoaded` handler probes the DOM for known selectors and, only if present, fetches from `api.php` and renders — every block is `if (element) {...}`-guarded, so it's safe to include everywhere.

Key behaviors:
- Live clock/greeting refresh every 60s (`[data-current-datetime]`, `[data-greeting]`).
- `request(resource)` helper: `GET {origin}/DATS/api.php?resource=X` with `credentials:'same-origin'`. **Note:** the origin path is hardcoded to `/DATS/` — the app assumes it's deployed at that exact subpath (typical of an XAMPP `htdocs/DATS` layout); moving it to a different base path or domain root would break these calls.
- Dynamically injects `profile.css` and builds a profile-edit modal from scratch, opened via `.user-chip` — `POST ?resource=profile`.
- Notification bell dropdown — `GET ?resource=notifications`, auto marks-as-read via `POST ?resource=notifications` when opened with unread items present.
- Live "recent uploads" refresh on the custom event `dats:uploads-changed` (dispatched by `admin/script.js` after a successful archive) — the one place the app avoids a full reload/refetch cycle for freshness.
- Populates dashboard `[data-stat="..."]` / `[data-staff-stat="..."]` values from `analytics` (the `data-staff-stat` attribute name is a naming leftover from before the Staff role was removed — it's just a dashboard widget selector today, not a role check).
- Builds `#studentRows`, `#categoryGrid`, `#historyRows`, `#userRows` (no role column/badge — every user is equivalent), `#collegeList`, the activity timeline, and the recently archived list entirely client-side from JSON.
- The Files page's year-folder delete action requires confirmation, then moves every active student in that year to the existing Recycle Bin while leaving their documents recoverable until a permanent purge.
- Populates the scan pages' student datalist and category `<select>`.

## `admin/script.js` — scan/OCR/CRUD engine

The most complex client file; covers:

- **Camera capture**: `getUserMedia({video:{facingMode:'environment'}})`; falls back to a hidden `<input type=file capture="environment">` when unsupported. Capture button draws the live `<video>` frame onto a `<canvas>` and converts it to a `File` named `camera-capture.jpg`. The markup for this (`#startCamera`/`#cameraVideo`/`#cameraPlaceholder`/`#captureButton`/`#cameraCapture`) lives in `admin/scan.php` as a first "Scan with camera" panel alongside "Upload document" — the JS silently no-ops on missing elements, which is what let this same file (and this markup) be reused on the old `staff/scan.php` before the Staff role was removed.
- **Manual upload**: drag-and-drop zone (`dragenter/dragover/dragleave/drop`) + click-to-browse, with `URL.createObjectURL` previews for images/PDFs in a grid.
- **OCR flow runs on file select/capture, not just at archive time**: as soon as a file is chosen (upload, drop, or camera capture/device-camera), it tries **server OCR first** (`POST api.php?resource=document-ocr`, which shells out to a local Tesseract binary — plus Ghostscript for PDF rasterization — if the server has them installed; see `backend.md`). If the server has no OCR engine installed (`ocr_status` comes back `Processing`/`Pending`, meaning it never actually attempted extraction), the client falls back to **client-side OCR**: lazy-loads `tesseract.js@5` from jsDelivr, and for PDFs additionally lazy-loads `pdfjs-dist@4.10.38` to rasterize pages to canvas before running OCR on the image. A live `#scanOcrBanner` shows the extracted-text summary, auto-selects the Document Type dropdown to the best `inferDocumentCategory()` match, and auto-fills Academic Year from a year pattern found in the text (`detectAcademicYearFromText()`) — both stay editable, and once the uploader edits either field manually it stops being auto-overwritten. The result is cached (`lastOcrResult`/`lastOcrFileKey`) so "Add to archive" reuses it instead of re-running OCR.
- **Academic Year is now required and validated** (4-digit, 2000–2100) before "Add to archive" is enabled — previously optional and free-text, which silently stored `'0000'` server-side for any blank submission (see `backend.md`).
- **"AI" category detection**: a hardcoded keyword-matching table (`documentCategoryHints`) scores OCR text against known category names client-side — this is the entire "AI detected category" feature; there is no real AI/LLM call (confirmed absent on the backend too — see `backend.md`). The detected type is shown during intake for the administrator to verify before direct archiving.
- Final submission: `POST api.php?resource=documents` as `FormData` (file + OCR text/status/error + AI summary/category); the server always archives the document immediately, regardless of OCR success, while retaining OCR status/error metadata; on success the form resets and dispatches `dats:uploads-changed` so dashboards refresh live.
- Also implements user CRUD, college/course CRUD (including logo upload), student CRUD, and a document viewer dialog — all via the same fetch-JSON-render pattern, with no shared API-client abstraction.

## API surface consumed from the client

**GET** `analytics`, `annual-report`, `students`, `categories`, `documents` (`?student_id=` or `?search=`), `enrollment-years`, `uploads` (`?user_id=`), `activity`, `notifications`, `profile`, `requests`, `colleges`, `users`, `document-view`, `document-download`.

**POST** `users`, `categories`, `colleges` (multipart, logo), `courses`, `students`, `document-ocr` (multipart), `documents` (multipart — the direct archive action), `enrollment-years`, `notifications`, `profile`, `document-download-bulk`.

All requests use `credentials:'same-origin'` (cookie session auth, no bearer tokens); uploads use `FormData`, everything else JSON. See `backend.md` for full request/response semantics — every resource is available to any signed-in user, there's no per-resource role gating anymore.

## Design system / CSS

No Tailwind, no Bootstrap — 100% hand-written CSS with custom properties. **Two independently-maintained, near-duplicate palettes**:

- `styles.css` (public landing page): `--ink:#2a171a`, `--muted:#75686a`, `--green:#6b1d2a` (maroon, misleadingly named), `--lime:#d6ad3a` (actually gold).
- `admin/styles.css`: `--maroon:#6b1d2a`, `--maroon-dark:#4a111b`, `--gold:#d6ad3a`, `--gold-pale:#f8efd5`. (There used to be a byte-for-byte duplicate `staff/styles.css`; it was deleted along with the rest of `staff/` when the Staff role was removed.)

Shared type stack across all CSS files: `--mono: 'DM Mono'`, `--sans: 'Manrope'` (both Google Fonts). Responsive breakpoints vary per file (850px/780px/640px/520px), `clamp()` used for fluid type, and a global `prefers-reduced-motion` media query disables transitions/animations.

Recurring components are reimplemented per file rather than shared as JS widgets: modal backdrops (`*-modal-backdrop[hidden]` pattern, ~8 separate instances), toasts (`#adminToast`/root `#toast`, same show/hide-timeout logic duplicated in each JS file), search/filter toolbars, and dynamically-built row-action dropdown menus (`.student-action-menu`, `.user-action-menu`) that close on outside-click.

## Third-party CDN dependencies

| Library | Source | Notes |
|---|---|---|
| Lucide icons | `https://unpkg.com/lucide@latest` | **Unpinned** — `@latest` is a stability/supply-chain risk worth pinning. Used via `data-lucide="..."` + `lucide.createIcons()` re-invoked after every dynamic DOM insert. |
| Google Fonts (DM Mono, Manrope) | `fonts.googleapis.com` | Loaded on every page. |
| jsPDF | `cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js` | Pinned version; only on analytics pages, for report export. |
| Tesseract.js | `cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js` | Lazy-loaded only when server OCR isn't available. |
| pdf.js | `cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs` (+ worker) | Lazy `import()`, used to rasterize PDF pages for client-side OCR. |

## Things to watch when extending the frontend

- The hardcoded `/DATS/` base path in `data-sync.js`'s `request()` helper will break if the app is deployed under a different path/domain.
- No shared "API client"/fetch wrapper exists — every call site duplicates fetch/JSON/error/toast handling; a small shared helper would reduce drift.
- Lucide is pinned to `@latest`; consider pinning an exact version for reproducibility.
- Static/hardcoded sample markup still exists in a couple of admin pages (e.g. `admin/students.php`'s raw table rows) that gets overwritten client-side — harmless at runtime but worth cleaning up so the source doesn't mislead future readers.
- `admin/categories.js` (relocated from the old `staff/script.js`) still carries some now-unreachable code paths (history-table filtering, a `[data-action]` router with stale copy) left over from when it was shared across the Staff dashboard, history, and categories pages — safe to trim opportunistically since none of it matches anything on `admin/categories.php`.
